<?php

namespace App\Services;

use App\Models\BillPayment;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DayBookService
{
    public const TrackedMethods = [BillPayment::MethodCash, BillPayment::MethodUpi];

    public function __construct(
        private BillRepositoryInterface $billRepository,
        private ExpenseRepositoryInterface $expenseRepository,
    ) {}

    /**
     * @return array{
     *     entries: Collection<int, array<string, mixed>>,
     *     opening: array<string, string>,
     *     closing: array<string, string>,
     *     totals: array<string, array{in: string, out: string}>,
     * }
     */
    public function forRange(Carbon $from, Carbon $to): array
    {
        $entries = $this->entries($from, $to);

        $opening = $this->openingBalances($from);

        return [
            'entries' => $entries,
            'opening' => $opening,
            'closing' => $this->closingBalances($opening, $entries),
            'totals' => $this->totalsByMethod($entries),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function entries(Carbon $from, Carbon $to): Collection
    {
        $payments = $this->billRepository->paymentsBetween($from, $to)->map(fn ($payment): array => [
            'date' => $payment->created_at,
            'type' => 'in',
            'source' => 'Bill',
            'reference' => $payment->bill->invoiceNumber(),
            'bill_id' => $payment->bill_id,
            'description' => $payment->bill->client?->name ?? 'Walk-in',
            'method' => $payment->method,
            'amount' => (string) $payment->amount,
        ]);

        $refunds = $this->billRepository->refundsBetween($from, $to)->map(fn ($refund): array => [
            'date' => $refund->created_at,
            'type' => 'out',
            'source' => 'Refund',
            'reference' => $refund->bill->invoiceNumber(),
            'bill_id' => $refund->bill_id,
            'description' => $refund->reason,
            'method' => $refund->method,
            'amount' => (string) $refund->amount,
        ]);

        $expenses = $this->expenseRepository->getBetweenDates($from, $to)->map(fn ($expense): array => [
            'date' => $expense->expense_date->copy()->endOfDay(),
            'type' => 'out',
            'source' => 'Expense',
            'reference' => $expense->category?->name ?? 'Expense',
            'bill_id' => null,
            'description' => $expense->description,
            'method' => $expense->payment_method,
            'amount' => (string) $expense->amount,
        ]);

        return $payments->concat($refunds)->concat($expenses)
            ->sortBy(fn (array $entry): string => $entry['date']->format('Y-m-d').($entry['type'] === 'in' ? '0' : '1').$entry['date']->format('H:i:s'))
            ->values();
    }

    /** @return array<string, string> */
    private function openingBalances(Carbon $from): array
    {
        $payments = $this->billRepository->paymentTotalsByMethodBefore($from);
        $refunds = $this->billRepository->refundTotalsByMethodBefore($from);
        $expenses = $this->expenseRepository->totalsByMethodBefore($from);

        $opening = [];

        foreach (self::TrackedMethods as $method) {
            $received = bcsub($payments[$method] ?? '0.00', $refunds[$method] ?? '0.00', 2);

            $opening[$method] = bcsub($received, $expenses[$method] ?? '0.00', 2);
        }

        return $opening;
    }

    /**
     * @param  array<string, string>  $opening
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, string>
     */
    private function closingBalances(array $opening, Collection $entries): array
    {
        $closing = $opening;

        foreach ($entries as $entry) {
            if (! array_key_exists($entry['method'], $closing)) {
                continue;
            }

            $closing[$entry['method']] = $entry['type'] === 'in'
                ? bcadd($closing[$entry['method']], $entry['amount'], 2)
                : bcsub($closing[$entry['method']], $entry['amount'], 2);
        }

        return $closing;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, array{in: string, out: string}>
     */
    private function totalsByMethod(Collection $entries): array
    {
        $totals = [];

        foreach ([...self::TrackedMethods, BillPayment::MethodCard] as $method) {
            $totals[$method] = ['in' => '0.00', 'out' => '0.00'];
        }

        foreach ($entries as $entry) {
            $direction = $entry['type'];

            $totals[$entry['method']][$direction] = bcadd($totals[$entry['method']][$direction], $entry['amount'], 2);
        }

        return $totals;
    }
}
