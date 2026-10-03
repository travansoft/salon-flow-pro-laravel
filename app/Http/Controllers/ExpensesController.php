<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\ExpenseFilterRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Models\Expense;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Services\ExpenseService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpensesController extends Controller
{
    public function __construct(
        private ExpenseRepositoryInterface $expenseRepository,
        private ExpenseCategoryRepositoryInterface $categoryRepository,
        private ExpenseService $expenseService,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(ExpenseFilterRequest $request): View
    {
        abort_unless($request->user()->can('expenses.view'), 403);

        [$from, $to] = $request->range();

        $expenses = $this->expenseRepository->getFiltered($from, $to, $request->filters());

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'from' => $from,
            'to' => $to,
            'filters' => $request->filters(),
            'categories' => $this->categoryRepository->getAll(),
            'total' => $this->expenseService->totalOf($expenses),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('expenses.create'), 403);

        $categories = $this->categoryRepository->getActive();

        return view('admin.expenses.create', ['categories' => $categories]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.create'), 403);

        $expense = $this->expenseService->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect($this->tenantUrl->route('expenses.show', ['expense' => $expense]))->with('status', 'Expense recorded.');
    }

    public function show(Request $request, string $subdomain, Expense $expense): View
    {
        abort_unless($request->user()->can('expenses.view'), 403);

        return view('admin.expenses.show', ['expense' => $expense]);
    }

    public function edit(Request $request, string $subdomain, Expense $expense): View
    {
        abort_unless($request->user()->can('expenses.edit'), 403);

        $categories = $this->categoryRepository->getActive();

        return view('admin.expenses.edit', ['expense' => $expense, 'categories' => $categories]);
    }

    public function update(UpdateExpenseRequest $request, string $subdomain, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.edit'), 403);

        $this->expenseService->update($expense, $request->validated());

        return redirect($this->tenantUrl->route('expenses.show', ['expense' => $expense]))->with('status', 'Expense updated.');
    }

    public function destroy(Request $request, string $subdomain, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.delete'), 403);

        $this->expenseService->delete($expense);

        return redirect($this->tenantUrl->route('expenses.index'))->with('status', 'Expense removed.');
    }
}
