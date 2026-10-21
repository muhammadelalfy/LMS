<?php

namespace Modules\PluginStore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PluginStore\Models\InstalledModule;
use Modules\PluginStore\Models\PluginProduct;

/**
 * Installing or uninstalling a plugin is a per-tenant entitlement flag, not
 * a code deployment: every catalogue module's code already ships with the
 * application and is always enabled (ADR 0001). This service only flips the
 * current school's own `installed_modules` row — it never touches the
 * filesystem or the shared module registry, so one school's action can
 * never affect another school's.
 */
final class PluginInstaller
{
    public function install(PluginProduct $plugin, int $installerId): InstalledModule
    {
        return DB::transaction(function () use ($plugin, $installerId): InstalledModule {
            return InstalledModule::query()->updateOrCreate(
                ['module_name' => $plugin->module_name],
                [
                    'plugin_product_id' => $plugin->id,
                    'installed_by' => $installerId,
                    'version' => $plugin->version,
                    'path' => 'Modules/'.$plugin->module_name,
                    'status' => 'installed',
                    'config' => is_array($plugin->metadata) ? $plugin->metadata : [],
                    'installed_at' => now(),
                    'last_error' => null,
                ],
            );
        });
    }

    public function uninstall(InstalledModule $module): InstalledModule
    {
        DB::transaction(function () use ($module): void {
            $module->update(['status' => 'uninstalled']);
        });

        return $module;
    }
}
