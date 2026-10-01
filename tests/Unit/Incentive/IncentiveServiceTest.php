<?php

namespace Tests\Unit\Incentive;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\IncentiveSetting;
use App\Models\IncentiveSlab;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Models\StaffTarget;
use App\Models\Tenant;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use App\Repositories\Contracts\IncentiveSettingRepositoryInterface;
use App\Repositories\Contracts\IncentiveSlabRepositoryInterface;
use App\Repositories\Contracts\StaffIncentiveRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Repositories\Contracts\StaffTargetRepositoryInterface;
use App\Services\IncentiveService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IncentiveServiceTest extends TestCase
{
    private IncentiveSettingRepositoryInterface&MockInterface $settingRepository;

    private IncentiveSlabRepositoryInterface&MockInterface $slabRepository;

    private StaffTargetRepositoryInterface&MockInterface $targetRepository;

    private BillLineItemRepositoryInterface&MockInterface $billLineItemRepository;

    private StaffIncentiveRepositoryInterface&MockInterface $staffIncentiveRepository;

    private StaffProfileRepositoryInterface&MockInterface $staffProfileRepository;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingRepository = Mockery::mock(IncentiveSettingRepositoryInterface::class);
        $this->slabRepository = Mockery::mock(IncentiveSlabRepositoryInterface::class);
        $this->targetRepository = Mockery::mock(StaffTargetRepositoryInterface::class);
        $this->billLineItemRepository = Mockery::mock(BillLineItemRepositoryInterface::class);
        $this->staffIncentiveRepository = Mockery::mock(StaffIncentiveRepositoryInterface::class);
        $this->staffProfileRepository = Mockery::mock(StaffProfileRepositoryInterface::class);

        $this->tenantContext = new TenantContext;
        $tenant = new Tenant;
        $tenant->id = 7;
        $this->tenantContext->set($tenant);
    }

    private function service(): IncentiveService
    {
        return new IncentiveService(
            $this->settingRepository,
            $this->slabRepository,
            $this->targetRepository,
            $this->billLineItemRepository,
            $this->staffIncentiveRepository,
            $this->staffProfileRepository,
            $this->tenantContext,
        );
    }

    private function settings(int $servicing = 70, int $referring = 30): IncentiveSetting
    {
        return new IncentiveSetting([
            'servicing_share_percent' => $servicing,
            'referring_share_percent' => $referring,
        ]);
    }

    /** @return Collection<int, IncentiveSlab> */
    private function defaultSlabs(): Collection
    {
        return new Collection([
            new IncentiveSlab(['min_achievement_percent' => 80, 'incentive_percent' => 3]),
            new IncentiveSlab(['min_achievement_percent' => 90, 'incentive_percent' => 4]),
            new IncentiveSlab(['min_achievement_percent' => 100, 'incentive_percent' => 5]),
        ]);
    }

    private function lineItem(string $lineTotal, ?int $servicingId, ?int $referrerId = null, string $discount = '0', string $gst = '0'): BillLineItem
    {
        $lineItem = new BillLineItem([
            'line_total' => $lineTotal,
            'discount_amount' => $discount,
            'cgst_amount' => bcdiv($gst, '2', 2),
            'sgst_amount' => bcdiv($gst, '2', 2),
            'igst_amount' => 0,
            'staff_profile_id' => $servicingId,
            'referred_by_staff_profile_id' => $referrerId,
        ]);

        return $lineItem;
    }

    private function staff(int $id, string $name): StaffProfile
    {
        $staff = new StaffProfile(['name' => $name]);
        $staff->id = $id;

        return $staff;
    }

    public function test_direct_line_credits_the_full_gst_inclusive_value_to_the_servicing_staff(): void
    {
        $lineItem = $this->lineItem('1000', 1, null, '100', '162');

        $split = $this->service()->splitLine($lineItem, $this->settings());

        $this->assertSame('1062.00', $split['basis']);
        $this->assertSame('1062.00', $split['servicingAmount']);
        $this->assertNull($split['referrerAmount']);
    }

    public function test_referred_line_is_split_seventy_thirty_by_default(): void
    {
        $lineItem = $this->lineItem('1000', 1, 2, '0', '180');

        $split = $this->service()->splitLine($lineItem, $this->settings());

        $this->assertSame('354.00', $split['referrerAmount']);
        $this->assertSame('826.00', $split['servicingAmount']);
        $this->assertSame('30.00', $split['referringPercent']);
    }

    public function test_referred_line_uses_the_configured_percentages(): void
    {
        $lineItem = $this->lineItem('1000', 1, 2);

        $split = $this->service()->splitLine($lineItem, $this->settings(60, 40));

        $this->assertSame('400.00', $split['referrerAmount']);
        $this->assertSame('600.00', $split['servicingAmount']);
    }

    public function test_servicing_share_takes_the_rounding_remainder_so_the_split_sums_to_the_line_value(): void
    {
        $lineItem = $this->lineItem('100.01', 1, 2);

        $split = $this->service()->splitLine($lineItem, $this->settings());

        $this->assertSame('30.00', $split['referrerAmount']);
        $this->assertSame('70.01', $split['servicingAmount']);
        $this->assertSame($split['basis'], bcadd($split['servicingAmount'], $split['referrerAmount'], 2));
    }

    public function test_referrer_who_is_also_the_servicing_staff_is_not_paid_twice(): void
    {
        $lineItem = $this->lineItem('1000', 1, 1);

        $split = $this->service()->splitLine($lineItem, $this->settings());

        $this->assertSame('1000.00', $split['servicingAmount']);
        $this->assertNull($split['referrerAmount']);
    }

    public function test_refund_scales_the_split_by_the_share_of_the_bill_kept(): void
    {
        $bill = new Bill(['total' => '1000', 'amount_refunded' => '250']);
        $lineItem = $this->lineItem('1000', 1, 2);

        $split = $this->service()->splitLine($lineItem, $this->settings(), $bill);

        $this->assertSame('750.00', $split['basis']);
        $this->assertSame('225.00', $split['referrerAmount']);
        $this->assertSame('525.00', $split['servicingAmount']);
    }

    public function test_full_refund_leaves_no_credit(): void
    {
        $bill = new Bill(['total' => '1000', 'amount_refunded' => '1000']);

        $split = $this->service()->splitLine($this->lineItem('1000', 1, 2), $this->settings(), $bill);

        $this->assertSame('0.00', $split['basis']);
        $this->assertSame('0.00', $split['servicingAmount']);
    }

    /** @return array<string, array{0: string, 1: ?string, 2: string}> */
    public static function slabBoundaries(): array
    {
        return [
            'just below first slab' => ['79999', '79.99', '0.00'],
            'exactly first slab' => ['80000', '80.00', '2400.00'],
            'between first and second' => ['85000', '85.00', '2550.00'],
            'exactly second slab' => ['90000', '90.00', '3600.00'],
            'exactly target' => ['100000', '100.00', '5000.00'],
            'above target keeps top slab' => ['120000', '120.00', '6000.00'],
        ];
    }

    #[DataProvider('slabBoundaries')]
    public function test_incentive_is_the_slab_percent_of_the_total_achieved(string $achieved, string $expectedPercent, string $expectedIncentive): void
    {
        $this->arrangeProgress(target: '100000', lineItems: [$this->lineItem($achieved, 1)]);

        $row = $this->service()->progressForAll(Carbon::parse('2026-06-15'))->get(1);

        $this->assertSame($expectedPercent, $row['achievementPercent']);
        $this->assertSame($expectedIncentive, $row['incentive']);
    }

    public function test_staff_without_a_target_earns_credit_but_no_incentive(): void
    {
        $this->arrangeProgress(target: null, lineItems: [$this->lineItem('50000', 1)]);

        $row = $this->service()->progressForAll(Carbon::parse('2026-06-15'))->get(1);

        $this->assertSame('50000.00', $row['achieved']);
        $this->assertNull($row['achievementPercent']);
        $this->assertSame('0.00', $row['incentive']);
    }

    public function test_referral_credit_counts_toward_the_referrers_target(): void
    {
        $this->arrangeProgress(target: '100000', lineItems: [$this->lineItem('10000', 1, 2)], staff: [$this->staff(1, 'Rizwan'), $this->staff(2, 'Azam')]);

        $progress = $this->service()->progressForAll(Carbon::parse('2026-06-15'));

        $this->assertSame('7000.00', $progress->get(1)['servicingCredit']);
        $this->assertSame('3000.00', $progress->get(2)['referralCredit']);
        $this->assertSame('3000.00', $progress->get(2)['achieved']);
    }

    public function test_next_slab_shortfall_is_reported(): void
    {
        $this->arrangeProgress(target: '100000', lineItems: [$this->lineItem('85000', 1)]);

        $row = $this->service()->progressForAll(Carbon::parse('2026-06-15'))->get(1);

        $this->assertSame('5000.00', $row['amountToNextSlab']);
        $this->assertSame('90.00', (string) $row['nextSlab']->min_achievement_percent);
    }

    public function test_bonuses_are_added_to_total_earned(): void
    {
        $this->arrangeProgress(target: '100000', lineItems: [$this->lineItem('100000', 1)], bonus: '500');

        $row = $this->service()->progressForAll(Carbon::parse('2026-06-15'))->get(1);

        $this->assertSame('5500.00', $row['totalEarned']);
    }

    public function test_copy_targets_never_overwrites_an_existing_target(): void
    {
        $from = Carbon::parse('2026-05-01');
        $to = Carbon::parse('2026-06-01');

        $this->targetRepository->shouldReceive('getForMonth')->with(Mockery::on(fn (Carbon $month) => $month->isSameMonth($to)))
            ->andReturn(new Collection([new StaffTarget(['staff_profile_id' => 1, 'target_amount' => 999])]));
        $this->targetRepository->shouldReceive('getForMonth')->with(Mockery::on(fn (Carbon $month) => $month->isSameMonth($from)))
            ->andReturn(new Collection([
                new StaffTarget(['staff_profile_id' => 1, 'target_amount' => 100]),
                new StaffTarget(['staff_profile_id' => 2, 'target_amount' => 200]),
            ]));
        $this->targetRepository->shouldReceive('upsert')->once()->with(7, 2, $to, '200.00');

        $copied = $this->service()->copyTargets($from, $to);

        $this->assertSame(1, $copied);
    }

    public function test_get_settings_creates_defaults_with_the_standard_slabs_when_missing(): void
    {
        $this->settingRepository->shouldReceive('findForTenant')->once()->andReturn(null);
        $this->slabRepository->shouldReceive('create')->times(3);
        $this->settingRepository->shouldReceive('create')->once()
            ->with(['tenant_id' => 7, 'servicing_share_percent' => 70, 'referring_share_percent' => 30])
            ->andReturn($this->settings());

        $settings = $this->service()->getSettings();

        $this->assertSame('70.00', (string) $settings->servicing_share_percent);
    }

    /**
     * @param  array<int, BillLineItem>  $lineItems
     * @param  array<int, StaffProfile>|null  $staff
     */
    private function arrangeProgress(?string $target, array $lineItems, ?array $staff = null, string $bonus = '0'): void
    {
        $this->settingRepository->shouldReceive('findForTenant')->andReturn($this->settings());
        $this->slabRepository->shouldReceive('getAllAscending')->andReturn($this->defaultSlabs());
        $this->targetRepository->shouldReceive('getForMonth')->andReturn(new Collection(
            $target === null ? [] : [new StaffTarget(['staff_profile_id' => 1, 'target_amount' => $target])]
        ));
        $this->billLineItemRepository->shouldReceive('getPaidBetween')->andReturn(new Collection($lineItems));
        $this->staffIncentiveRepository->shouldReceive('getBetweenDates')->andReturn(new Collection(
            bccomp($bonus, '0', 2) === 0 ? [] : [new StaffIncentive(['staff_profile_id' => 1, 'amount' => $bonus])]
        ));
        $this->staffProfileRepository->shouldReceive('getActive')->andReturn(new Collection($staff ?? [$this->staff(1, 'Rizwan')]));
    }
}
