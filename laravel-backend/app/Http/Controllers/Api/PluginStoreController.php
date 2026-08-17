<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstalledModule;
use App\Models\PluginProduct;
use App\Services\PluginInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PluginStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $purchased = $user->pluginPurchases()->where('status', 'completed')->pluck('plugin_product_id');
        $installed = InstalledModule::query()->where('status', 'installed')->pluck('module_name', 'plugin_product_id');

        $plugins = PluginProduct::query()->where('is_active', true)->orderBy('name')->get()->map(function (PluginProduct $plugin) use ($purchased, $installed): array {
            return [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'version' => $plugin->version,
                'module_name' => $plugin->module_name,
                'price' => $plugin->price,
                'purchased' => $purchased->contains($plugin->id),
                'installed' => $installed->has($plugin->id),
                'installed_module' => $installed->get($plugin->id),
                'metadata' => $plugin->metadata,
            ];
        });

        return response()->json(['data' => $plugins]);
    }

    public function installed(): JsonResponse
    {
        return response()->json(['data' => InstalledModule::with('plugin')->latest('installed_at')->get()]);
    }

    public function purchase(Request $request, PluginProduct $plugin): JsonResponse
    {
        abort_unless($plugin->is_active, 404);
        $purchase = $request->user()->pluginPurchases()->firstOrCreate(
            ['plugin_product_id' => $plugin->id],
            ['status' => 'completed', 'purchased_at' => now()],
        );

        return response()->json(['purchase' => $purchase->load('plugin')], $purchase->wasRecentlyCreated ? 201 : 200);
    }

    public function install(Request $request, PluginProduct $plugin, PluginInstaller $installer): JsonResponse
    {
        abort_unless($request->user()->isAnyRole('admin'), 403, 'تثبيت الإضافات متاح لمدير النظام فقط.');
        abort_unless($plugin->is_active, 404);
        abort_unless($request->user()->pluginPurchases()->where('plugin_product_id', $plugin->id)->where('status', 'completed')->exists(), 403, 'يجب شراء الإضافة قبل تثبيتها.');

        try {
            $module = $installer->install($plugin, $request->user()->id);
            return response()->json(['module' => $module->load('plugin'), 'message' => 'تم تثبيت الإضافة وتفعيلها بنجاح.']);
        } catch (Throwable $error) {
            report($error);
            return response()->json(['message' => $error->getMessage()], 422);
        }
    }

    public function uninstall(Request $request, PluginProduct $plugin): JsonResponse
    {
        abort_unless($request->user()->isAnyRole('admin'), 403, 'إدارة الإضافات متاحة لمدير النظام فقط.');
        $module = InstalledModule::query()->where('plugin_product_id', $plugin->id)->firstOrFail();
        $path = base_path($module->path);
        if (is_dir($path)) {
            abort_unless(str_starts_with(realpath($path) ?: '', realpath(base_path('Modules')) . DIRECTORY_SEPARATOR), 422, 'مسار الوحدة غير آمن.');
            \Illuminate\Support\Facades\File::deleteDirectory($path);
        }
        $module->update(['status' => 'uninstalled']);
        return response()->json(['message' => 'تم إلغاء تثبيت الإضافة.']);
    }
}
