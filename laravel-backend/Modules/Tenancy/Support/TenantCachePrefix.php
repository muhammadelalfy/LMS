<?php

namespace Modules\Tenancy\Support;

use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Makes every cache key belong to one school, so one school never reads
 * another's cached figures, locks or counters. Works on any cache store
 * (database, redis, array, file), unlike tag-based tenancy, which needs a
 * store that supports tags.
 *
 * Most stores put the prefix in front of every key. The file store has no
 * prefix, so each school gets its own folder there instead.
 */
class TenantCachePrefix implements TenancyBootstrapper
{
    private ?string $originalPrefix = null;

    /** @var array<string, string> file store name => its original folder */
    private array $originalPaths = [];

    public function bootstrap(Tenant $tenant)
    {
        $key = $tenant->getTenantKey();

        $this->originalPrefix ??= (string) config('cache.prefix');
        config(['cache.prefix' => $this->originalPrefix.'school_'.$key.'_']);

        foreach (config('cache.stores', []) as $name => $store) {
            if (($store['driver'] ?? null) === 'file') {
                $this->originalPaths[$name] ??= (string) $store['path'];
                config(["cache.stores.{$name}.path" => $this->originalPaths[$name].DIRECTORY_SEPARATOR.'school_'.$key]);
            }
        }
        $this->rebuildStores();
    }

    public function revert()
    {
        if ($this->originalPrefix !== null) {
            config(['cache.prefix' => $this->originalPrefix]);
        }
        foreach ($this->originalPaths as $name => $path) {
            config(["cache.stores.{$name}.path" => $path]);
        }
        $this->originalPrefix = null;
        $this->originalPaths = [];
        $this->rebuildStores();
    }

    /** Stores read the prefix and folder when they are created, so forget the ones already made. */
    private function rebuildStores(): void
    {
        app('cache')->forgetDriver();
        Cache::clearResolvedInstances();
    }
}
