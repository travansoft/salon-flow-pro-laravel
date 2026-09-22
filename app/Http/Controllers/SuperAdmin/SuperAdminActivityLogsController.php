<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SuperAdminActivityLogRepositoryInterface;
use Illuminate\View\View;

class SuperAdminActivityLogsController extends Controller
{
    public function __construct(private SuperAdminActivityLogRepositoryInterface $activityLogRepository) {}

    public function index(): View
    {
        return view('super-admin.activity.index', ['logs' => $this->activityLogRepository->paginateLatest()]);
    }
}
