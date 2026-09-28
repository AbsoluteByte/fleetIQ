<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicle Profit/Loss Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 16px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f3f3; }
        .text-right { text-align: right; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
@php
    $car = $report['car'] ?? null;
    $summary = $report['summary'] ?? [];
    $lines = $report['lines'] ?? [];
@endphp
<h1>Vehicle Profit/Loss — {{ $car?->registration ?? '—' }}</h1>
<p class="muted">
    Generated {{ now()->format('d M Y H:i') }}
    @if($report['from'] ?? null)
        | From {{ $report['from']->format('d M Y') }}
    @endif
    @if($report['to'] ?? null)
        | To {{ $report['to']->format('d M Y') }}
    @endif
</p>

<h2>Summary — Income</h2>
<table>
    <tr><td>Rental (invoiced)</td><td class="text-right">£{{ number_format($summary['rental_invoiced'] ?? 0, 2) }}</td></tr>
    <tr><td>Rental (collected)</td><td class="text-right">£{{ number_format($summary['rental_collected'] ?? 0, 2) }}</td></tr>
    <tr><td>Damage / excess (invoiced)</td><td class="text-right">£{{ number_format($summary['damage_invoiced'] ?? 0, 2) }}</td></tr>
    <tr><td>Damage / excess (collected)</td><td class="text-right">£{{ number_format($summary['damage_collected'] ?? 0, 2) }}</td></tr>
    <tr><td>Sale + other income</td><td class="text-right">£{{ number_format(($summary['sale_income'] ?? 0) + ($summary['other_income'] ?? 0), 2) }}</td></tr>
    <tr><td><strong>Total collected income</strong></td><td class="text-right"><strong>£{{ number_format($summary['total_income_collected'] ?? 0, 2) }}</strong></td></tr>
</table>

<h2>Summary — Expenses</h2>
<table>
    <tr><td>Total expenses</td><td class="text-right">£{{ number_format($summary['total_expenses'] ?? 0, 2) }}</td></tr>
    <tr><td><strong>Net (collected − expenses)</strong></td><td class="text-right"><strong>£{{ number_format($summary['net_profit_collected'] ?? 0, 2) }}</strong></td></tr>
</table>

<h2>Transaction history</h2>
<table>
    <thead>
    <tr>
        <th>Date</th>
        <th>Dir</th>
        <th>Category</th>
        <th>Description</th>
        <th class="text-right">Amount</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $line)
        <tr>
            <td>{{ $line['date'] ?? '—' }}</td>
            <td>{{ ($line['direction'] ?? '') === 'in' ? 'In' : 'Out' }}</td>
            <td>{{ $line['category_label'] ?? '' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($line['description'] ?? '', 80) }}</td>
            <td class="text-right">£{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
            <td>{{ $line['posting_status'] ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
