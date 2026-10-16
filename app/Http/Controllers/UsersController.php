<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\SaveUserRequest;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\SuperAdmin\TenantUserService;
use App\Services\TenantContext;
use App\Services\TenantLimits;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct(
        private TenantUserRepositoryInterface $tenantUserRepository,
        private TenantUserService $tenantUserService,
        private RoleRepositoryInterface $roleRepository,
        private BranchRepositoryInterface $branchRepository,
        private TenantLimits $tenantLimits,
        private TenantContext $tenantContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('users.view'), 403);

        $tenant = $this->tenantContext->get();

        return view('admin.users.index', [
            'users' => $this->tenantUserRepository->getByTenant($tenant),
            'activeUserCount' => $this->tenantLimits->activeUserCount($tenant),
            'userLimit' => $tenant->userLimit(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('users.create'), 403);

        return view('admin.users.create', $this->formData($request));
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('users.create'), 403);

        $this->tenantUserService->create($this->tenantContext->get(), $request->validated());

        return redirect($this->tenantUrl->route('users.index'))->with('status', 'User created.');
    }

    public function edit(Request $request, string $subdomain, User $managedUser): View
    {
        abort_unless($request->user()->can('users.edit'), 403);

        return view('admin.users.edit', $this->formData($request, $managedUser) + [
            'managedUser' => $managedUser,
        ]);
    }

    public function update(SaveUserRequest $request, string $subdomain, User $managedUser): RedirectResponse
    {
        abort_unless($request->user()->can('users.edit'), 403);

        $data = $request->validated();

        if ($managedUser->is($request->user())) {
            unset($data['roles']);
        }

        $this->tenantUserService->update($managedUser, $data);

        return redirect($this->tenantUrl->route('users.index'))->with('status', 'User updated.');
    }

    public function toggleLogin(Request $request, string $subdomain, User $managedUser): RedirectResponse
    {
        abort_unless($request->user()->can('users.edit'), 403);

        if ($managedUser->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot disable your own login.']);
        }

        if ($managedUser->isLoginEnabled()) {
            $this->tenantUserService->disableLogin($managedUser);

            return redirect($this->tenantUrl->route('users.index'))->with('status', 'Login disabled.');
        }

        $this->tenantUserService->enableLogin($managedUser);

        return redirect($this->tenantUrl->route('users.index'))->with('status', 'Login enabled.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ?User $managedUser = null): array
    {
        $currentRoles = $managedUser?->getRoleNames()->all() ?? [];
        $currentBranchIds = $managedUser?->branches()->pluck('branches.id')->all() ?? [];

        return [
            'roles' => $this->roleRepository->getAssignableByTenant($this->tenantContext->get()->id),
            'branches' => $this->branchRepository->getActive(),
            'selectedRoles' => $request->old('roles', $currentRoles),
            'selectedBranchIds' => array_map('intval', $request->old('branch_ids', $currentBranchIds)),
            'isSelf' => $managedUser?->is($request->user()) ?? false,
        ];
    }
}
