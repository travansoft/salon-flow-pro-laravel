<?php

namespace Tests\Concerns;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;

trait CreatesInclusiveBills
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function billFor(Tenant $tenant, array $lines, float $discountPercent = 0, ?Carbon $billDate = null): Bill
    {
        app(TenantContext::class)->set($tenant);
        $branch = Branch::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()
            ?? Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->for($tenant)->create();

        return app(BillingService::class)->createManualBill($client->id, $user->id, $lines, $discountPercent, $billDate);
    }
}
