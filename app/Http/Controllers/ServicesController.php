<?php

namespace App\Http\Controllers;

use App\Http\Requests\Services\StoreServiceRequest;
use App\Http\Requests\Services\UpdateServiceRequest;
use App\Models\Service;
use App\Repositories\Contracts\ServiceCategoryRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\ServiceCatalogService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServicesController extends Controller
{
    public function __construct(
        private ServiceRepositoryInterface $serviceRepository,
        private ServiceCategoryRepositoryInterface $categoryRepository,
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private ServiceCatalogService $serviceCatalogService,
        private TenantUrl $tenantUrl,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('services.view'), 403);

        $services = $this->serviceRepository->getActive();

        return view('admin.services.index', [
            'services' => $services,
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('services.create'), 403);

        $categories = $this->categoryRepository->getActive();

        return view('admin.services.create', [
            'categories' => $categories,
            'staff' => $this->staffProfileRepository->getActive(),
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('services.create'), 403);

        $service = $this->serviceCatalogService->create($request->validated(), $request->user()->id);

        return redirect($this->tenantUrl->route('services.show', ['service' => $service]))->with('status', 'Service created.');
    }

    public function show(Request $request, string $subdomain, Service $service): View
    {
        abort_unless($request->user()->can('services.view'), 403);

        return view('admin.services.show', [
            'service' => $service,
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function edit(Request $request, string $subdomain, Service $service): View
    {
        abort_unless($request->user()->can('services.edit'), 403);

        $categories = $this->categoryRepository->getActive();

        return view('admin.services.edit', [
            'service' => $service,
            'categories' => $categories,
            'staff' => $this->staffProfileRepository->getActive(),
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function update(UpdateServiceRequest $request, string $subdomain, Service $service): RedirectResponse
    {
        abort_unless($request->user()->can('services.edit'), 403);

        $this->serviceCatalogService->update($service, $request->validated(), $request->user()->id);

        return redirect($this->tenantUrl->route('services.show', ['service' => $service]))->with('status', 'Service updated.');
    }

    public function destroy(Request $request, string $subdomain, Service $service): RedirectResponse
    {
        abort_unless($request->user()->can('services.delete'), 403);

        $this->serviceCatalogService->deactivate($service);

        return redirect($this->tenantUrl->route('services.index'))->with('status', 'Service disabled.');
    }

    public function eligibleStaff(Request $request, string $subdomain, Service $service): JsonResponse
    {
        abort_unless($request->user()->canAny(['billing.create', 'appointments.create']), 403);

        $staff = $this->staffProfileRepository->getByService($service->id);

        return response()->json([
            'staff' => $staff->map(fn ($staffMember) => [
                'id' => $staffMember->id,
                'name' => $staffMember->name,
            ])->values(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $term = (string) $request->query('q', '');

        if ($term === '') {
            return response()->json(['services' => []]);
        }

        $services = $this->serviceRepository->search($term);
        $tenantDefaultGstRate = (float) $this->tenantContext->get()->default_gst_rate;

        return response()->json([
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'price' => (float) $service->price,
                'tax_rate' => (float) $service->effectiveTaxRate($tenantDefaultGstRate),
                'price_inclusive' => (float) $service->priceInclusiveOfTax($tenantDefaultGstRate),
            ])->values(),
        ]);
    }
}
