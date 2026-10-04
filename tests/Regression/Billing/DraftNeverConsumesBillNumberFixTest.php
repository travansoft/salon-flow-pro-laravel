<?php

namespace Tests\Regression\Billing;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class DraftNeverConsumesBillNumberFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    public function test_saving_drafts_creates_no_bills(): void
    {
        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('FrontDesk');

        $this->actingAs($user)->postToTenant('/bill-drafts', [
            'client_name' => 'Asha',
            'lines' => [['description' => 'Haircut', 'priceInclusive' => 500, 'quantity' => 1]],
        ])->assertOk();

        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('bill_drafts', 1);
    }
}
