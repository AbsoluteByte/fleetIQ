<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaymentIndexService
{
    public function baseQuery(int $tenantId): Builder
    {
        return Driver::query()
            ->where('tenant_id', $tenantId)
            ->withPaymentIndexAggregates()
            ->withMax(['payments as last_posted_payment_date' => function ($query) {
                $query->posted();
            }], 'payment_date')
            ->withMax('invoices as latest_invoice_date', 'invoice_date')
            ->withCount(['invoices', 'payments'])
            ->with(['agreements' => fn ($query) => $query->currentlyActive()->with([
                'car',
                'paymentBankAccount',
                'replacementVehicleAgreements' => fn ($replacementQuery) => $replacementQuery
                    ->currentlyActiveReplacement()
                    ->with('car'),
            ])])
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($status = $request->input('filter_driver_status')) {
            $query->where('is_active', $status === 'active');
        }

        $this->applyDateTimeRange($query, 'payment_remind_at', $request->input('filter_reminder_from'), $request->input('filter_reminder_to'));
        $this->applyDateRangeHaving($query, 'last_posted_payment_date', $request->input('filter_last_payment_from'), $request->input('filter_last_payment_to'));
        $this->applyDateRangeHaving($query, 'latest_invoice_date', $request->input('filter_latest_invoice_from'), $request->input('filter_latest_invoice_to'));

        return $query;
    }

    public function applySearchKeyword(Builder $query, string $keyword): Builder
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($keyword) {
            $inner->where(function (Builder $namePostcode) use ($keyword) {
                DriverKeywordSearch::applyNameAndPostcodeMatch($namePostcode, $keyword);
            })
                ->orWhere('phone_number', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhereHas('agreements', function (Builder $agreementQuery) use ($keyword) {
                    $agreementQuery->currentlyActive()->where(function (Builder $vehicleMatch) use ($keyword) {
                        $vehicleMatch->whereHas('car', fn (Builder $car) => $car->where('registration', 'like', "%{$keyword}%"))
                            ->orWhereHas('replacementVehicleAgreements', function (Builder $replacement) use ($keyword) {
                                $replacement->currentlyActiveReplacement()
                                    ->whereHas('car', fn (Builder $car) => $car->where('registration', 'like', "%{$keyword}%"));
                            });
                    });
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rowPayload(Driver $driver): array
    {
        $lastPaymentIso = $driver->last_posted_payment_date
            ? \Carbon\Carbon::parse($driver->last_posted_payment_date)->format('Y-m-d')
            : '';
        $latestInvoiceIso = $driver->latest_invoice_date
            ? \Carbon\Carbon::parse($driver->latest_invoice_date)->format('Y-m-d')
            : '';
        $pendingDfsAmount = (float) ($driver->pending_dfs_amount ?? 0);
        $totalPaidPosted = (float) ($driver->total_paid ?? 0);
        $registrations = $driver->agreements
            ->flatMap(fn ($agreement) => $agreement->vehicleRegistrationsIncludingReplacements())
            ->unique()
            ->values();
        $payToBank = $driver->agreements
            ->map(fn ($agreement) => $agreement->paymentBankAccount?->paymentDisplayName())
            ->filter()
            ->unique()
            ->values();
        $hasPendingDfs = $driver->total_due > 0 && $pendingDfsAmount > 0;
        $totalDueClass = $driver->total_due > 0
            ? ($hasPendingDfs ? 'text-warning' : 'text-danger')
            : 'text-muted';
        $pendingDfsTooltip = $hasPendingDfs
            ? '£'.number_format($pendingDfsAmount, 2).' pending daily financial sheet approval.'
            : null;
        $dfsExportStatus = $pendingDfsAmount > 0
            ? 'pending'
            : ($totalPaidPosted > 0 ? 'posted' : '');

        return [
            'driver' => $this->driverHtml($driver),
            'paying_company' => $driver->primaryPayingCompanyName(),
            'vehicle' => $registrations->isNotEmpty() ? $registrations->implode(', ') : '—',
            'pay_to' => $payToBank->isNotEmpty() ? $payToBank->implode(', ') : '—',
            'phone' => $driver->phone_number ?? 'N/A',
            'invoices_count' => (int) $driver->invoices_count,
            'payments_count' => (int) $driver->payments_count,
            'payment_due' => $latestInvoiceIso ? \Carbon\Carbon::parse($latestInvoiceIso)->format('d M Y') : '—',
            'last_payment' => $lastPaymentIso ? \Carbon\Carbon::parse($lastPaymentIso)->format('d M Y') : '—',
            'total_due_html' => $this->totalDueHtml($driver, $totalDueClass, $hasPendingDfs, $pendingDfsTooltip),
            'credit_html' => $this->creditHtml($driver),
            'actions_html' => $this->actionsHtml($driver),
            'filter_driver_status' => $driver->is_active ? 'active' : 'inactive',
            'filter_remind_at' => $driver->payment_remind_at?->toIso8601String() ?? '',
            'filter_last_payment_date' => $lastPaymentIso,
            'filter_latest_invoice_date' => $latestInvoiceIso,
            'filter_dfs_export_status' => $dfsExportStatus,
            'export_total_due' => '£'.number_format((float) $driver->total_due, 2),
            'export_credit' => '£'.number_format((float) $driver->credit_amount, 2),
        ];
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @return list<array<string, mixed>>
     */
    public function rowsForExport(Collection $drivers): array
    {
        return $drivers->map(fn (Driver $driver) => $this->rowPayload($driver))->values()->all();
    }

    private function totalDueHtml(Driver $driver, string $class, bool $hasPendingDfs, ?string $tooltip): string
    {
        $amount = '£'.number_format((float) $driver->total_due, 2);
        $tooltipAttr = $tooltip
            ? ' data-toggle="tooltip" data-placement="top" title="'.e($tooltip).'"'
            : '';
        $extraClass = $hasPendingDfs ? ' js-dfs-pending-amount' : '';

        return '<strong class="'.$class.$extraClass.'"'.$tooltipAttr.'>'.$amount.'</strong>';
    }

    private function creditHtml(Driver $driver): string
    {
        $class = $driver->credit_amount > 0 ? 'text-success' : 'text-muted';

        return '<strong class="'.$class.'">£'.number_format((float) $driver->credit_amount, 2).'</strong>';
    }

    private function driverHtml(Driver $driver): string
    {
        $label = e($driver->selectOptionLabel() ?: 'N/A');
        $html = '<strong>'.$label.'</strong>';

        if ($payingCompany = $driver->primaryPayingCompanyName()) {
            $html .= '<br><span class="paying-company-subtitle d-block">Pays via: '.e($payingCompany).'</span>';
        }

        return $html;
    }

    private function actionsHtml(Driver $driver): string
    {
        $showUrl = e(route('payments.driver', $driver));
        $createUrl = e(route('payments.create', ['driver_id' => $driver->id]));
        $updateUrl = e(route('payments.follow-up.update', $driver));
        $driverName = e($driver->selectOptionLabel() ?: trim($driver->first_name.' '.$driver->last_name));
        $hasFollowUp = $driver->hasPaymentFollowUpNote() || $driver->hasPaymentReminder();
        $followUpClass = $hasFollowUp ? 'btn-warning' : 'btn-outline-secondary';

        return '<div class="btn-group" role="group">'
            .'<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info js-action-tooltip" data-toggle="tooltip" title="View Driver Payments"><i class="fa fa-eye"></i></a>'
            .'<a href="'.$createUrl.'" class="btn btn-sm btn-outline-primary js-action-tooltip" data-toggle="tooltip" title="Add Payment"><i class="fa fa-plus"></i></a>'
            .'<button type="button" class="btn btn-sm '.$followUpClass.' js-action-tooltip js-driver-follow-up" data-toggle="tooltip" title="Notes/Reminder"'
            .' data-driver-id="'.$driver->id.'" data-driver-name="'.$driverName.'"'
            .' data-notes="'.e($driver->payment_follow_up_notes ?? '').'"'
            .' data-remind-at="'.e($driver->payment_remind_at?->toIso8601String() ?? '').'"'
            .' data-update-url="'.$updateUrl.'"><i class="fa fa-sticky-note"></i></button>'
            .'</div>';
    }

    private function applyDateTimeRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where($column, '>=', $this->normalizeDateTimeFilter($from));
        }

        if ($to) {
            $query->where($column, '<=', $this->normalizeDateTimeFilter($to, endOfMinute: true));
        }
    }

    private function normalizeDateTimeFilter(string $value, bool $endOfMinute = false): string
    {
        $normalized = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            return $endOfMinute
                ? $normalized.' 23:59:59'
                : $normalized.' 00:00:00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            return $endOfMinute
                ? $normalized.':59'
                : $normalized.':00';
        }

        return $normalized;
    }

    private function applyDateRangeHaving(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($column === 'last_posted_payment_date') {
            if ($from && $to) {
                $query->whereRaw(
                    'date((select max(payment_date) from payments where payments.driver_id = drivers.id and posting_status = ?)) between date(?) and date(?)',
                    [Payment::POSTING_STATUS_POSTED, $from, $to]
                );
            } elseif ($from) {
                $query->whereRaw(
                    'date((select max(payment_date) from payments where payments.driver_id = drivers.id and posting_status = ?)) >= date(?)',
                    [Payment::POSTING_STATUS_POSTED, $from]
                );
            } elseif ($to) {
                $query->whereRaw(
                    'date((select max(payment_date) from payments where payments.driver_id = drivers.id and posting_status = ?)) <= date(?)',
                    [Payment::POSTING_STATUS_POSTED, $to]
                );
            }

            return;
        }

        if ($column === 'latest_invoice_date') {
            if ($from && $to) {
                $query->whereRaw(
                    'date((select max(invoice_date) from invoices where invoices.driver_id = drivers.id)) between date(?) and date(?)',
                    [$from, $to]
                );
            } elseif ($from) {
                $query->whereRaw(
                    'date((select max(invoice_date) from invoices where invoices.driver_id = drivers.id)) >= date(?)',
                    [$from]
                );
            } elseif ($to) {
                $query->whereRaw(
                    'date((select max(invoice_date) from invoices where invoices.driver_id = drivers.id)) <= date(?)',
                    [$to]
                );
            }
        }
    }
}
