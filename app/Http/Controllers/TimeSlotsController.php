<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointments\StoreTimeSlotRequest;
use App\Http\Requests\Appointments\UpdateTimeSlotRequest;
use App\Models\TimeSlot;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimeSlotsController extends Controller
{
    public function __construct(
        private TimeSlotRepositoryInterface $timeSlotRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('appointments.view'), 403);

        $timeSlots = $this->timeSlotRepository->getAll();

        return view('admin.time-slots.index', ['timeSlots' => $timeSlots]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        return view('admin.time-slots.create');
    }

    public function store(StoreTimeSlotRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        $data = $request->validated();

        $this->timeSlotRepository->create([
            'tenant_id' => $this->tenantContext->get()->id,
            'branch_id' => $this->branchContext->get()->id,
            'start_time' => $data['start_time'].':00',
            'end_time' => $data['end_time'].':00',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect($this->tenantUrl->route('timeSlots.index'))->with('status', 'Time slot created.');
    }

    public function edit(Request $request, string $subdomain, TimeSlot $timeSlot): View
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        return view('admin.time-slots.edit', ['timeSlot' => $timeSlot]);
    }

    public function update(UpdateTimeSlotRequest $request, string $subdomain, TimeSlot $timeSlot): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        $data = $request->validated();

        if (isset($data['start_time'])) {
            $data['start_time'] .= ':00';
        }

        if (isset($data['end_time'])) {
            $data['end_time'] .= ':00';
        }

        $this->timeSlotRepository->update($timeSlot, $data);

        return redirect($this->tenantUrl->route('timeSlots.index'))->with('status', 'Time slot updated.');
    }

    public function destroy(Request $request, string $subdomain, TimeSlot $timeSlot): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.delete'), 403);

        $this->timeSlotRepository->delete($timeSlot);

        return redirect($this->tenantUrl->route('timeSlots.index'))->with('status', 'Time slot removed.');
    }
}
