<?php

namespace App\Services;

use App\Models\InstalledModule;
use App\Models\PluginProduct;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use RuntimeException;
use ZipArchive;

final class PluginInstaller
{
    public function install(PluginProduct $plugin, int $installerId): InstalledModule
    {
        $artifact = $this->artifactPath($plugin->artifact_path);
        $this->assertHash($artifact, $plugin->artifact_sha256);

        $zip = new ZipArchive();
        $zipOpened = false;
        if ($zip->open($artifact) !== true) {
            throw new RuntimeException('تعذر فتح ملف الإضافة المضغوط.');
        }
        $zipOpened = true;

        $stage = storage_path('app/plugins/staging/' . Str::uuid());
        File::ensureDirectoryExists($stage);
        $backup = null;

        try {
            $this->validateEntries($zip);
            if (!$zip->extractTo($stage)) {
                throw new RuntimeException('تعذر فك ضغط الإضافة في المساحة المؤقتة.');
            }
            $zip->close();
            $zipOpened = false;

            $moduleRoot = $this->resolveModuleRoot($stage);
            $manifest = $this->readManifest($moduleRoot);
            $this->assertManifest($plugin, $manifest);

            $destination = base_path('Modules/' . $plugin->module_name);
            if (File::isDirectory($destination)) {
                $backup = storage_path('app/plugins/backups/' . $plugin->module_name . '/' . now()->format('YmdHis'));
                File::ensureDirectoryExists(dirname($backup));
                File::moveDirectory($destination, $backup);
            }
            File::ensureDirectoryExists(dirname($destination));
            File::moveDirectory($moduleRoot, $destination);

            Artisan::call('optimize:clear');
            Module::find($plugin->module_name)?->enable();

            return InstalledModule::updateOrCreate(
                ['module_name' => $plugin->module_name],
                [
                    'plugin_product_id' => $plugin->id,
                    'installed_by' => $installerId,
                    'version' => (string) ($manifest['version'] ?? $plugin->version),
                    'path' => 'Modules/' . $plugin->module_name,
                    'status' => 'installed',
                    'config' => is_array($manifest['config'] ?? null) ? $manifest['config'] : [],
                    'installed_at' => now(),
                    'last_error' => null,
                ],
            );
        } catch (\Throwable $error) {
            if ($zipOpened) {
                $zip->close();
            }
            $destination = base_path('Modules/' . $plugin->module_name);
            if ($backup && File::isDirectory($backup)) {
                File::deleteDirectory($destination);
                File::moveDirectory($backup, $destination);
            }
            throw $error;
        } finally {
            File::deleteDirectory($stage);
        }
    }

    private function artifactPath(?string $relativePath): string
    {
        if (!$relativePath || Str::contains($relativePath, ['..', '\\']) || Str::startsWith($relativePath, ['/'])) {
            throw new RuntimeException('مسار ملف الإضافة غير صالح.');
        }
        $path = Storage::disk('local')->path($relativePath);
        if (!File::exists($path) || !Str::endsWith(Str::lower($path), '.zip')) {
            throw new RuntimeException('ملف الإضافة غير موجود أو ليس ZIP.');
        }
        return $path;
    }

    private function assertHash(string $path, ?string $expected): void
    {
        if ($expected && !hash_equals(strtolower($expected), strtolower(hash_file('sha256', $path)))) {
            throw new RuntimeException('فشل التحقق من بصمة ملف الإضافة.');
        }
    }

    private function validateEntries(ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!$name || Str::startsWith($name, ['/']) || Str::contains($name, ['../', '..\\', "\0"])) {
                throw new RuntimeException('يحتوي ملف الإضافة على مسار غير آمن.');
            }
            $stats = $zip->statIndex($index);
            if ($stats && (($stats['external_attributes'] ?? 0) & 0xF000) === 0xA000) {
                throw new RuntimeException('الروابط الرمزية غير مسموحة داخل الإضافة.');
            }
        }
    }

    private function resolveModuleRoot(string $stage): string
    {
        if (File::exists($stage . '/module.json')) {
            return $stage;
        }
        $directories = collect(File::directories($stage))->filter(fn (string $path) => File::exists($path . '/module.json'))->values();
        if ($directories->count() !== 1) {
            throw new RuntimeException('يجب أن يحتوي ZIP على module.json في الجذر أو داخل مجلد وحدة واحد.');
        }
        return $directories->first();
    }

    private function readManifest(string $moduleRoot): array
    {
        $manifest = json_decode(File::get($moduleRoot . '/module.json'), true);
        if (!is_array($manifest)) {
            throw new RuntimeException('ملف module.json غير صالح.');
        }
        return $manifest;
    }

    private function assertManifest(PluginProduct $plugin, array $manifest): void
    {
        if (($manifest['name'] ?? null) !== $plugin->module_name || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $plugin->module_name)) {
            throw new RuntimeException('اسم الوحدة داخل manifest لا يطابق سجل الإضافة.');
        }
        if (!isset($manifest['version']) || !is_string($manifest['version'])) {
            throw new RuntimeException('إصدار الوحدة مفقود من module.json.');
        }
    }
}
