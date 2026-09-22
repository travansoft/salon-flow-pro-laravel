<?php

namespace Tests\Regression\Billing;

use App\Models\Tenant;
use App\Repositories\Eloquent\BillRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillNumberLockAvoidsAggregateForUpdateFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug: nextBillNumber() used ->max('bill_number')->lockForUpdate(), which
     * compiles to "SELECT MAX(bill_number) ... FOR UPDATE". SQLite silently
     * accepts this, but PostgreSQL (the project's actual database) rejects
     * locking an aggregate query with "FOR UPDATE is not allowed with
     * aggregate functions", making every quick-bill settle and manual bill
     * creation fail with a 500 in production while every test stayed green.
     * Fixed by locking the actual max row via orderByDesc()+lockForUpdate()
     * ->value(), which both engines support.
     */
    public function test_next_bill_number_locks_a_row_not_an_aggregate(): void
    {
        $tenant = Tenant::factory()->create();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        app(BillRepository::class)->nextBillNumber($tenant->id, '2026-27');

        $lockingQueries = array_filter($queries, fn (string $sql) => str_contains(strtolower($sql), 'for update'));

        $this->assertNotEmpty($lockingQueries, 'Expected a locking query to run.');

        foreach ($lockingQueries as $sql) {
            $this->assertStringNotContainsString('max(', strtolower($sql), 'Locking query must not lock an aggregate function.');
        }
    }
}
