<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientsController extends Controller
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private TenantContext $tenantContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('clients.view'), 403);

        $search = $request->query('search');
        $clients = $search ? $this->clientRepository->search($search) : $this->clientRepository->getAll();

        return view('admin.clients.index', ['clients' => $clients, 'search' => $search]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('clients.create'), 403);

        return view('admin.clients.create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('clients.create'), 403);

        $client = $this->clientRepository->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->get()->id,
        ]);

        return redirect($this->tenantUrl->route('clients.show', ['client' => $client]))->with('status', 'Client created.');
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('billing.create'), 403);

        $term = (string) $request->query('q', '');

        if ($term === '') {
            return response()->json(['clients' => []]);
        }

        $clients = $this->clientRepository->search($term)->take(10);

        return response()->json([
            'clients' => $clients->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'gst_number' => $client->gst_number,
            ])->values(),
        ]);
    }

    public function show(Request $request, string $subdomain, Client $client): View
    {
        abort_unless($request->user()->can('clients.view'), 403);

        $client->load(['appointments' => fn ($query) => $query->latest('start_at')->with('services')]);

        return view('admin.clients.show', ['client' => $client]);
    }

    public function edit(Request $request, string $subdomain, Client $client): View
    {
        abort_unless($request->user()->can('clients.edit'), 403);

        return view('admin.clients.edit', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, string $subdomain, Client $client): RedirectResponse
    {
        abort_unless($request->user()->can('clients.edit'), 403);

        $this->clientRepository->update($client, $request->validated());

        return redirect($this->tenantUrl->route('clients.show', ['client' => $client]))->with('status', 'Client updated.');
    }
}
