<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\InstalledModule;
use App\Models\Student;
use App\Models\PluginProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use RuntimeException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PluginStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('Modules/AttendanceInsights'));
        parent::tearDown();
    }

    public function test_admin_can_purchase_and_install_a_valid_module_zip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plugin = PluginProduct::create([
            'slug' => 'attendance-insights',
            'name' => 'تحليلات الحضور',
            'description' => 'وحدة تجريبية',
            'version' => '1.0.0',
            'module_name' => 'AttendanceInsights',
            'artifact_path' => 'plugins/artifacts/attendance-insights.zip',
            'artifact_sha256' => 'c91600cd5ec365daee974159f041af14b38d734bdd9430b60542e52ae485d21e',
            'price' => 0,
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/plugins')->assertOk()->assertJsonPath('data.0.purchased', false);
        $this->postJson("/api/plugins/{$plugin->id}/purchase")->assertCreated();
        $this->postJson("/api/plugins/{$plugin->id}/install")->assertOk()->assertJsonPath('module.module_name', 'AttendanceInsights');

        $this->assertFileExists(base_path('Modules/AttendanceInsights/module.json'));
        $this->assertDatabaseHas('installed_modules', ['module_name' => 'AttendanceInsights', 'status' => 'installed']);

        $student = Student::factory()->create();
        AttendanceRecord::factory()->create(['student_id' => $student->id, 'status' => 'present', 'date_at' => now(), 'recorded_by' => $admin->id]);
        $report = app(\Modules\AttendanceInsights\Services\AttendanceInsightsReport::class)->summarize(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(1, $report['totals']['present']);
    }

    public function test_failed_replacement_restores_the_previous_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plugin = PluginProduct::create([
            'slug' => 'attendance-insights', 'name' => 'تحليلات الحضور', 'version' => '1.0.0',
            'module_name' => 'AttendanceInsights', 'artifact_path' => 'plugins/artifacts/attendance-insights.zip',
            'artifact_sha256' => 'c91600cd5ec365daee974159f041af14b38d734bdd9430b60542e52ae485d21e',
            'price' => 0, 'is_active' => true,
        ]);
        File::ensureDirectoryExists(base_path('Modules/AttendanceInsights'));
        File::put(base_path('Modules/AttendanceInsights/previous.txt'), 'previous-version');
        Module::shouldReceive('find')->once()->andThrow(new RuntimeException('activation failed'));

        try {
            app(\App\Services\PluginInstaller::class)->install($plugin, $admin->id);
            $this->fail('The forced activation failure should abort installation.');
        } catch (RuntimeException $error) {
            $this->assertSame('activation failed', $error->getMessage());
        }

        $this->assertFileExists(base_path('Modules/AttendanceInsights/previous.txt'));
        $this->assertSame('previous-version', File::get(base_path('Modules/AttendanceInsights/previous.txt')));
        $this->assertDatabaseMissing('installed_modules', ['module_name' => 'AttendanceInsights']);
    }

    public function test_non_admin_cannot_install_a_plugin(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $plugin = PluginProduct::create([
            'slug' => 'blocked-plugin', 'name' => 'إضافة محمية', 'version' => '1.0.0',
            'module_name' => 'BlockedPlugin', 'artifact_path' => 'plugins/artifacts/attendance-insights.zip',
            'price' => 0, 'is_active' => true,
        ]);
        Sanctum::actingAs($teacher);
        $this->postJson("/api/plugins/{$plugin->id}/install")->assertForbidden();
    }

    public function test_unsafe_artifact_paths_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plugin = PluginProduct::create([
            'slug' => 'unsafe-plugin', 'name' => 'إضافة غير آمنة', 'version' => '1.0.0',
            'module_name' => 'UnsafePlugin', 'artifact_path' => '../unsafe.zip',
            'price' => 0, 'is_active' => true,
        ]);
        $admin->pluginPurchases()->create(['plugin_product_id' => $plugin->id, 'status' => 'completed', 'purchased_at' => now()]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/plugins/{$plugin->id}/install")->assertStatus(422);
        $this->assertDatabaseMissing('installed_modules', ['module_name' => 'UnsafePlugin']);
    }
}
