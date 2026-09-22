<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantSettings\UpdateTenantSettingsRequest;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class TenantSettingsController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantContext $tenantContext,
    ) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('settings.view'), 403);

        return view('admin.settings.edit', ['tenant' => $this->tenantContext->get()]);
    }

    public function update(UpdateTenantSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.edit'), 403);

        $tenant = $this->tenantContext->get();
        $data = $request->safe()->except(['print_logo', 'ui_logo', 'remove_print_logo', 'remove_ui_logo']);

        $data['print_logo'] = $this->resolveLogo($tenant, $request->file('print_logo'), $request->boolean('remove_print_logo'), 'print_logo');
        $data['ui_logo'] = $this->resolveLogo($tenant, $request->file('ui_logo'), $request->boolean('remove_ui_logo'), 'ui_logo');

        $this->tenantRepository->update($tenant, $data);

        return back()->with('status', 'Settings updated.');
    }

    private function resolveLogo(Tenant $tenant, ?UploadedFile $file, bool $remove, string $attribute): ?string
    {
        if ($remove) {
            return null;
        }

        if (! $file) {
            return $tenant->{$attribute};
        }

        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        return "data:{$file->getMimeType()};base64,{$base64}";
    }
}
