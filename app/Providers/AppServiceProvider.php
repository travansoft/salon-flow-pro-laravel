<?php

namespace App\Providers;

use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\BridalEngagementRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\CommissionRateRepositoryInterface;
use App\Repositories\Contracts\DesignationRepositoryInterface;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Repositories\Contracts\InventoryCategoryRepositoryInterface;
use App\Repositories\Contracts\MainDomainRepositoryInterface;
use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ServiceCategoryRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\StaffIncentiveRepositoryInterface;
use App\Repositories\Contracts\StaffLeaveRequestRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Repositories\Contracts\SuperAdminActivityLogRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Repositories\Eloquent\BillRepository;
use App\Repositories\Eloquent\BranchRepository;
use App\Repositories\Eloquent\BridalEngagementRepository;
use App\Repositories\Eloquent\ClientRepository;
use App\Repositories\Eloquent\CommissionRateRepository;
use App\Repositories\Eloquent\DesignationRepository;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Repositories\Eloquent\InventoryCategoryRepository;
use App\Repositories\Eloquent\MainDomainRepository;
use App\Repositories\Eloquent\PlatformAdminRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\ServiceCategoryRepository;
use App\Repositories\Eloquent\ServiceRepository;
use App\Repositories\Eloquent\StaffIncentiveRepository;
use App\Repositories\Eloquent\StaffLeaveRequestRepository;
use App\Repositories\Eloquent\StaffProfileRepository;
use App\Repositories\Eloquent\SuperAdminActivityLogRepository;
use App\Repositories\Eloquent\TenantRepository;
use App\Repositories\Eloquent\TenantUserRepository;
use App\Repositories\Eloquent\TimeSlotRepository;
use App\Services\BranchContext;
use App\Services\Contracts\ReminderChannelInterface;
use App\Services\LogReminderChannel;
use App\Services\SuperAdminUrl;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(BranchContext::class);
        $this->app->singleton(TenantUrl::class);
        $this->app->singleton(SuperAdminUrl::class);

        $this->app->bind(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(MainDomainRepositoryInterface::class, MainDomainRepository::class);
        $this->app->bind(StaffProfileRepositoryInterface::class, StaffProfileRepository::class);
        $this->app->bind(DesignationRepositoryInterface::class, DesignationRepository::class);
        $this->app->bind(StaffLeaveRequestRepositoryInterface::class, StaffLeaveRequestRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(ServiceCategoryRepositoryInterface::class, ServiceCategoryRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class, AppointmentRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(BridalEngagementRepositoryInterface::class, BridalEngagementRepository::class);
        $this->app->bind(BillRepositoryInterface::class, BillRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(InventoryCategoryRepositoryInterface::class, InventoryCategoryRepository::class);
        $this->app->bind(ExpenseRepositoryInterface::class, ExpenseRepository::class);
        $this->app->bind(ExpenseCategoryRepositoryInterface::class, ExpenseCategoryRepository::class);
        $this->app->bind(CommissionRateRepositoryInterface::class, CommissionRateRepository::class);
        $this->app->bind(StaffIncentiveRepositoryInterface::class, StaffIncentiveRepository::class);
        $this->app->bind(TimeSlotRepositoryInterface::class, TimeSlotRepository::class);
        $this->app->bind(ReminderChannelInterface::class, LogReminderChannel::class);
        $this->app->bind(TenantUserRepositoryInterface::class, TenantUserRepository::class);
        $this->app->bind(PlatformAdminRepositoryInterface::class, PlatformAdminRepository::class);
        $this->app->bind(SuperAdminActivityLogRepositoryInterface::class, SuperAdminActivityLogRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.admin', function ($view): void {
            $view->with('tenant', $this->app->make(TenantContext::class)->get());
            $view->with('currentBranch', $this->app->make(BranchContext::class)->get());
        });

        View::composer('*', function ($view): void {
            $view->with('tenantUrl', $this->app->make(TenantUrl::class));
            $view->with('superAdminUrl', $this->app->make(SuperAdminUrl::class));
        });
    }
}
