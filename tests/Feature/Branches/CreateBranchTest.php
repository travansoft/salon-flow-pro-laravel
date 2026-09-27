<?php

namespace Tests\Feature\Branches;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class CreateBranchTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_branch(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->postToTenant('/branches', [
            'name' => 'Marine Drive',
            'slug' => 'marine-drive',
            'invoice_prefix' => 'mdv',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('branches', [
            'name' => 'Marine Drive',
            'slug' => 'marine-drive',
            'invoice_prefix' => 'MDV',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_invoice_prefix_is_uppercased(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $this->actingAs($owner)->postToTenant('/branches', [
            'name' => 'Marine Drive',
            'slug' => 'marine-drive',
            'invoice_prefix' => 'mdv',
        ]);

        $this->assertDatabaseHas('branches', ['invoice_prefix' => 'MDV']);
    }

    public function test_validation_rejects_duplicate_invoice_prefix_within_tenant(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $this->actingAs($owner)->postToTenant('/branches', [
            'name' => 'Marine Drive',
            'slug' => 'marine-drive',
            'invoice_prefix' => 'MDV',
        ]);

        $response = $this->actingAs($owner)->postToTenant('/branches', [
            'name' => 'Fort Kochi',
            'slug' => 'fort-kochi',
            'invoice_prefix' => 'MDV',
        ]);

        $response->assertSessionHasErrors('invoice_prefix');
    }

    public function test_validation_rejects_missing_name(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->postToTenant('/branches', [
            'slug' => 'marine-drive',
            'invoice_prefix' => 'MDV',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
