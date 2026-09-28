@php
    $plReport = $vehicleProfitLossReport ?? null;
    $summary = is_array($plReport) ? ($plReport['summary'] ?? []) : [];
    $lines = is_array($plReport) ? ($plReport['lines'] ?? []) : [];
    $plCar = is_array($plReport) ? ($plReport['car'] ?? null) : null;
@endphp

<form method="GET" action="{{ route('reports.index') }}" class="mb-2" id="vehiclePlReportForm">
    <div class="form-row align-items-end">
        <div class="form-group col-md-4 col-lg-3 mb-1">
            <label class="small text-muted mb-25 d-block" for="pl_car_id">Registration</label>
            <select name="pl_car_id" id="pl_car_id" class="form-control select-search" required>
                <option value="">Select vehicle</option>
                @foreach(($ticketCars ?? $cars ?? collect())->sortBy('registration') as $reportCar)
                    <option value="{{ $reportCar->id }}" @selected((int) ($plCarId ?? 0) === (int) $reportCar->id)>
                        {{ $reportCar->registration }}@if($reportCar->carModel) — {{ $reportCar->carModel->name }}@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-2 col-lg-2 mb-1">
            <label class="small text-muted mb-25 d-block" for="pl_from">From</label>
            <input type="date" name="pl_from" id="pl_from" class="form-control" value="{{ $plFrom ?? '' }}">
        </div>
        <div class="form-group col-md-2 col-lg-2 mb-1">
            <label class="small text-muted mb-25 d-block" for="pl_to">To</label>
            <input type="date" name="pl_to" id="pl_to" class="form-control" value="{{ $plTo ?? '' }}">
        </div>
        <div class="form-group col-md-2 col-lg-2 mb-1">
            <label class="small text-muted mb-25 d-block" for="pl_posting">Posting</label>
            <select name="pl_posting" id="pl_posting" class="form-control">
                <option value="all" @selected(($plPosting ?? 'all') === 'all')>All</option>
                <option value="posted" @selected(($plPosting ?? '') === 'posted')>Posted only</option>
                <option value="pending" @selected(($plPosting ?? '') === 'pending')>Pending only</option>
            </select>
        </div>
        <div class="form-group col-md-2 col-lg-2 mb-1">
            <button type="submit" class="btn btn-primary btn-block">Run report</button>
        </div>
    </div>
</form>

@if($plDateError ?? false)
    <div class="alert alert-danger">{{ $plDateError }}</div>
@elseif(is_array($plReport) && ! empty($plReport['error']))
    <div class="alert alert-danger">{{ $plReport['error'] }}</div>
@elseif(! ($plCarId ?? null))
    <p class="text-muted mb-0">Select a vehicle registration to view profit/loss, transaction history, and totals.</p>
@elseif($plReportReady ?? false)
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
        <p class="mb-0 text-muted">
            <strong>{{ $plCar?->registration }}</strong>
            @if(filled($plFrom) || filled($plTo))
                — {{ filled($plFrom) ? \Carbon\Carbon::parse($plFrom)->format('d M Y') : 'Start' }}
                to {{ filled($plTo) ? \Carbon\Carbon::parse($plTo)->format('d M Y') : 'Present' }}
            @else
                — All dates
            @endif
        </p>
        <div class="btn-group btn-group-sm mt-25 mt-md-0">
            @php
                $exportQuery = array_filter([
                    'pl_car_id' => $plCarId,
                    'pl_from' => $plFrom,
                    'pl_to' => $plTo,
                    'pl_posting' => $plPosting,
                ], fn ($v) => $v !== null && $v !== '');
            @endphp
            <a class="btn btn-outline-primary" href="{{ route('reports.index', array_merge($exportQuery, ['export' => 'pl_csv'])) }}">
                Export CSV (Excel)
            </a>
            <a class="btn btn-outline-primary" href="{{ route('reports.index', array_merge($exportQuery, ['export' => 'pl_pdf'])) }}">
                Export PDF
            </a>
        </div>
    </div>

    <p class="small text-muted">
        Rental and damage show <strong>invoiced</strong> and <strong>collected</strong> separately. Headline net uses collected income minus expenses.
        MOT/PHV records and expenses may both appear if entered twice.
    </p>

    <div class="row mb-2">
        <div class="col-lg-6 mb-1">
            <div class="card h-100">
                <div class="card-header py-1" style="position:static; width:100%;"><strong>Total income</strong></div>
                <div class="card-body py-1">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td>Rental (invoiced)</td><td class="text-right">£{{ number_format($summary['rental_invoiced'] ?? 0, 2) }}</td></tr>
                        <tr><td>Rental (collected)</td><td class="text-right">£{{ number_format($summary['rental_collected'] ?? 0, 2) }}</td></tr>
                        <tr><td>Claims / damage / excess (invoiced)</td><td class="text-right">£{{ number_format($summary['damage_invoiced'] ?? 0, 2) }}</td></tr>
                        <tr><td>Claims / damage / excess (collected)</td><td class="text-right">£{{ number_format($summary['damage_collected'] ?? 0, 2) }}</td></tr>
                        <tr><td>Sale income</td><td class="text-right">£{{ number_format($summary['sale_income'] ?? 0, 2) }}</td></tr>
                        <tr><td>Other income</td><td class="text-right">£{{ number_format($summary['other_income'] ?? 0, 2) }}</td></tr>
                        <tr class="border-top"><td><strong>Total collected income</strong></td><td class="text-right"><strong>£{{ number_format($summary['total_income_collected'] ?? 0, 2) }}</strong></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-1">
            <div class="card h-100">
                <div class="card-header py-1" style="position:static; width:100%;"><strong>Total expenses</strong></div>
                <div class="card-body py-1">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td>Purchase cost</td><td class="text-right">£{{ number_format($summary['purchase'] ?? 0, 2) }}</td></tr>
                        <tr><td>Insurance</td><td class="text-right">£{{ number_format($summary['insurance'] ?? 0, 2) }}</td></tr>
                        <tr><td>MOT &amp; PHV licence</td><td class="text-right">£{{ number_format($summary['mot_phv'] ?? 0, 2) }}</td></tr>
                        <tr><td>Tax</td><td class="text-right">£{{ number_format($summary['tax'] ?? 0, 2) }}</td></tr>
                        <tr><td>Repairs, servicing, tyres &amp; parts</td><td class="text-right">£{{ number_format($summary['repairs'] ?? 0, 2) }}</td></tr>
                        <tr><td>Recovery / storage</td><td class="text-right">£{{ number_format($summary['recovery'] ?? 0, 2) }}</td></tr>
                        <tr><td>Other expenses</td><td class="text-right">£{{ number_format($summary['other_expense'] ?? 0, 2) }}</td></tr>
                        <tr class="border-top"><td><strong>Total expenses</strong></td><td class="text-right"><strong>£{{ number_format($summary['total_expenses'] ?? 0, 2) }}</strong></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-2">
        <div class="col-md-6">
            <div class="alert {{ ($summary['net_profit_collected'] ?? 0) >= 0 ? 'alert-success' : 'alert-danger' }} mb-0 py-1">
                <strong>Net profit/loss (collected income − expenses):</strong>
                £{{ number_format($summary['net_profit_collected'] ?? 0, 2) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-info mb-0 py-1">
                <strong>Net (invoiced rent/damage + sale/other − expenses):</strong>
                £{{ number_format($summary['net_profit_invoiced'] ?? 0, 2) }}
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="vehiclePlLedgerTable">
            <thead>
            <tr>
                <th>Date</th>
                <th>In / Out</th>
                <th>Category</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
                <th>Status</th>
                <th>Source</th>
            </tr>
            </thead>
            <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ filled($line['date'] ?? null) ? \Carbon\Carbon::parse($line['date'])->format('d M Y') : '—' }}</td>
                    <td>{{ ($line['direction'] ?? '') === 'in' ? 'In' : 'Out' }}</td>
                    <td>{{ $line['category_label'] ?? '' }}</td>
                    <td>{{ $line['description'] ?? '' }}</td>
                    <td class="text-right">£{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
                    <td>
                        @php $ps = $line['posting_status'] ?? null; @endphp
                        @if($ps === 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @elseif($ps === 'posted')
                            <span class="badge badge-success">Posted</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $line['source_label'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-3">No transactions in this period.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif
