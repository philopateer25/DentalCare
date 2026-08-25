<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .details { margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .totals { margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice</h1>
        <p>Invoice #: {{ $invoice->invoice_number }}</p>
        <p>Date: {{ $invoice->invoice_date?->format('Y-m-d') }}</p>
    </div>

    <div class="details">
        <h3>Patient: {{ $invoice->patient->full_name ?? 'N/A' }}</h3>
        <p>Doctor: {{ $invoice->doctor->name ?? 'N/A' }}</p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description ?? 'Treatment' }}</td>
                <td>{{ number_format($item->total, 2) }} EGP</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p>Subtotal: {{ number_format($invoice->subtotal, 2) }} EGP</p>
        <p>Discount: {{ number_format($invoice->discount, 2) }} EGP</p>
        <p>Tax: {{ number_format($invoice->tax, 2) }} EGP</p>
        <h3>Total Amount: {{ number_format($invoice->total_amount, 2) }} EGP</h3>
        <p>Paid Amount: {{ number_format($invoice->paid_amount, 2) }} EGP</p>
        <h4>Remaining Balance: {{ number_format($invoice->remaining_balance, 2) }} EGP</h4>
    </div>
</body>
</html>
