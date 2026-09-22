<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class TenantSettingsUpdateTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_save_valid_gst_details(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'legal_name' => 'Mejora Salon Private Limited',
            'address' => '123 MG Road, Kochi',
            'gst_number' => '32AAAAA0000A1Z5',
            'gst_state_code' => '32',
            'default_gst_rate' => 18,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'gst_number' => '32AAAAA0000A1Z5',
            'gst_state_code' => '32',
        ]);
    }

    public function test_invalid_gstin_format_is_rejected(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'gst_number' => 'not-a-valid-gstin',
            'default_gst_rate' => 18,
        ]);

        $response->assertSessionHasErrors('gst_number');
    }

    public function test_owner_can_upload_print_and_ui_logos_as_base64(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'default_gst_rate' => 18,
            'print_logo' => UploadedFile::fake()->create('print-logo.png', 10, 'image/png'),
            'ui_logo' => UploadedFile::fake()->create('ui-logo.png', 10, 'image/png'),
        ]);

        $response->assertRedirect();
        $this->tenant->refresh();
        $this->assertStringStartsWith('data:image/png;base64,', $this->tenant->print_logo);
        $this->assertStringStartsWith('data:image/png;base64,', $this->tenant->ui_logo);
    }

    public function test_owner_can_remove_an_existing_logo(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $this->tenant->update(['print_logo' => 'data:image/png;base64,abc123']);

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'default_gst_rate' => 18,
            'remove_print_logo' => '1',
        ]);

        $response->assertRedirect();
        $this->assertNull($this->tenant->refresh()->print_logo);
    }

    public function test_updating_settings_without_a_new_logo_keeps_the_existing_one(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $this->tenant->update(['print_logo' => 'data:image/png;base64,abc123']);

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'legal_name' => 'Updated Name',
            'default_gst_rate' => 18,
        ]);

        $response->assertRedirect();
        $this->assertSame('data:image/png;base64,abc123', $this->tenant->refresh()->print_logo);
    }

    public function test_owner_can_update_a_single_field_without_sending_the_others(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $this->tenant->update(['default_gst_rate' => 18]);

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'print_logo' => UploadedFile::fake()->create('print-logo.png', 10, 'image/png'),
        ]);

        $response->assertRedirect();
        $this->tenant->refresh();
        $this->assertStringStartsWith('data:image/png;base64,', $this->tenant->print_logo);
        $this->assertEquals(18, $this->tenant->default_gst_rate);
    }

    public function test_oversized_logo_upload_is_rejected(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->putToTenant('/settings', [
            'default_gst_rate' => 18,
            'print_logo' => UploadedFile::fake()->create('too-big.png', 600, 'image/png'),
        ]);

        $response->assertSessionHasErrors('print_logo');
    }
}
