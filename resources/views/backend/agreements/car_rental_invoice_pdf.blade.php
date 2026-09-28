<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Car Rental Invoice — {{ $record->displaySerial() }}</title>
    <style>
        @page { margin: 28px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; line-height: 1.45; }
        .center { text-align: center; }
        .title { font-size: 16px; font-weight: bold; letter-spacing: 0.5px; margin: 4px 0 2px; }
        .subtitle { font-size: 13px; font-weight: bold; margin: 0 0 10px; }
        .meta { font-size: 10px; margin-bottom: 14px; }
        .meta div { margin: 2px 0; }
        h3 { font-size: 11px; margin: 14px 0 6px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.breakdown { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
        table.breakdown th, table.breakdown td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
        }
        table.breakdown th { background: #f4f4f4; text-align: left; }
        table.breakdown td.amount { text-align: right; white-space: nowrap; }
        .totals-block { margin-top: 8px; }
        .totals-block table { width: 100%; border-collapse: collapse; }
        .totals-block td { padding: 3px 0; }
        .totals-block td.label { width: 70%; }
        .totals-block td.val { text-align: right; font-weight: bold; }
        .page-break { page-break-before: always; }
        .declaration { font-size: 11px; text-align: justify; margin: 12px 0; }
        .signature { margin-top: 28px; font-weight: bold; }
    </style>
</head>
<body>
@php
    $totals = $totals ?? ($record->totals_snapshot ?? []);
    $weekCount = (int) ($totals['week_count'] ?? 0);
    $weeklyRate = (float) ($totals['weekly_rate'] ?? 0);
    $totalPaid = (float) ($totals['total_paid'] ?? 0);
    $insuranceTotal = (float) ($totals['insurance_total'] ?? 0);
    $rentAfterInsurance = (float) ($totals['rent_after_insurance'] ?? 0);
    $netExVat = (float) ($totals['net_ex_vat'] ?? 0);
    $vatAmount = (float) ($totals['vat_amount'] ?? 0);
    $vatRate = (float) ($totals['vat_rate'] ?? $record->vat_rate ?? 20);
    $weeklyInsurance = (float) ($totals['weekly_insurance_amount'] ?? $record->weekly_insurance_amount ?? 0);
    $rentalStart = $record->rental_start_date ?? ($totals['rental_start_date'] ?? null);
    $rentalEnd = $record->rental_end_date ?? ($totals['rental_end_date'] ?? null);
    $driverName = $driver?->full_name ?? 'Customer';
    $companyName = strtoupper($company?->name ?? 'COMPANY');
    $carLabel = trim(($car?->carModel?->name ?? '').' '.($car?->registration ?? ''));
    $fmt = fn ($n) => '£'.number_format((float) $n, 2);
@endphp

<div class="center">
    <div class="title">{{ $companyName }}</div>
    <div class="subtitle">CAR RENTAL INVOICE</div>
</div>

<div class="meta center">
    @if($company?->company_registration_number)
        <div>Company Number {{ $company->company_registration_number }}</div>
    @endif
    @if($record->vat_registration_number)
        <div>VAT Registration Number {{ $record->vat_registration_number }}</div>
    @endif
    @if($company)
        <div>Registered Office {{ $company->commaSeparatedAddress() }}</div>
    @endif
    <div>Invoice No. {{ $record->displaySerial() }}</div>
    <div>Invoice Date {{ $record->invoice_date?->format('j F Y') }}</div>
</div>

<h3>Billed To</h3>
<div>Customer {{ $driverName }}</div>
@if($driver)
    <div>Address @include('backend.agreements._driver_address_pdf', ['driver' => $driver])</div>
@endif

<h3>Vehicle &amp; Rental Details</h3>
<table class="breakdown">
    <tr><td>Car Make &amp; Model</td><td>{{ $car?->carModel?->name ?? '—' }}</td></tr>
    <tr><td>Registration</td><td>{{ $car?->registration ?? '—' }}</td></tr>
    <tr><td>Rental Start Date</td><td>{{ $rentalStart ? \Carbon\Carbon::parse($rentalStart)->format('j F Y') : '—' }}</td></tr>
    <tr><td>Rental End Date</td><td>{{ $rentalEnd ? \Carbon\Carbon::parse($rentalEnd)->format('j F Y') : '—' }}</td></tr>
    <tr><td>Rental Period</td><td>{{ $weekCount }} {{ $weekCount === 1 ? 'week' : 'weeks' }}</td></tr>
    <tr><td>Weekly Rental Rate</td><td>{{ $fmt($weeklyRate) }} including insurance</td></tr>
</table>

<h3>Charge Breakdown</h3>
<table class="breakdown">
    <thead>
        <tr>
            <th>Description</th>
            <th>Calculation</th>
            <th class="amount">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total rental payments</td>
            <td>{{ $weekCount }} x {{ $fmt($weeklyRate) }}</td>
            <td class="amount">{{ $fmt($totalPaid) }}</td>
        </tr>
        <tr>
            <td>Insurance</td>
            <td>{{ $weekCount }} x {{ $fmt($weeklyInsurance) }}</td>
            <td class="amount">{{ $fmt($insuranceTotal) }}</td>
        </tr>
        <tr>
            <td>Vehicle rental after insurance</td>
            <td>{{ $fmt($totalPaid) }} - {{ $fmt($insuranceTotal) }}</td>
            <td class="amount">{{ $fmt($rentAfterInsurance) }}</td>
        </tr>
        <tr>
            <td>Vehicle rental excluding VAT</td>
            <td></td>
            <td class="amount">{{ $fmt($netExVat) }}</td>
        </tr>
        <tr>
            <td>VAT @ {{ number_format($vatRate, 0) }}%</td>
            <td></td>
            <td class="amount">{{ $fmt($vatAmount) }}</td>
        </tr>
        <tr>
            <td>Vehicle rental including VAT</td>
            <td></td>
            <td class="amount">{{ $fmt($rentAfterInsurance) }}</td>
        </tr>
    </tbody>
</table>

<div class="totals-block">
    <table>
        <tr><td class="label"><strong>TOTAL PAID:</strong></td><td class="val">{{ $fmt($totalPaid) }}</td></tr>
        <tr><td class="label">Total insurance</td><td class="val">{{ $fmt($insuranceTotal) }}</td></tr>
        <tr><td class="label">Net vehicle rental before VAT</td><td class="val">{{ $fmt($netExVat) }}</td></tr>
        <tr><td class="label">VAT</td><td class="val">{{ $fmt($vatAmount) }}</td></tr>
        <tr><td class="label">Vehicle rental including VAT</td><td class="val">{{ $fmt($rentAfterInsurance) }}</td></tr>
        <tr><td class="label">Total amount paid including insurance</td><td class="val">{{ $fmt($totalPaid) }}</td></tr>
    </table>
</div>

<div class="page-break"></div>

<h3 class="center">Payment Declaration</h3>
<p class="declaration">
    This invoice confirms that {{ $driverName }} rented a {{ $car?->carModel?->name ?? 'vehicle' }}, registration {{ $car?->registration ?? '—' }}, from {{ $company?->name ?? 'the company' }}
    from {{ $rentalStart ? \Carbon\Carbon::parse($rentalStart)->format('j F Y') : '—' }} to {{ $rentalEnd ? \Carbon\Carbon::parse($rentalEnd)->format('j F Y') : '—' }},
    a total of {{ $weekCount }} {{ $weekCount === 1 ? 'week' : 'weeks' }}, at an agreed rate of {{ $fmt($weeklyRate) }} per week.
    The total amount paid is {{ $fmt($totalPaid) }}.
    The weekly charge includes insurance of {{ $fmt($weeklyInsurance) }} per week, totalling {{ $fmt($insuranceTotal) }}.
    After deducting insurance, the vehicle rental charge is {{ $fmt($rentAfterInsurance) }} inclusive of VAT, comprising {{ $fmt($netExVat) }} net rental charge and {{ $fmt($vatAmount) }} VAT.
</p>
<p class="declaration"><strong>Amount Paid in Full: {{ $fmt($totalPaid) }}</strong></p>
<p class="signature">For and on behalf of {{ $companyName }}</p>
</body>
</html>
