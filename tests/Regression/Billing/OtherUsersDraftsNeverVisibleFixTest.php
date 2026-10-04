<?php

namespace Tests\Regression\Billing;

use App\Models\BillDraft;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class OtherUsersDraftsNeverVisibleFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    private User $colleague;

    private BillDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);

        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('FrontDesk');
        $this->colleague = User::factory()->for($this->tenant)->create();
        $this->colleague->assignRole('FrontDesk');

        $this->draft = BillDraft::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'client_name' => 'Private Priya',
        ]);
    }

    public function test_colleague_does_not_see_the_draft_on_bills_list_or_dashboard(): void
    {
        $this->actingAs($this->colleague)->getFromTenant('/bills')->assertDontSee('Private Priya');
        $this->actingAs($this->colleague)->getFromTenant('/dashboard')->assertDontSee('Private Priya');
    }

    public function test_colleague_cannot_open_update_or_discard_the_draft(): void
    {
        $this->actingAs($this->colleague)->getFromTenant("/bills/create?draft={$this->draft->id}")->assertNotFound();
        $this->actingAs($this->colleague)->putToTenant("/bill-drafts/{$this->draft->id}", ['client_name' => 'Hijacked'])->assertNotFound();
        $this->actingAs($this->colleague)->deleteFromTenant("/bill-drafts/{$this->draft->id}")->assertNotFound();

        $this->assertDatabaseHas('bill_drafts', ['id' => $this->draft->id, 'client_name' => 'Private Priya']);
    }
}
