<?php

namespace Tests\Unit\SuperAdmin;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\SuperAdmin\TenantService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    public function test_create_encodes_uploaded_logos_as_base64_and_delegates_to_repository(): void
    {
        $repository = Mockery::mock(TenantRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => str_starts_with($data['print_logo'], 'data:image/png;base64,'))
            ->andReturn(new Tenant);

        $service = new TenantService($repository);

        $tenant = $service->create([
            'name' => 'Studio',
            'print_logo' => UploadedFile::fake()->image('logo.png', 5, 5)->size(1),
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
    }

    public function test_update_keeps_existing_logo_when_no_new_file_and_not_removed(): void
    {
        $tenant = new Tenant(['print_logo' => 'data:image/png;base64,existing']);

        $repository = Mockery::mock(TenantRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->withArgs(fn (Tenant $t, array $data) => $data['print_logo'] === 'data:image/png;base64,existing')
            ->andReturn($tenant);

        $service = new TenantService($repository);

        $service->update($tenant, ['name' => 'Studio']);
    }

    public function test_update_clears_logo_when_removal_requested(): void
    {
        $tenant = new Tenant(['print_logo' => 'data:image/png;base64,existing']);

        $repository = Mockery::mock(TenantRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->withArgs(fn (Tenant $t, array $data) => $data['print_logo'] === null)
            ->andReturn($tenant);

        $service = new TenantService($repository);

        $service->update($tenant, ['name' => 'Studio', 'remove_print_logo' => true]);
    }
}
