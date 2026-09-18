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
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: 700;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 2px 0;
            font-size: 11px;
        }

        th.num, td.num {
            text-align: right;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
        }

        .grand-total {
            font-size: 14px;
            font-weight: 700;
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

    <div class="center bold">{{ $bill->tenant->legal_name ?? $bill->tenant->name }}</div>
    @if ($bill->tenant->address)
        <div class="center">{{ $bill->tenant->address }}</div>
    @endif
    @if ($bill->tenant->gst_number)
        <div class="center">GSTIN: {{ $bill->tenant->gst_number }}</div>
    @endif

    <div class="divider"></div>

    <div class="totals-row"><span>Invoice</span><span>{{ $bill->invoiceNumber() }}</span></div>
    <div class="totals-row"><span>Date</span><span>{{ $bill->created_at->format('d-M-Y H:i') }}</span></div>
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
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Rate</th>
                <th class="num">Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->lineItems as $item)
                <tr>
                    <td colspan="4">
                        {{ $item->description }}
                        @if ($item->service?->hsn_sac_code)
                            <span style="font-size:10px">(HSN/SAC {{ $item->service->hsn_sac_code }})</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="totals-row"><span>Subtotal</span><span>{{ number_format($bill->subtotal, 2) }}</span></div>
    @if ($bill->igst_amount > 0)
        <div class="totals-row"><span>IGST</span><span>{{ number_format($bill->igst_amount, 2) }}</span></div>
    @else
        <div class="totals-row"><span>CGST</span><span>{{ number_format($bill->cgst_amount, 2) }}</span></div>
        <div class="totals-row"><span>SGST</span><span>{{ number_format($bill->sgst_amount, 2) }}</span></div>
    @endif

    <div class="divider"></div>

    <div class="totals-row grand-total"><span>Total</span><span>&#8377;{{ number_format($bill->total, 2) }}</span></div>
    <div class="totals-row"><span>Paid</span><span>{{ number_format($bill->amount_paid, 2) }}</span></div>
    <div class="totals-row"><span>Balance due</span><span>{{ number_format($bill->balanceDue(), 2) }}</span></div>

    <div class="divider"></div>
    <div class="center">Thank you for visiting!</div>

    <div class="no-print">
        <button onclick="window.print()">Print</button>
    </div>
</body>
</html>
