<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreServiceProductRequest;
use App\Http\Requests\Inventory\UpdateServiceProductRequest;
use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Services\ServiceProductService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceProductsController extends Controller
{
    public function __construct(
        private ServiceProductService $serviceProductService,
        private TenantUrl $tenantUrl,
    ) {}

    public function store(StoreServiceProductRequest $request, string $subdomain, Service $service): RedirectResponse
    {
        abort_unless($request->user()->can('services.edit'), 403);

        $this->serviceProductService->addProduct(
            $service,
            (int) $request->validated('product_id'),
            (float) $request->validated('quantity_used'),
        );

        return redirect($this->tenantUrl->route('services.show', ['service' => $service]).'#service-products')->with('status', 'Product added to service.');
    }

    public function update(UpdateServiceProductRequest $request, string $subdomain, Service $service, ServiceProductUsage $usage): RedirectResponse
    {
        abort_unless($request->user()->can('services.edit'), 403);
        abort_unless($usage->service_id === $service->id, 404);

        $this->serviceProductService->updateQuantity($usage, (float) $request->validated('quantity_used'));

        return redirect($this->tenantUrl->route('services.show', ['service' => $service]).'#service-products')->with('status', 'Quantity updated.');
    }

    public function destroy(Request $request, string $subdomain, Service $service, ServiceProductUsage $usage): RedirectResponse
    {
        abort_unless($request->user()->can('services.edit'), 403);
        abort_unless($usage->service_id === $service->id, 404);

        $this->serviceProductService->removeProduct($usage);

        return redirect($this->tenantUrl->route('services.show', ['service' => $service]).'#service-products')->with('status', 'Product removed from service.');
    }
}
