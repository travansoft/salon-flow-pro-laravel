<?php

namespace App\Http\Controllers;

use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\BranchService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function __construct(
        private BranchRepositoryInterface $branchRepository,
        private BranchService $branchService,
        private TenantUrl $tenantUrl,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('branches.view'), 403);

        return view('admin.branches.index', [
            'branches' => $this->branchRepository->getActive(),
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('branches.create'), 403);

        return view('admin.branches.create', [
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('branches.create'), 403);

        $branch = $this->branchService->create($request->validated());

        return redirect($this->tenantUrl->route('branches.show', ['branch' => $branch]))->with('status', 'Branch created.');
    }

    public function show(Request $request, string $subdomain, Branch $branch): View
    {
        abort_unless($request->user()->can('branches.view'), 403);

        return view('admin.branches.show', [
            'branch' => $branch,
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function edit(Request $request, string $subdomain, Branch $branch): View
    {
        abort_unless($request->user()->can('branches.edit'), 403);

        return view('admin.branches.edit', [
            'branch' => $branch,
            'tenant' => $this->tenantContext->get(),
        ]);
    }

    public function update(UpdateBranchRequest $request, string $subdomain, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->can('branches.edit'), 403);

        $this->branchService->update($branch, $request->validated());

        return redirect($this->tenantUrl->route('branches.show', ['branch' => $branch]))->with('status', 'Branch updated.');
    }

    public function destroy(Request $request, string $subdomain, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->can('branches.delete'), 403);

        $this->branchService->deactivate($branch);

        return redirect($this->tenantUrl->route('branches.index'))->with('status', 'Branch disabled.');
    }
}
