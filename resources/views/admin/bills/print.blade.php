<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $bill->invoiceNumber() }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            width: 302px;
            margin: 0 auto;
            padding: 10px;
            font-family: 'Consolas', 'Lucida Console', 'Courier New', monospace;
            font-size: 12px;
            color: #000;
        }

        .shop-name {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 19px;
            font-weight: 700;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: 700;
        }

        .divider {
            border-top: 1px solid #000;
            margin: 8px 0;
        }

        .banner {
            letter-spacing: 2px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            text-align: left;
            padding: 3px 2px;
            font-size: 10.5px;
            word-break: break-word;
            vertical-align: top;
        }

        th:first-child, td:first-child {
            width: 8%;
        }

        th:nth-child(2), td:nth-child(2) {
            width: 40%;
        }

        th:nth-child(3), td:nth-child(3) {
            width: 15%;
        }

        th:nth-child(4), td:nth-child(4) {
            width: 18%;
        }

        th:nth-child(5), td:nth-child(5) {
            width: 19%;
        }

        th.num, td.num {
            text-align: right;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 10px;
            font-size: 11px;
        }

        .summary-grid .totals-row {
            padding: 1px 0;
        }

        .grand-total {
            font-size: 14px;
            font-weight: 700;
        }

        .amount-words {
            font-size: 11px;
        }

        .no-print {
            margin-top: 14px;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    @if ($bill->tenant->print_logo)
        <div class="center"><img src="{{ $bill->tenant->print_logo }}" alt="Logo" style="max-height:50px;max-width:100%"></div>
    @endif

    <div class="center shop-name">{{ $bill->tenant->legal_name ?? $bill->tenant->name }}</div>
    @if ($bill->tenant->address)
        <div class="center">{{ $bill->tenant->address }}</div>
    @endif
    @if ($bill->tenant->phone)
        <div class="center">PH: {{ $bill->tenant->phone }}</div>
    @endif

    <div class="divider"></div>

    @if ($bill->tenant->gst_number)
        <div class="center">GSTIN: {{ $bill->tenant->gst_number }}</div>
    @endif
    <div class="center bold banner">:::: INVOICE ::::</div>

    <div class="divider"></div>

    <div class="totals-row"><span>Inv. No.</span><span>{{ $bill->invoiceNumber() }}</span></div>
    <div class="totals-row"><span>Date</span><span>{{ $bill->created_at->format('d-M-Y h:i A') }}</span></div>
    @if ($bill->createdBy)
        <div class="totals-row"><span>Billed by</span><span>{{ $bill->createdBy->name }}</span></div>
    @endif
    <div class="totals-row"><span>Customer</span><span>{{ $bill->client->name }}</span></div>
    @if ($bill->client->gst_number)
        <div class="totals-row"><span>Customer GSTIN</span><span>{{ $bill->client->gst_number }}</span></div>
    @endif

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Name of product</th>
                <th class="num">Qty</th>
                <th class="num">Rate</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->lineItems as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if ($item->service?->hsn_sac_code)
                            <br><span style="font-size:9px">HSN/SAC {{ $item->service->hsn_sac_code }}</span>
                        @endif
                    </td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="center bold">:::: SUMMARY ::::</div>
    <div class="totals-row"><span>Item Qty</span><span>{{ $bill->lineItems->sum('quantity') }}</span></div>
    @if ($bill->discount_amount > 0)
        <div class="totals-row"><span>Discount ({{ number_format($bill->discount_percent, 2) }}%)</span><span>&minus;{{ number_format($bill->discount_amount, 2) }}</span></div>
    @endif

    @php
        $gstBreakdown = collect($bill->gstBreakdownByRate())->filter(fn ($amounts) => bccomp($amounts['taxable'], '0', 2) > 0);
        $showPerRateBreakdown = $gstBreakdown->count() > 1;
    @endphp

    @if ($showPerRateBreakdown)
        @foreach ($gstBreakdown as $rate => $amounts)
            <div class="divider"></div>
            <div class="totals-row bold"><span>{{ $rate }}% GST</span><span></span></div>
            <div class="summary-grid">
                <div class="totals-row"><span>Taxable</span><span>{{ number_format($amounts['taxable'], 2) }}</span></div>
                @if (bccomp($amounts['igst'], '0', 2) > 0)
                    <div class="totals-row"><span>IGST</span><span>{{ number_format($amounts['igst'], 2) }}</span></div>
                @else
                    @if (bccomp($amounts['cgst'], '0', 2) > 0)
                        <div class="totals-row"><span>CGST</span><span>{{ number_format($amounts['cgst'], 2) }}</span></div>
                    @endif
                    @if (bccomp($amounts['sgst'], '0', 2) > 0)
                        <div class="totals-row"><span>SGST</span><span>{{ number_format($amounts['sgst'], 2) }}</span></div>
                    @endif
                @endif
            </div>
        @endforeach
    @else
        <div class="summary-grid">
            <div class="totals-row"><span>Taxable Amt</span><span>{{ number_format($bill->subtotal - $bill->discount_amount, 2) }}</span></div>
            @if ($gstBreakdown->isNotEmpty())
                <div class="totals-row"><span>{{ $gstBreakdown->keys()->first() }}% GST</span><span>{{ number_format($bill->tax_amount, 2) }}</span></div>
            @endif
            @if (bccomp($bill->igst_amount, '0', 2) > 0)
                <div class="totals-row"><span>IGST Amt</span><span>{{ number_format($bill->igst_amount, 2) }}</span></div>
            @else
                @if (bccomp($bill->cgst_amount, '0', 2) > 0)
                    <div class="totals-row"><span>CGST Amt</span><span>{{ number_format($bill->cgst_amount, 2) }}</span></div>
                @endif
                @if (bccomp($bill->sgst_amount, '0', 2) > 0)
                    <div class="totals-row"><span>SGST Amt</span><span>{{ number_format($bill->sgst_amount, 2) }}</span></div>
                @endif
            @endif
        </div>
    @endif

    <div class="divider"></div>

    <div class="totals-row"><span>Total Amt</span><span>{{ number_format($bill->total, 2) }}</span></div>

    <div class="divider"></div>

    <div class="totals-row grand-total"><span>Bill Amount</span><span>&#8377;{{ number_format($bill->total, 2) }}</span></div>

    <div class="amount-words">{{ \App\Services\NumberToWords::rupees((float) $bill->total) }}</div>

    <div class="divider"></div>

    <div class="totals-row"><span>Paid</span><span>{{ number_format($bill->amount_paid, 2) }}</span></div>
    <div class="totals-row"><span>Balance due</span><span>{{ number_format($bill->balanceDue(), 2) }}</span></div>

    <div class="divider"></div>
    <div class="center">Thank you, Visit again..</div>

    <div class="no-print">
        <button onclick="window.print()">Print</button>
    </div>
</body>
</html>
