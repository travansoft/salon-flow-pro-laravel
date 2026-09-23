@extends('layouts.admin')

@section('title', 'Bill #'.$bill->bill_number)

@section('content')
    @php
        $statusPillClass = match ($bill->status) {
            \App\Models\Bill::StatusPaid => 'sfp-pill-green',
            \App\Models\Bill::StatusPartial => 'sfp-pill-amber',
            \App\Models\Bill::StatusUnpaid => 'sfp-pill-blue',
            \App\Models\Bill::StatusVoid => 'sfp-pill-neutral',
            default => 'sfp-pill-neutral',
        };
    @endphp

    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
        <div>
            <h1 style="font-family:'Bricolage Grotesque',Outfit,sans-serif;font-weight:600;font-size:30px;margin:0;letter-spacing:-.01em">Bill &middot; {{ $bill->invoiceNumber() }}</h1>
            <p style="font-size:13.5px;color:#66736F;margin:6px 0 0">
                {{ $bill->client->name }}
                &middot; {{ $bill->created_at->format('d M Y, h:i A') }}
                @if ($bill->createdBy)
                    &middot; Billed by {{ $bill->createdBy->name }}
                @endif
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <a href="{{ $tenantUrl->route('bills.print', $bill) }}" target="_blank" class="sfp-btn-outline">Print</a>
            <span class="sfp-pill {{ $statusPillClass }}">{{ ucfirst($bill->status) }}</span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:14px">
        <div style="display:grid;gap:14px;align-content:start">
            <div style="background:#fff;border:1px solid #E3EAE8;border-radius:18px;overflow:hidden">
                <div style="display:grid;grid-template-columns:1fr 78px 96px 96px;padding:14px 20px;background:#FDFAF8;border-bottom:1px solid #E3EAE8;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:#94A19D">
                    <span>Item</span>
                    <span style="text-align:center">Qty</span>
                    <span style="text-align:right">Rate</span>
                    <span style="text-align:right">Amount</span>
                </div>
                @foreach ($bill->lineItems as $item)
                    <div style="display:grid;grid-template-columns:1fr 78px 96px 96px;padding:16px 20px;border-bottom:1px solid #EDF1F0;align-items:center">
                        <div>
                            <div style="font-size:14.5px">{{ $item->description }}</div>
                            <div style="font-size:11.5px;color:#94A19D;margin-top:3px">
                                {{ $item->tax_rate }}% tax
                                @if ($item->staffProfile)
                                    &middot; {{ $item->staffProfile->name }}
                                @endif
                            </div>
                        </div>
                        <span class="sfp-mono" style="text-align:center;font-size:13px">{{ $item->quantity }}</span>
                        <span class="sfp-mono" style="text-align:right;font-size:13px;color:#66736F">&#8377;{{ number_format($item->unit_price, 2) }}</span>
                        <span class="sfp-mono" style="text-align:right;font-size:13.5px">&#8377;{{ number_format($item->line_total, 2) }}</span>
                    </div>
                @endforeach
            </div>

            <div style="background:#fff;border:1px solid #E3EAE8;border-radius:18px;padding:20px 22px">
                <div style="display:grid;gap:11px;font-size:14px">
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:#66736F">Subtotal</span>
                        <span class="sfp-mono">&#8377;{{ number_format($bill->subtotal, 2) }}</span>
                    </div>
                    @if ($bill->discount_amount > 0)
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:#A8506B">Discount ({{ number_format($bill->discount_percent, 2) }}%)</span>
                            <span class="sfp-mono" style="color:#A8506B">&minus;&#8377;{{ number_format($bill->discount_amount, 2) }}</span>
                        </div>
                    @endif
                    @if ($bill->igst_amount > 0)
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:#66736F">IGST</span>
                            <span class="sfp-mono">&#8377;{{ number_format($bill->igst_amount, 2) }}</span>
                        </div>
                    @else
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:#66736F">CGST</span>
                            <span class="sfp-mono">&#8377;{{ number_format($bill->cgst_amount, 2) }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:#66736F">SGST</span>
                            <span class="sfp-mono">&#8377;{{ number_format($bill->sgst_amount, 2) }}</span>
                        </div>
                    @endif
                    <div style="display:flex;justify-content:space-between;align-items:baseline;padding-top:13px;margin-top:4px;border-top:1px solid #E3EAE8">
                        <span style="font-size:15px">Total payable</span>
                        <span style="font-family:'Bricolage Grotesque',Outfit,sans-serif;font-weight:600;font-size:30px">&#8377;{{ number_format($bill->total, 2) }}</span>
                    </div>
                </div>
                <p style="font-size:12.5px;color:#66736F;margin:14px 0 0">
                    Paid <span class="sfp-mono">&#8377;{{ number_format($bill->amount_paid, 2) }}</span>
                    &middot; Balance due <span class="sfp-mono">&#8377;{{ number_format($bill->balanceDue(), 2) }}</span>
                </p>
            </div>
        </div>

        <div style="display:grid;gap:14px;align-content:start">
            <div class="sfp-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                    <h2 class="sfp-card-title" style="margin:0">Payments</h2>
                    @can('billing.create')
                        <button type="button" class="sfp-action-link" style="background:none;border:none;cursor:pointer" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">Add payment</button>
                    @endcan
                </div>

                @forelse ($bill->payments as $payment)
                    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #EDF1F0;font-size:13.5px">
                        <span style="color:#66736F">{{ ucfirst($payment->method) }}</span>
                        <span class="sfp-mono">&#8377;{{ number_format($payment->amount, 2) }}</span>
                    </div>
                @empty
                    <p style="color:#66736F;font-size:13.5px;margin:0">No payments recorded yet.</p>
                @endforelse
            </div>

            @if ($bill->refunds->isNotEmpty())
                <div class="sfp-card">
                    <h2 class="sfp-card-title">Refunds</h2>

                    @foreach ($bill->refunds as $refund)
                        <div style="padding:10px 0;border-bottom:1px solid #EDF1F0;font-size:13.5px">
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#66736F">Refund</span>
                                <span class="sfp-mono">&#8377;{{ number_format($refund->amount, 2) }}</span>
                            </div>
                            <div style="color:#94A19D;font-size:12.5px;margin-top:3px">{{ $refund->reason }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @can('billing.edit')
                <div class="sfp-card">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <h2 class="sfp-card-title" style="margin:0">Refunds</h2>
                        <button type="button" class="sfp-action-link" style="background:none;border:none;cursor:pointer" data-bs-toggle="modal" data-bs-target="#issueRefundModal">Issue refund</button>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    @can('billing.create')
        <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ $tenantUrl->route('bills.recordPayment', $bill) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title" id="recordPaymentModalLabel">Record payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="sfp-field">
                                <label class="sfp-label">Method</label>
                                <select name="payments[0][method]" class="sfp-select">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="upi">UPI</option>
                                </select>
                            </div>
                            <div class="sfp-field">
                                <label class="sfp-label">Amount</label>
                                <input type="number" step="0.01" name="payments[0][amount]" class="sfp-input">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="sfp-btn-primary">Record payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('billing.edit')
        <div class="modal fade" id="issueRefundModal" tabindex="-1" aria-labelledby="issueRefundModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ $tenantUrl->route('bills.refund', $bill) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title" id="issueRefundModalLabel">Issue refund</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="sfp-field">
                                <label class="sfp-label">Refund amount</label>
                                <input type="number" step="0.01" name="amount" class="sfp-input">
                            </div>
                            <div class="sfp-field">
                                <label class="sfp-label">Reason</label>
                                <input type="text" name="reason" class="sfp-input">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="sfp-btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="sfp-btn-primary" style="background:#A8506B;border-color:#A8506B">Issue refund</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection
