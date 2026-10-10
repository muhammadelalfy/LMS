<?php

declare(strict_types=1);

use Modules\Tenancy\Models\School;
use Modules\Tenancy\Support\TenantCachePrefix;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Features\TenantConfig;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

/*
 * Every school is a tenant with its own database and its own subdomain
 * (https://<school>.<TENANT_BASE_DOMAIN>). The central application (the
 * operator's API, billing data, the list of schools) lives on CENTRAL_DOMAINS.
 *
 * Tenant tables are created by the migrations in each module's
 * Database/Migrations folder; database/migrations holds only the central ones.
 */
return [
    'tenant_model' => School::class,
    // A school's id is its subdomain, chosen by the operator.
    'id_generator' => null,
    'domain_model' => Domain::class,

    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CENTRAL_DOMAINS', 'localhost,127.0.0.1')),
    ))),

    // Development only: the school a plain http://127.0.0.1:8000 serves, so one URL works without subdomains.
    // Ignored outside the local and testing environments.
    'default_school' => env('DEFAULT_SCHOOL'),

    // A new school is reachable at <slug>.<base_domain>.
    'base_domain' => env('TENANT_BASE_DOMAIN', 'zewal.test'),

    // Operator API token (Authorization: Bearer …) for /api/central/*.
    'operator_token' => env('CENTRAL_API_TOKEN'),

    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        // Cache keys carry the school's id, on any cache store (the package's
        // own bootstrapper needs a store that supports tags).
        TenantCachePrefix::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),
        'template_tenant_connection' => null,
        // A school's database is called prefix + id + suffix, e.g. tenantalpha.sqlite.
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant'),
        'suffix' => env('TENANCY_DB_SUFFIX', ''),
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'prefix_base' => 'school',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public'],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [
        // A school's own payment, SMS and WhatsApp settings override the platform's.
        TenantConfig::class,
    ],

    'routes' => false,

    'migration_parameters' => [
        '--force' => true,
        '--path' => glob(base_path('Modules/*/Database/Migrations'), GLOB_ONLYDIR) ?: [],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\ArabicDemoSeeder',
        '--force' => true,
    ],
];
