<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillLineItem;
use App\Models\IncentiveSetting;
use App\Models\IncentiveSlab;
use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Repositories\Contracts\BillLineItemRepositoryInterface;
use App\Repositories\Contracts\IncentiveSettingRepositoryInterface;
use App\Repositories\Contracts\IncentiveSlabRepositoryInterface;
use App\Repositories\Contracts\StaffIncentiveRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Repositories\Contracts\StaffTargetRepositoryInterface;
use Database\Seeders\IncentiveDefaultsSeeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncentiveService
{
    public function __construct(
        private IncentiveSettingRepositoryInterface $settingRepository,
        private IncentiveSlabRepositoryInterface $slabRepository,
        private StaffTargetRepositoryInterface $targetRepository,
        private BillLineItemRepositoryInterface $billLineItemRepository,
        private StaffIncentiveRepositoryInterface $staffIncentiveRepository,
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private TenantContext $tenantContext,
    ) {}

    public function getSettings(): IncentiveSetting
    {
        $setting = $this->settingRepository->findForTenant();

        if ($setting) {
            return $setting;
        }

        return DB::transaction(function (): IncentiveSetting {
            $tenantId = $this->tenantContext->get()->id;

            foreach (IncentiveDefaultsSeeder::DefaultSlabs as $slab) {
                $this->slabRepository->create([
                    'tenant_id' => $tenantId,
                    'min_achievement_percent' => $slab['min'],
                    'incentive_percent' => $slab['incentive'],
                ]);
            }

            return $this->settingRepository->create([
                'tenant_id' => $tenantId,
                'servicing_share_percent' => 70,
                'referring_share_percent' => 30,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateSettings(array $data): IncentiveSetting
    {
        return $this->settingRepository->update($this->getSettings(), $data);
    }

    /** @return EloquentCollection<int, IncentiveSlab> */
    public function getSlabs(): EloquentCollection
    {
        $this->getSettings();

        return $this->slabRepository->getAllAscending();
    }

    /** @param array<string, mixed> $data */
    public function createSlab(array $data): IncentiveSlab
    {
        return $this->slabRepository->create($data);
    }

    /** @param array<string, mixed> $data */
    public function updateSlab(IncentiveSlab $slab, array $data): IncentiveSlab
    {
        return $this->slabRepository->update($slab, $data);
    }

    public function deleteSlab(IncentiveSlab $slab): bool
    {
        return $this->slabRepository->delete($slab);
    }

    /** @return Collection<int, string> */
    public function targetsForMonth(Carbon $month): Collection
    {
        return $this->targetRepository->getForMonth($month)
            ->mapWithKeys(fn ($target) => [$target->staff_profile_id => (string) $target->target_amount]);
    }

    /** @param array<int, ?string> $targets target amount, or null to clear, keyed by staff profile id */
    public function setTargets(Carbon $month, array $targets): void
    {
        $tenantId = $this->tenantContext->get()->id;

        DB::transaction(function () use ($month, $targets, $tenantId): void {
            foreach ($targets as $staffProfileId => $amount) {
                if ($amount === null || $amount === '') {
                    $this->targetRepository->deleteFor($staffProfileId, $month);

                    continue;
                }

                $this->targetRepository->upsert($tenantId, $staffProfileId, $month, (string) $amount);
            }
        });
    }

    /** Copies targets into the destination month, never overwriting a target already set there. */
    public function copyTargets(Carbon $fromMonth, Carbon $toMonth): int
    {
        $existing = $this->targetsForMonth($toMonth);
        $tenantId = $this->tenantContext->get()->id;
        $copied = 0;

        foreach ($this->targetsForMonth($fromMonth) as $staffProfileId => $amount) {
            if ($existing->has($staffProfileId)) {
                continue;
            }

            $this->targetRepository->upsert($tenantId, $staffProfileId, $toMonth, $amount);
            $copied++;
        }

        return $copied;
    }

    /**
     * Splits the GST-inclusive, post-discount value of a line between the
     * servicing staff and the referrer. The servicing share is the remainder
     * so the two credits always add up to the line value exactly.
     *
     * @return array{
     *     basis: string,
     *     servicingAmount: string,
     *     servicingPercent: string,
     *     referrerAmount: ?string,
     *     referringPercent: ?string,
     * }
     */
    public function splitLine(BillLineItem $lineItem, IncentiveSetting $setting): array
    {
        $basis = $lineItem->totalWithTax();
        $hasDistinctReferrer = $lineItem->referred_by_staff_profile_id !== null
            && $lineItem->referred_by_staff_profile_id !== $lineItem->staff_profile_id;

        if (! $hasDistinctReferrer) {
            return [
                'basis' => $basis,
                'servicingAmount' => $basis,
                'servicingPercent' => '100.00',
                'referrerAmount' => null,
                'referringPercent' => null,
            ];
        }

        $referringPercent = (string) $setting->referring_share_percent;
        $referrerAmount = bcmul($basis, bcdiv($referringPercent, '100', 4), 2);

        return [
            'basis' => $basis,
            'servicingAmount' => bcsub($basis, $referrerAmount, 2),
            'servicingPercent' => (string) $setting->servicing_share_percent,
            'referrerAmount' => $referrerAmount,
            'referringPercent' => $referringPercent,
        ];
    }

    /**
     * @return Collection<int, array{
     *     lineItem: BillLineItem,
     *     basis: string,
     *     servicingAmount: string,
     *     servicingPercent: string,
     *     referrerAmount: ?string,
     *     referringPercent: ?string,
     * }>
     */
    public function splitForBill(Bill $bill): Collection
    {
        $setting = $this->getSettings();

        return $bill->lineItems->map(fn (BillLineItem $lineItem) => [
            'lineItem' => $lineItem,
            ...$this->splitLine($lineItem, $setting),
        ]);
    }

    /**
     * Progress of every active staff member for the month, keyed by staff profile id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function progressForAll(Carbon $month): Collection
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $setting = $this->getSettings();
        $slabs = $this->slabRepository->getAllAscending();
        $targets = $this->targetsForMonth($month);
        $credits = $this->creditsByStaff($this->billLineItemRepository->getPaidBetween($from, $to), $setting);
        $bonuses = $this->staffIncentiveRepository->getBetweenDates($from, $to)
            ->groupBy('staff_profile_id')
            ->map(fn (Collection $group) => $group->reduce(
                fn (string $carry, StaffIncentive $bonus) => bcadd($carry, (string) $bonus->amount, 2),
                '0.00'
            ));

        return $this->staffProfileRepository->getActive()->mapWithKeys(function (StaffProfile $staff) use ($targets, $credits, $bonuses, $slabs): array {
            $credit = $credits->get($staff->id, ['servicing' => '0.00', 'referral' => '0.00', 'count' => 0]);

            return [$staff->id => $this->buildProgress(
                $staff,
                $targets->get($staff->id),
                $credit,
                (string) $bonuses->get($staff->id, '0.00'),
                $slabs,
            )];
        });
    }

    /**
     * @param  EloquentCollection<int, BillLineItem>  $lineItems
     * @return Collection<int, array{servicing: string, referral: string, count: int}>
     */
    private function creditsByStaff(EloquentCollection $lineItems, IncentiveSetting $setting): Collection
    {
        $blank = ['servicing' => '0.00', 'referral' => '0.00', 'count' => 0];
        $credits = collect();

        foreach ($lineItems as $lineItem) {
            if ($lineItem->staff_profile_id === null) {
                continue;
            }

            $split = $this->splitLine($lineItem, $setting);

            $servicer = $credits->get($lineItem->staff_profile_id, $blank);
            $servicer['servicing'] = bcadd($servicer['servicing'], $split['servicingAmount'], 2);
            $servicer['count']++;
            $credits->put($lineItem->staff_profile_id, $servicer);

            if ($split['referrerAmount'] === null) {
                continue;
            }

            $referrer = $credits->get($lineItem->referred_by_staff_profile_id, $blank);
            $referrer['referral'] = bcadd($referrer['referral'], $split['referrerAmount'], 2);
            $credits->put($lineItem->referred_by_staff_profile_id, $referrer);
        }

        return $credits;
    }

    /**
     * @param  array{servicing: string, referral: string, count: int}  $credit
     * @param  EloquentCollection<int, IncentiveSlab>  $slabs
     * @return array<string, mixed>
     */
    private function buildProgress(StaffProfile $staff, ?string $target, array $credit, string $bonus, EloquentCollection $slabs): array
    {
        $achieved = bcadd($credit['servicing'], $credit['referral'], 2);
        $hasTarget = $target !== null && bccomp($target, '0', 2) > 0;
        $achievementPercent = $hasTarget ? bcdiv(bcmul($achieved, '100', 4), $target, 2) : null;

        $slab = null;
        $nextSlab = null;
        $amountToNextSlab = null;
        $incentive = '0.00';

        if ($hasTarget) {
            $slab = $slabs->last(fn (IncentiveSlab $candidate) => bccomp((string) $candidate->min_achievement_percent, $achievementPercent, 2) <= 0);
            $nextSlab = $slabs->first(fn (IncentiveSlab $candidate) => bccomp((string) $candidate->min_achievement_percent, $achievementPercent, 2) > 0);

            if ($slab) {
                $incentive = bcmul($achieved, bcdiv((string) $slab->incentive_percent, '100', 4), 2);
            }

            if ($nextSlab) {
                $amountToNextSlab = bcsub(bcdiv(bcmul($target, (string) $nextSlab->min_achievement_percent, 4), '100', 2), $achieved, 2);
            }
        }

        return [
            'staff' => $staff,
            'target' => $target,
            'servicingCredit' => $credit['servicing'],
            'referralCredit' => $credit['referral'],
            'achieved' => $achieved,
            'achievementPercent' => $achievementPercent,
            'slab' => $slab,
            'incentive' => $incentive,
            'nextSlab' => $nextSlab,
            'amountToNextSlab' => $amountToNextSlab,
            'bonus' => $bonus,
            'totalEarned' => bcadd($incentive, $bonus, 2),
            'lineItemCount' => $credit['count'],
        ];
    }
}
