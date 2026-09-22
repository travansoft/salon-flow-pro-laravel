<?php

use App\Http\Controllers\AppointmentsController;
use App\Http\Controllers\BillsController;
use App\Http\Controllers\BridalEngagementsController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\CommissionEarningsController;
use App\Http\Controllers\CommissionRatesController;
use App\Http\Controllers\DesignationsController;
use App\Http\Controllers\ExpenseCategoriesController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\InventoryCategoriesController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ServiceCategoriesController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\StaffIncentivesController;
use App\Http\Controllers\StaffLeaveRequestsController;
use App\Http\Controllers\StaffLoginController;
use App\Http\Controllers\StaffsController;
use App\Http\Controllers\StockAdjustmentsController;
use App\Http\Controllers\StopImpersonationController;
use App\Http\Controllers\SuperAdmin\PlatformAdminsController;
use App\Http\Controllers\SuperAdmin\SuperAdminActivityLogsController;
use App\Http\Controllers\SuperAdmin\SuperAdminProfileController;
use App\Http\Controllers\SuperAdmin\TenantsController;
use App\Http\Controllers\SuperAdmin\TenantUserImpersonationController;
use App\Http\Controllers\SuperAdmin\TenantUsersController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdminLoginController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\TimeSlotsController;
use App\Http\Controllers\WalkInsController;
use Illuminate\Support\Facades\Route;

$mainDomain = config('tenancy.main_domain');

/**
 * Registers the full tenant admin panel's routes. Called twice: once under
 * the {subdomain}.mainDomain domain (bare names, e.g. "services.index"),
 * and once under mainDomain/{slug} (each name suffixed ".bySlug", e.g.
 * "services.index.bySlug") so TenantUrl::route() can resolve either style
 * from a single call site without callers needing to know which one is
 * active for the current request.
 */
$registerTenantRoutes = function (string $nameSuffix = ''): void {
    Route::get('/login', [LoginController::class, 'create'])->name("login{$nameSuffix}");
    Route::post('/login', [LoginController::class, 'store'])->name("login.store{$nameSuffix}");
    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name("logout{$nameSuffix}");

    Route::middleware('auth')->group(function () use ($nameSuffix): void {
        Route::get('/dashboard', [TenantDashboardController::class, 'index'])->name("tenant.dashboard{$nameSuffix}");

        Route::delete('/impersonation', [StopImpersonationController::class, 'destroy'])->name("impersonation.stop{$nameSuffix}");

        Route::middleware('permission:staff.view')->group(function () use ($nameSuffix): void {
            Route::get('/staff', [StaffsController::class, 'index'])->name("staff.index{$nameSuffix}");
            Route::get('/staff/leave-requests', [StaffLeaveRequestsController::class, 'index'])->name("staff.leaveRequests.index{$nameSuffix}");
            Route::get('/staff/designations', [DesignationsController::class, 'index'])->name("designations.index{$nameSuffix}");
        });

        Route::middleware('permission:staff.create')->group(function () use ($nameSuffix): void {
            Route::get('/staff/create', [StaffsController::class, 'create'])->name("staff.create{$nameSuffix}");
            Route::post('/staff', [StaffsController::class, 'store'])->name("staff.store{$nameSuffix}");
            Route::get('/staff/designations/create', [DesignationsController::class, 'create'])->name("designations.create{$nameSuffix}");
            Route::post('/staff/designations', [DesignationsController::class, 'store'])->name("designations.store{$nameSuffix}");
        });

        Route::middleware('permission:staff.edit')->group(function () use ($nameSuffix): void {
            Route::get('/staff/{staff}/edit', [StaffsController::class, 'edit'])->name("staff.edit{$nameSuffix}");
            Route::put('/staff/{staff}', [StaffsController::class, 'update'])->name("staff.update{$nameSuffix}");
            Route::post('/staff/leave-requests', [StaffLeaveRequestsController::class, 'store'])->name("staff.leaveRequests.store{$nameSuffix}");
            Route::put('/staff/leave-requests/{leaveRequest}', [StaffLeaveRequestsController::class, 'update'])->name("staff.leaveRequests.update{$nameSuffix}");
            Route::get('/staff/designations/{designation}/edit', [DesignationsController::class, 'edit'])->name("designations.edit{$nameSuffix}");
            Route::put('/staff/designations/{designation}', [DesignationsController::class, 'update'])->name("designations.update{$nameSuffix}");
            Route::put('/staff/{staff}/login/disable', [StaffLoginController::class, 'disable'])->name("staff.login.disable{$nameSuffix}");
            Route::put('/staff/{staff}/login/enable', [StaffLoginController::class, 'enable'])->name("staff.login.enable{$nameSuffix}");
        });

        Route::middleware('permission:staff.delete')->group(function () use ($nameSuffix): void {
            Route::delete('/staff/{staff}', [StaffsController::class, 'destroy'])->name("staff.destroy{$nameSuffix}");
            Route::delete('/staff/designations/{designation}', [DesignationsController::class, 'destroy'])->name("designations.destroy{$nameSuffix}");
        });

        Route::middleware('permission:staff.view')->group(function () use ($nameSuffix): void {
            Route::get('/staff/{staff}', [StaffsController::class, 'show'])->name("staff.show{$nameSuffix}");
        });

        Route::middleware('permission:services.view')->group(function () use ($nameSuffix): void {
            Route::get('/services', [ServicesController::class, 'index'])->name("services.index{$nameSuffix}");
            Route::get('/services/categories', [ServiceCategoriesController::class, 'index'])->name("serviceCategories.index{$nameSuffix}");
        });

        Route::middleware('permission:services.create')->group(function () use ($nameSuffix): void {
            Route::get('/services/create', [ServicesController::class, 'create'])->name("services.create{$nameSuffix}");
            Route::post('/services', [ServicesController::class, 'store'])->name("services.store{$nameSuffix}");
            Route::get('/services/categories/create', [ServiceCategoriesController::class, 'create'])->name("serviceCategories.create{$nameSuffix}");
            Route::post('/services/categories', [ServiceCategoriesController::class, 'store'])->name("serviceCategories.store{$nameSuffix}");
        });

        Route::middleware('permission:services.edit')->group(function () use ($nameSuffix): void {
            Route::get('/services/{service}/edit', [ServicesController::class, 'edit'])->name("services.edit{$nameSuffix}");
            Route::put('/services/{service}', [ServicesController::class, 'update'])->name("services.update{$nameSuffix}");
            Route::get('/services/categories/{category}/edit', [ServiceCategoriesController::class, 'edit'])->name("serviceCategories.edit{$nameSuffix}");
            Route::put('/services/categories/{category}', [ServiceCategoriesController::class, 'update'])->name("serviceCategories.update{$nameSuffix}");
        });

        Route::middleware('permission:services.delete')->group(function () use ($nameSuffix): void {
            Route::delete('/services/{service}', [ServicesController::class, 'destroy'])->name("services.destroy{$nameSuffix}");
            Route::delete('/services/categories/{category}', [ServiceCategoriesController::class, 'destroy'])->name("serviceCategories.destroy{$nameSuffix}");
        });

        Route::middleware('permission:billing.create')->group(function () use ($nameSuffix): void {
            Route::get('/services/search', [ServicesController::class, 'search'])->name("services.search{$nameSuffix}");
        });

        Route::middleware('permission:services.view')->group(function () use ($nameSuffix): void {
            Route::get('/services/{service}', [ServicesController::class, 'show'])->name("services.show{$nameSuffix}");
        });

        Route::middleware('permission:inventory.view')->group(function () use ($nameSuffix): void {
            Route::get('/products', [ProductsController::class, 'index'])->name("products.index{$nameSuffix}");
            Route::get('/products/categories', [InventoryCategoriesController::class, 'index'])->name("productCategories.index{$nameSuffix}");
        });

        Route::middleware('permission:inventory.create')->group(function () use ($nameSuffix): void {
            Route::get('/products/create', [ProductsController::class, 'create'])->name("products.create{$nameSuffix}");
            Route::post('/products', [ProductsController::class, 'store'])->name("products.store{$nameSuffix}");
            Route::get('/products/categories/create', [InventoryCategoriesController::class, 'create'])->name("productCategories.create{$nameSuffix}");
            Route::post('/products/categories', [InventoryCategoriesController::class, 'store'])->name("productCategories.store{$nameSuffix}");
        });

        Route::middleware('permission:inventory.edit')->group(function () use ($nameSuffix): void {
            Route::get('/products/{product}/edit', [ProductsController::class, 'edit'])->name("products.edit{$nameSuffix}");
            Route::put('/products/{product}', [ProductsController::class, 'update'])->name("products.update{$nameSuffix}");
            Route::post('/products/{product}/stock-adjustments', [StockAdjustmentsController::class, 'store'])->name("products.stockAdjustments.store{$nameSuffix}");
            Route::get('/products/categories/{category}/edit', [InventoryCategoriesController::class, 'edit'])->name("productCategories.edit{$nameSuffix}");
            Route::put('/products/categories/{category}', [InventoryCategoriesController::class, 'update'])->name("productCategories.update{$nameSuffix}");
        });

        Route::middleware('permission:inventory.delete')->group(function () use ($nameSuffix): void {
            Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name("products.destroy{$nameSuffix}");
            Route::delete('/products/categories/{category}', [InventoryCategoriesController::class, 'destroy'])->name("productCategories.destroy{$nameSuffix}");
        });

        Route::middleware('permission:inventory.view')->group(function () use ($nameSuffix): void {
            Route::get('/products/{product}', [ProductsController::class, 'show'])->name("products.show{$nameSuffix}");
        });

        Route::middleware('permission:appointments.view')->group(function () use ($nameSuffix): void {
            Route::get('/appointments', [AppointmentsController::class, 'index'])->name("appointments.index{$nameSuffix}");
            Route::get('/walk-ins', [WalkInsController::class, 'index'])->name("walkIns.index{$nameSuffix}");
            Route::get('/appointments/time-slots', [TimeSlotsController::class, 'index'])->name("timeSlots.index{$nameSuffix}");
        });

        Route::middleware('permission:appointments.create')->group(function () use ($nameSuffix): void {
            Route::get('/appointments/create', [AppointmentsController::class, 'create'])->name("appointments.create{$nameSuffix}");
            Route::post('/appointments', [AppointmentsController::class, 'store'])->name("appointments.store{$nameSuffix}");
            Route::post('/walk-ins', [WalkInsController::class, 'store'])->name("walkIns.store{$nameSuffix}");
            Route::get('/appointments/clients/search', [AppointmentsController::class, 'searchClients'])->name("appointments.searchClients{$nameSuffix}");
            Route::post('/appointments/clients/quick-create', [AppointmentsController::class, 'quickCreateClient'])->name("appointments.quickCreateClient{$nameSuffix}");
            Route::get('/appointments/services/{service}/eligible-staff', [ServicesController::class, 'eligibleStaff'])->name("appointments.services.eligibleStaff{$nameSuffix}");
            Route::get('/appointments/time-slots/create', [TimeSlotsController::class, 'create'])->name("timeSlots.create{$nameSuffix}");
            Route::post('/appointments/time-slots', [TimeSlotsController::class, 'store'])->name("timeSlots.store{$nameSuffix}");
        });

        Route::middleware('permission:appointments.edit')->group(function () use ($nameSuffix): void {
            Route::put('/appointments/{appointment}/reschedule', [AppointmentsController::class, 'reschedule'])->name("appointments.reschedule{$nameSuffix}");
            Route::put('/appointments/{appointment}/cancel', [AppointmentsController::class, 'cancel'])->name("appointments.cancel{$nameSuffix}");
            Route::put('/appointments/{appointment}/no-show', [AppointmentsController::class, 'noShow'])->name("appointments.noShow{$nameSuffix}");
            Route::put('/walk-ins/{walkIn}/assign', [WalkInsController::class, 'assign'])->name("walkIns.assign{$nameSuffix}");
            Route::get('/appointments/time-slots/{timeSlot}/edit', [TimeSlotsController::class, 'edit'])->name("timeSlots.edit{$nameSuffix}");
            Route::put('/appointments/time-slots/{timeSlot}', [TimeSlotsController::class, 'update'])->name("timeSlots.update{$nameSuffix}");
        });

        Route::middleware('permission:appointments.delete')->group(function () use ($nameSuffix): void {
            Route::delete('/appointments/time-slots/{timeSlot}', [TimeSlotsController::class, 'destroy'])->name("timeSlots.destroy{$nameSuffix}");
        });

        Route::middleware('permission:appointments.view')->group(function () use ($nameSuffix): void {
            Route::get('/appointments/{appointment}', [AppointmentsController::class, 'show'])->name("appointments.show{$nameSuffix}");
        });

        Route::middleware('permission:clients.view')->group(function () use ($nameSuffix): void {
            Route::get('/clients', [ClientsController::class, 'index'])->name("clients.index{$nameSuffix}");
        });

        Route::middleware('permission:billing.create')->group(function () use ($nameSuffix): void {
            Route::get('/clients/search', [ClientsController::class, 'search'])->name("clients.search{$nameSuffix}");
        });

        Route::middleware('permission:clients.create')->group(function () use ($nameSuffix): void {
            Route::get('/clients/create', [ClientsController::class, 'create'])->name("clients.create{$nameSuffix}");
            Route::post('/clients', [ClientsController::class, 'store'])->name("clients.store{$nameSuffix}");
        });

        Route::middleware('permission:clients.edit')->group(function () use ($nameSuffix): void {
            Route::get('/clients/{client}/edit', [ClientsController::class, 'edit'])->name("clients.edit{$nameSuffix}");
            Route::put('/clients/{client}', [ClientsController::class, 'update'])->name("clients.update{$nameSuffix}");
        });

        Route::middleware('permission:clients.view')->group(function () use ($nameSuffix): void {
            Route::get('/clients/{client}', [ClientsController::class, 'show'])->name("clients.show{$nameSuffix}");
        });

        Route::middleware('permission:appointments.view')->group(function () use ($nameSuffix): void {
            Route::get('/bridal-engagements', [BridalEngagementsController::class, 'index'])->name("bridalEngagements.index{$nameSuffix}");
        });

        Route::middleware('permission:appointments.create')->group(function () use ($nameSuffix): void {
            Route::get('/bridal-engagements/create', [BridalEngagementsController::class, 'create'])->name("bridalEngagements.create{$nameSuffix}");
            Route::post('/bridal-engagements', [BridalEngagementsController::class, 'store'])->name("bridalEngagements.store{$nameSuffix}");
        });

        Route::middleware('permission:appointments.view')->group(function () use ($nameSuffix): void {
            Route::get('/bridal-engagements/{bridalEngagement}', [BridalEngagementsController::class, 'show'])->name("bridalEngagements.show{$nameSuffix}");
        });

        Route::middleware('permission:billing.view')->group(function () use ($nameSuffix): void {
            Route::get('/bills', [BillsController::class, 'index'])->name("bills.index{$nameSuffix}");
        });

        Route::middleware('permission:billing.create')->group(function () use ($nameSuffix): void {
            Route::get('/bills/create', [BillsController::class, 'create'])->name("bills.create{$nameSuffix}");
            Route::get('/services/{service}/eligible-staff', [ServicesController::class, 'eligibleStaff'])->name("services.eligibleStaff{$nameSuffix}");
            Route::post('/appointments/{appointment}/bill', [BillsController::class, 'generateFromAppointment'])->name("bills.generateFromAppointment{$nameSuffix}");
            Route::post('/bills', [BillsController::class, 'storeManual'])->name("bills.storeManual{$nameSuffix}");
            Route::post('/bills/settle', [BillsController::class, 'settle'])->name("bills.settle{$nameSuffix}");
            Route::put('/bills/{bill}/payments', [BillsController::class, 'recordPayment'])->name("bills.recordPayment{$nameSuffix}");
        });

        Route::middleware('permission:billing.edit')->group(function () use ($nameSuffix): void {
            Route::put('/bills/{bill}/refund', [BillsController::class, 'refund'])->name("bills.refund{$nameSuffix}");
        });

        Route::middleware('permission:billing.view')->group(function () use ($nameSuffix): void {
            Route::get('/bills/{bill}', [BillsController::class, 'show'])->name("bills.show{$nameSuffix}");
            Route::get('/bills/{bill}/print', [BillsController::class, 'print'])->name("bills.print{$nameSuffix}");
        });

        Route::middleware('permission:expenses.view')->group(function () use ($nameSuffix): void {
            Route::get('/expenses', [ExpensesController::class, 'index'])->name("expenses.index{$nameSuffix}");
            Route::get('/expenses/categories', [ExpenseCategoriesController::class, 'index'])->name("expenseCategories.index{$nameSuffix}");
        });

        Route::middleware('permission:expenses.create')->group(function () use ($nameSuffix): void {
            Route::get('/expenses/create', [ExpensesController::class, 'create'])->name("expenses.create{$nameSuffix}");
            Route::post('/expenses', [ExpensesController::class, 'store'])->name("expenses.store{$nameSuffix}");
            Route::get('/expenses/categories/create', [ExpenseCategoriesController::class, 'create'])->name("expenseCategories.create{$nameSuffix}");
            Route::post('/expenses/categories', [ExpenseCategoriesController::class, 'store'])->name("expenseCategories.store{$nameSuffix}");
        });

        Route::middleware('permission:expenses.edit')->group(function () use ($nameSuffix): void {
            Route::get('/expenses/{expense}/edit', [ExpensesController::class, 'edit'])->name("expenses.edit{$nameSuffix}");
            Route::put('/expenses/{expense}', [ExpensesController::class, 'update'])->name("expenses.update{$nameSuffix}");
            Route::get('/expenses/categories/{category}/edit', [ExpenseCategoriesController::class, 'edit'])->name("expenseCategories.edit{$nameSuffix}");
            Route::put('/expenses/categories/{category}', [ExpenseCategoriesController::class, 'update'])->name("expenseCategories.update{$nameSuffix}");
        });

        Route::middleware('permission:expenses.delete')->group(function () use ($nameSuffix): void {
            Route::delete('/expenses/{expense}', [ExpensesController::class, 'destroy'])->name("expenses.destroy{$nameSuffix}");
            Route::delete('/expenses/categories/{category}', [ExpenseCategoriesController::class, 'destroy'])->name("expenseCategories.destroy{$nameSuffix}");
        });

        Route::middleware('permission:expenses.view')->group(function () use ($nameSuffix): void {
            Route::get('/expenses/{expense}', [ExpensesController::class, 'show'])->name("expenses.show{$nameSuffix}");
        });

        Route::middleware('permission:commissions.view')->group(function () use ($nameSuffix): void {
            Route::get('/commission-rates', [CommissionRatesController::class, 'index'])->name("commissionRates.index{$nameSuffix}");
            Route::get('/commission-earnings', [CommissionEarningsController::class, 'index'])->name("commissionEarnings.index{$nameSuffix}");
        });

        Route::middleware('permission:commissions.create')->group(function () use ($nameSuffix): void {
            Route::get('/commission-rates/create', [CommissionRatesController::class, 'create'])->name("commissionRates.create{$nameSuffix}");
            Route::post('/commission-rates', [CommissionRatesController::class, 'store'])->name("commissionRates.store{$nameSuffix}");
            Route::get('/staff-incentives/create', [StaffIncentivesController::class, 'create'])->name("staffIncentives.create{$nameSuffix}");
            Route::post('/staff-incentives', [StaffIncentivesController::class, 'store'])->name("staffIncentives.store{$nameSuffix}");
        });

        Route::middleware('permission:commissions.edit')->group(function () use ($nameSuffix): void {
            Route::get('/commission-rates/{commissionRate}/edit', [CommissionRatesController::class, 'edit'])->name("commissionRates.edit{$nameSuffix}");
            Route::put('/commission-rates/{commissionRate}', [CommissionRatesController::class, 'update'])->name("commissionRates.update{$nameSuffix}");
        });

        Route::middleware('permission:commissions.delete')->group(function () use ($nameSuffix): void {
            Route::delete('/commission-rates/{commissionRate}', [CommissionRatesController::class, 'destroy'])->name("commissionRates.destroy{$nameSuffix}");
        });

        Route::middleware('permission:dashboard.view')->group(function () use ($nameSuffix): void {
            Route::get('/reports', [ReportsController::class, 'index'])->name("reports.index{$nameSuffix}");
        });

        Route::middleware('permission:settings.view')->group(function () use ($nameSuffix): void {
            Route::get('/settings', [TenantSettingsController::class, 'edit'])->name("settings.edit{$nameSuffix}");
        });

        Route::middleware('permission:settings.edit')->group(function () use ($nameSuffix): void {
            Route::put('/settings', [TenantSettingsController::class, 'update'])->name("settings.update{$nameSuffix}");
        });
    });
};

/**
 * Registers the super-admin panel's routes. Called under the
 * admin.{mainDomain} domain (bare names, e.g. "superAdmin.dashboard") and
 * under mainDomain/admin (suffixed ".byPath", e.g. "superAdmin.dashboard.byPath"),
 * mirroring the tenant route registration pattern above.
 */
$registerSuperAdminRoutes = function (string $nameSuffix = ''): void {
    Route::get('/login', [SuperAdminLoginController::class, 'create'])->name("superAdmin.login{$nameSuffix}");
    Route::post('/login', [SuperAdminLoginController::class, 'store'])->name("superAdmin.login.store{$nameSuffix}");
    Route::post('/logout', [SuperAdminLoginController::class, 'destroy'])->middleware('auth:super_admin')->name("superAdmin.logout{$nameSuffix}");

    Route::middleware('auth:super_admin')->group(function () use ($nameSuffix): void {
        Route::get('/', [SuperAdminDashboardController::class, 'index'])->name("superAdmin.dashboard{$nameSuffix}");

        Route::get('/tenants', [TenantsController::class, 'index'])->name("superAdmin.tenants.index{$nameSuffix}");
        Route::get('/tenants/create', [TenantsController::class, 'create'])->name("superAdmin.tenants.create{$nameSuffix}");
        Route::post('/tenants', [TenantsController::class, 'store'])->name("superAdmin.tenants.store{$nameSuffix}");
        Route::get('/tenants/{tenant}', [TenantsController::class, 'show'])->name("superAdmin.tenants.show{$nameSuffix}");
        Route::get('/tenants/{tenant}/edit', [TenantsController::class, 'edit'])->name("superAdmin.tenants.edit{$nameSuffix}");
        Route::put('/tenants/{tenant}', [TenantsController::class, 'update'])->name("superAdmin.tenants.update{$nameSuffix}");
        Route::delete('/tenants/{tenant}', [TenantsController::class, 'destroy'])->name("superAdmin.tenants.destroy{$nameSuffix}");

        Route::get('/tenants/{tenant}/users', [TenantUsersController::class, 'index'])->name("superAdmin.tenants.users.index{$nameSuffix}");
        Route::get('/tenants/{tenant}/users/create', [TenantUsersController::class, 'create'])->name("superAdmin.tenants.users.create{$nameSuffix}");
        Route::post('/tenants/{tenant}/users', [TenantUsersController::class, 'store'])->name("superAdmin.tenants.users.store{$nameSuffix}");
        Route::get('/tenants/{tenant}/users/{tenantUser}/edit', [TenantUsersController::class, 'edit'])->name("superAdmin.tenants.users.edit{$nameSuffix}");
        Route::put('/tenants/{tenant}/users/{tenantUser}', [TenantUsersController::class, 'update'])->name("superAdmin.tenants.users.update{$nameSuffix}");
        Route::put('/tenants/{tenant}/users/{tenantUser}/toggle-login', [TenantUsersController::class, 'destroy'])->name("superAdmin.tenants.users.toggleLogin{$nameSuffix}");

        Route::get('/admins', [PlatformAdminsController::class, 'index'])->name("superAdmin.platformAdmins.index{$nameSuffix}");
        Route::get('/admins/create', [PlatformAdminsController::class, 'create'])->name("superAdmin.platformAdmins.create{$nameSuffix}");
        Route::post('/admins', [PlatformAdminsController::class, 'store'])->name("superAdmin.platformAdmins.store{$nameSuffix}");
        Route::get('/admins/{platformAdmin}/edit', [PlatformAdminsController::class, 'edit'])->name("superAdmin.platformAdmins.edit{$nameSuffix}");
        Route::put('/admins/{platformAdmin}', [PlatformAdminsController::class, 'update'])->name("superAdmin.platformAdmins.update{$nameSuffix}");
        Route::delete('/admins/{platformAdmin}', [PlatformAdminsController::class, 'destroy'])->name("superAdmin.platformAdmins.destroy{$nameSuffix}");

        Route::get('/profile', [SuperAdminProfileController::class, 'edit'])->name("superAdmin.profile.edit{$nameSuffix}");
        Route::put('/profile', [SuperAdminProfileController::class, 'update'])->name("superAdmin.profile.update{$nameSuffix}");

        Route::get('/activity', [SuperAdminActivityLogsController::class, 'index'])->name("superAdmin.activity.index{$nameSuffix}");

        Route::post('/tenants/{tenant}/users/{tenantUser}/impersonate', [TenantUserImpersonationController::class, 'store'])->name("superAdmin.tenants.users.impersonate{$nameSuffix}");
    });
};

Route::domain('admin.'.$mainDomain)->middleware('super_admin.only')->group(function () use ($registerSuperAdminRoutes): void {
    $registerSuperAdminRoutes();
});

Route::domain('{subdomain}.'.$mainDomain)->middleware('tenant.only')->group(function () use ($registerTenantRoutes): void {
    Route::get('/', function () {
        $destination = auth()->check() ? '/dashboard' : '/login';

        return redirect()->to(request()->getSchemeAndHttpHost().$destination);
    })->name('tenant.root');

    $registerTenantRoutes();
});

Route::domain($mainDomain)->group(function () use ($registerTenantRoutes, $registerSuperAdminRoutes): void {
    Route::middleware('super_admin.only')->prefix('/admin')->group(function () use ($registerSuperAdminRoutes): void {
        $registerSuperAdminRoutes('.byPath');
    });

    Route::middleware('tenant.only')->prefix('/{slug}')->where(['slug' => '(?!admin$|login$|register$)[a-z0-9-]+'])->group(function () use ($registerTenantRoutes): void {
        Route::middleware('auth')->get('/', [TenantDashboardController::class, 'index'])->name('tenant.dashboard.bySlugRoot');

        $registerTenantRoutes('.bySlug');
    });

    Route::get('/', function () {
        return view('welcome');
    })->name('landing');
});
