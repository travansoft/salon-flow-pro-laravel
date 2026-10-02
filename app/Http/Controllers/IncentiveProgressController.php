<?php

namespace App\Http\Controllers;

use App\Http\Requests\Incentive\IncentiveMonthRequest;
use App\Models\StaffProfile;
use App\Services\IncentiveService;
use Illuminate\View\View;

class IncentiveProgressController extends Controller
{
    public function __construct(private IncentiveService $incentiveService) {}

    public function index(IncentiveMonthRequest $request): View
    {
        $user = $request->user();

        abort_unless($user->can('incentives.view'), 403);

        $month = $request->month();
        $progress = $this->incentiveService->progressForAll($month);

        if (! $user->can('incentives.create') && ! $user->can('incentives.edit')) {
            $ownStaffProfile = $user->staffProfile;

            abort_unless($ownStaffProfile !== null, 403);

            $progress = $progress->only([$ownStaffProfile->id]);
        }

        $slabs = $this->incentiveService->getSlabs();

        return view('admin.incentive.progress.index', [
            'progress' => $progress,
            'month' => $month,
            'slabs' => $slabs,
            'scalePercent' => max(100, (float) $slabs->max('min_achievement_percent')),
            'settings' => $this->incentiveService->getSettings(),
            'totalIncentive' => $progress->reduce(fn (string $carry, array $row) => bcadd($carry, $row['incentive'], 2), '0.00'),
            'totalBonus' => $progress->reduce(fn (string $carry, array $row) => bcadd($carry, $row['bonus'], 2), '0.00'),
            'totalAccrued' => $progress->reduce(fn (string $carry, array $row) => bcadd($carry, $row['totalEarned'], 2), '0.00'),
        ]);
    }

    public function show(IncentiveMonthRequest $request, string $subdomain, StaffProfile $staffProfile): View
    {
        $user = $request->user();

        abort_unless($user->can('incentives.view'), 403);

        $canSeeEveryone = $user->can('incentives.create') || $user->can('incentives.edit');

        abort_unless($canSeeEveryone || $user->staffProfile?->id === $staffProfile->id, 403);

        $month = $request->month();

        return view('admin.incentive.progress.show', [
            'staff' => $staffProfile,
            'month' => $month,
            'creditLines' => $this->incentiveService->creditLinesFor($staffProfile, $month),
            'progress' => $this->incentiveService->progressForAll($month)->get($staffProfile->id),
        ]);
    }
}
