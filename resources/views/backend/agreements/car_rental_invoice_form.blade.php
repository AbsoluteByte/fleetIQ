@extends('layouts.admin', ['title' => 'Car Rental Invoice'])

@section('content')
    <div class="mb-3">
        <a href="{{ route('agreements.show', $agreement) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back to agreement
        </a>
    </div>

    <h1 class="h3 mb-3">
        <i class="fa fa-file-text-o me-2"></i>
        Car rental invoice — Agreement #{{ $agreement->id }}
    </h1>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($existingRecord)
        <div class="alert alert-info">
            Last generated invoice no. <strong>{{ $existingRecord->displaySerial() }}</strong>
            ({{ $existingRecord->updated_at?->format('d M Y H:i') }}).
            Regenerating keeps the same serial and updates the breakdown.
        </div>
    @endif

    @if($eligiblePayments->isEmpty())
        <div class="alert alert-warning">
            No posted rent payments are allocated to this agreement yet. Record and allocate payments before generating an invoice.
        </div>
    @else
        <form method="POST" action="{{ route('agreements.car-rental-invoice.store', $agreement) }}" target="_blank">
            @csrf

            <div class="card mb-3">
                <div class="card-header" style="position:static; width:100%;"><strong>Invoice details</strong></div>
                <div class="card-body row g-3 mt-0">
                    <div class="col-md-4">
                        <label class="form-label">Invoice date</label>
                        <input type="date" name="invoice_date" class="form-control" required
                               value="{{ old('invoice_date', $existingRecord?->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Weekly insurance amount (£)</label>
                        <input type="number" step="0.01" min="0" name="weekly_insurance_amount" class="form-control" required
                               value="{{ old('weekly_insurance_amount', $existingRecord?->weekly_insurance_amount ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">VAT rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="vat_rate" class="form-control" required
                               value="{{ old('vat_rate', $existingRecord?->vat_rate ?? 20) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">VAT registration number</label>
                        <input type="text" name="vat_registration_number" class="form-control" maxlength="64"
                               value="{{ old('vat_registration_number', $existingRecord?->vat_registration_number ?? '') }}"
                               placeholder="e.g. 456 0701 06">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rental start (optional)</label>
                        <input type="date" name="rental_start_date" class="form-control"
                               value="{{ old('rental_start_date', $existingRecord?->rental_start_date?->format('Y-m-d') ?? '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rental end (optional)</label>
                        <input type="date" name="rental_end_date" class="form-control"
                               value="{{ old('rental_end_date', $existingRecord?->rental_end_date?->format('Y-m-d') ?? '') }}">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header" style="position:static; width:100%;"><strong>Payment filters</strong></div>
                <div class="card-body row g-3 align-items-end mt-0">
                    <div class="col-md-5">
                        <label class="form-label">Bank account (non-cash payments)</label>
                        <select name="bank_account_filter_id" id="bankAccountFilter" class="form-select">
                            <option value="">All bank / card accounts</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}"
                                    @selected((string) old('bank_account_filter_id', $bankAccountFilterId) === (string) $account->id)>
                                    {{ $account->paymentDisplayName() }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Changing filters updates the list below (bank/card by default; cash when included).</small>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="include_cash" value="1" id="includeCash"
                                   @checked(old('include_cash', $includeCash))>
                            <label class="form-check-label" for="includeCash">Include cash payments</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="applyPaymentFilters">Select all visible payments</button>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center" style="position:static; width:100%;">
                    <div><strong>Payments</strong></div>
                    <small class="text-muted">Weekly rate (discounted): £{{ number_format($agreement->discounted_rent, 2) }}</small>
                </div>
                <div class="card-body p-0 mt-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="selectAllPayments" checked></th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Bank</th>
                                    <th class="text-end">Rent allocated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($eligiblePayments as $payment)
                                    @php
                                        $selected = in_array($payment['payment_id'], old('payment_ids', $defaultSelectedPaymentIds), true);
                                    @endphp
                                    <tr data-method="{{ $payment['payment_method'] }}"
                                        data-bank-id="{{ $payment['bank_account_id'] ?? '' }}">
                                        <td>
                                            <input type="checkbox" name="payment_ids[]" value="{{ $payment['payment_id'] }}"
                                                   class="payment-checkbox" @checked($selected)>
                                        </td>
                                        <td>{{ $payment['payment_no'] ?? '#'.$payment['payment_id'] }}</td>
                                        <td>{{ $payment['payment_date'] ? \Carbon\Carbon::parse($payment['payment_date'])->format('d M Y') : '—' }}</td>
                                        <td>{{ $payment['payment_method'] }}</td>
                                        <td>{{ $payment['bank_display'] ?? '—' }}</td>
                                        <td class="text-end">£{{ number_format($payment['rent_allocated_amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr id="paymentsTableEmptyRow" style="display: none;">
                                    <td colspan="6" class="text-center text-muted py-3">
                                        No payments match the current filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-danger">
                <i class="fa fa-file-pdf-o me-1"></i> Generate PDF
            </button>
        </form>
    @endif
@endsection

@push('js')
<script>
(function () {
    const bankMethods = @json(\App\Models\Payment::METHODS_REQUIRING_BANK_ACCOUNT);
    const selectAll = document.getElementById('selectAllPayments');
    const bankAccountFilter = document.getElementById('bankAccountFilter');
    const includeCashInput = document.getElementById('includeCash');
    const paymentRows = document.querySelectorAll('#paymentsTable tbody tr[data-method]');

    function rowMatchesFilter(row) {
        const method = row.getAttribute('data-method') || '';
        const bankId = String(row.getAttribute('data-bank-id') || '');
        const includeCash = includeCashInput?.checked ?? false;
        const bankFilter = String(bankAccountFilter?.value || '');

        if (method === 'Cash') {
            return includeCash;
        }
        if (bankMethods.includes(method)) {
            return bankFilter === '' || bankId === bankFilter;
        }

        return false;
    }

    function visiblePaymentCheckboxes() {
        return Array.from(paymentRows)
            .filter(function (row) {
                return row.style.display !== 'none';
            })
            .map(function (row) {
                return row.querySelector('.payment-checkbox');
            })
            .filter(Boolean);
    }

    function syncSelectAllCheckbox() {
        const visible = visiblePaymentCheckboxes();
        if (!selectAll || visible.length === 0) {
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            return;
        }
        const checkedCount = visible.filter(function (cb) {
            return cb.checked;
        }).length;
        selectAll.checked = checkedCount === visible.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < visible.length;
    }

    function refreshPaymentListVisibility() {
        let visibleCount = 0;
        paymentRows.forEach(function (row) {
            if (row.id === 'paymentsTableEmptyRow') {
                return;
            }
            const show = rowMatchesFilter(row);
            row.style.display = show ? '' : 'none';
            const checkbox = row.querySelector('.payment-checkbox');
            if (checkbox) {
                checkbox.disabled = !show;
            }
            if (show) {
                visibleCount++;
            }
        });

        const emptyRow = document.getElementById('paymentsTableEmptyRow');
        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        syncSelectAllCheckbox();
    }

    function selectAllVisiblePayments() {
        paymentRows.forEach(function (row) {
            if (row.style.display === 'none') {
                return;
            }
            const checkbox = row.querySelector('.payment-checkbox');
            if (checkbox) {
                checkbox.checked = true;
            }
        });
        syncSelectAllCheckbox();
    }

    bankAccountFilter?.addEventListener('change', refreshPaymentListVisibility);
    includeCashInput?.addEventListener('change', refreshPaymentListVisibility);

    document.getElementById('applyPaymentFilters')?.addEventListener('click', selectAllVisiblePayments);

    selectAll?.addEventListener('change', function () {
        const checked = this.checked;
        visiblePaymentCheckboxes().forEach(function (cb) {
            cb.checked = checked;
        });
        this.indeterminate = false;
    });

    paymentRows.forEach(function (row) {
        const cb = row.querySelector('.payment-checkbox');
        cb?.addEventListener('change', syncSelectAllCheckbox);
    });

    refreshPaymentListVisibility();
})();
</script>
@endpush
