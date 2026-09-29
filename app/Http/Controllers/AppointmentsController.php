<?php

namespace App\Http\Controllers;

use App\Exceptions\StaffUnavailableException;
use App\Http\Requests\Appointments\CancelAppointmentRequest;
use App\Http\Requests\Appointments\RescheduleAppointmentRequest;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Services\AppointmentService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;

class AppointmentsController extends Controller
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentService $appointmentService,
        private ClientRepositoryInterface $clientRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private TimeSlotRepositoryInterface $timeSlotRepository,
        private TenantContext $tenantContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('appointments.view'), 403);

        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $appointments = $this->appointmentRepository->getForDate($date);

        return view('admin.appointments.index', ['appointments' => $appointments, 'date' => $date]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        return view('admin.appointments.create', [
            'timeSlots' => $this->timeSlotRepository->getActive(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        $data = $request->validated();

        try {
            $appointment = $this->appointmentService->book(
                $data['client_id'],
                Carbon::parse($data['start_at']),
                $data['services'],
                $data['notes'] ?? null,
            );
        } catch (StaffUnavailableException $exception) {
            return back()->withErrors(['services' => $exception->getMessage()])->withInput();
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['services' => $exception->getMessage()])->withInput();
        }

        return redirect($this->tenantUrl->route('appointments.show', ['appointment' => $appointment]))->with('status', 'Appointment booked.');
    }

    public function searchClients(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['clients' => []]);
        }

        $clients = $this->clientRepository->search($term)->take(8);

        return response()->json([
            'clients' => $clients->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
            ])->values(),
        ]);
    }

    public function searchServices(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        $term = trim((string) $request->query('q', ''));

        $services = $term === ''
            ? $this->serviceRepository->getActive()->take(10)
            : $this->serviceRepository->search($term);

        return response()->json([
            'services' => $services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
            ])->values(),
        ]);
    }

    public function quickCreateClient(StoreClientRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('appointments.create'), 403);

        $client = $this->clientRepository->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->get()->id,
        ]);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
        ], 201);
    }

    public function show(Request $request, string $subdomain, Appointment $appointment): View
    {
        abort_unless($request->user()->can('appointments.view'), 403);

        $appointment->load(['client', 'services', 'staffProfiles']);

        return view('admin.appointments.show', ['appointment' => $appointment]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, string $subdomain, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        $data = $request->validated();

        try {
            $this->appointmentService->reschedule(
                $appointment,
                Carbon::parse($data['start_at']),
                $data['reason'] ?? null,
                $request->user()->id,
            );
        } catch (StaffUnavailableException $exception) {
            return back()->withErrors(['start_at' => $exception->getMessage()])->withInput();
        }

        return redirect($this->tenantUrl->route('appointments.show', ['appointment' => $appointment]))->with('status', 'Appointment rescheduled.');
    }

    public function cancel(CancelAppointmentRequest $request, string $subdomain, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        $this->appointmentService->cancel($appointment, $request->validated()['reason'], $request->user()->id);

        return redirect($this->tenantUrl->route('appointments.index'))->with('status', 'Appointment cancelled.');
    }

    public function noShow(Request $request, string $subdomain, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->can('appointments.edit'), 403);

        $this->appointmentService->markNoShow($appointment, $request->user()->id);

        return redirect($this->tenantUrl->route('appointments.index'))->with('status', 'Marked as no-show.');
    }
}
