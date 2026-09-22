<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Service;
use InvalidArgumentException;

class QuickBillService
{
    public const WalkInClientName = 'Walk-in customer';

    public function __construct(
        private BillingService $billingService,
        private TenantContext $tenantContext,
    ) {}

    public function findServiceByCode(string $code): ?Service
    {
        return Service::query()->active()->withCode($code)->first();
    }

    public function findClientByPhone(string $phone): ?Client
    {
        return Client::query()->where('phone', $phone)->first();
    }

    public function walkInClient(): Client
    {
        $tenant = $this->tenantContext->get();

        return Client::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => self::WalkInClientName, 'phone' => ''],
        );
    }

    /**
     * Resolves the client for a bill from either a selected client id, freeform
     * name/phone/GSTIN details typed for a new client, or nothing at all.
     *
     * @param  array{client_id?: int|null, name?: string|null, phone?: string|null, gst_number?: string|null}  $details
     */
    public function resolveClient(array $details): Client
    {
        if (! empty($details['client_id'])) {
            return Client::query()->findOrFail($details['client_id']);
        }

        $name = trim((string) ($details['name'] ?? ''));
        $phone = trim((string) ($details['phone'] ?? ''));
        $gstNumber = trim((string) ($details['gst_number'] ?? ''));

        if ($name === '' && $phone === '' && $gstNumber === '') {
            return $this->walkInClient();
        }

        $tenant = $this->tenantContext->get();

        if ($phone !== '') {
            $existing = Client::query()->where('tenant_id', $tenant->id)->where('phone', $phone)->first();

            if ($existing) {
                return $existing;
            }
        }

        return Client::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name !== '' ? $name : self::WalkInClientName,
            'phone' => $phone,
            'gst_number' => $gstNumber !== '' ? $gstNumber : null,
        ]);
    }

    /**
     * Creates a bill from resolved line items and settles it in full with a single payment.
     *
     * @param  array<int, array{service_id: int, staff_profile_id?: int|null, quantity?: int, description?: string, unit_price?: float}>  $items
     * @param  array{client_id?: int|null, name?: string|null, phone?: string|null, gst_number?: string|null}  $clientDetails
     */
    public function createAndSettle(array $items, array $clientDetails, string $paymentMethod, int $staffUserId, float $discountPercent = 0): Bill
    {
        if ($items === []) {
            throw new InvalidArgumentException('At least one line item is required.');
        }

        $tenant = $this->tenantContext->get();

        $lineItems = [];
        foreach ($items as $item) {
            $staffProfileId = $item['staff_profile_id'] ?? null;

            if (! empty($item['service_id'])) {
                $service = Service::query()->active()->find($item['service_id']);

                if (! $service) {
                    throw new InvalidArgumentException('One of the selected services is no longer available.');
                }

                if ($staffProfileId && ! $service->staff()->where('staff_profiles.id', $staffProfileId)->exists()) {
                    throw new InvalidArgumentException("The selected staff member is not eligible to perform \"{$service->name}\".");
                }

                $lineItems[] = [
                    'service_id' => $service->id,
                    'staff_profile_id' => $staffProfileId,
                    'description' => $service->name,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => (float) $service->price,
                    'tax_rate' => (float) ($service->tax_rate ?? $tenant->default_gst_rate),
                ];

                continue;
            }

            $lineItems[] = [
                'service_id' => null,
                'staff_profile_id' => $staffProfileId,
                'description' => $item['description'] ?? 'Manual item',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_rate' => (float) $tenant->default_gst_rate,
            ];
        }

        $client = $this->resolveClient($clientDetails);

        $bill = $this->billingService->createManualBill($client->id, $staffUserId, $lineItems, $discountPercent);

        return $this->billingService->recordPayments($bill, [
            ['method' => $paymentMethod, 'amount' => (float) $bill->total],
        ], $staffUserId);
    }
}
