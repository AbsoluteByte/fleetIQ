<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\DepositRefund;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgreementIndexService
{
    /**
     * @return list<string>
     */
    public function filterStatusNames(int $tenantId): array
    {
        return Status::query()
            ->where('type', 'agreement')
            ->whereHas('agreements', fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function baseQuery(int $tenantId): Builder
    {
        return Agreement::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'company',
                'driver',
                'car',
                'status',
                'parentAgreement',
                'depositRefund',
                'upgradedToAgreement',
                'renewedToAgreement',
            ]);
    }

    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($status = trim((string) $request->input('filter_status'))) {
            $query->whereHas('status', fn (Builder $inner) => $inner->where('name', $status));
        }

        if ($request->boolean('filter_has_notice')) {
            $query->whereHas('status', fn (Builder $inner) => $inner->whereIn('name', ['Active', 'Swap']))
                ->whereNotNull('termination_notice_date');
        }

        $this->applyDateRangeFilter($query, 'start_date', $request->input('filter_rented_from'), $request->input('filter_rented_to'));

        if ($request->filled('filter_closed_from') || $request->filled('filter_closed_to')) {
            $from = $request->input('filter_closed_from');
            $to = $request->input('filter_closed_to') ?: now()->toDateString();

            $query->whereHas('status', fn (Builder $inner) => $inner->where('name', 'Terminated'))
                ->where(function (Builder $inner) use ($from, $to) {
                    $inner->where(function (Builder $closed) use ($from, $to) {
                        $closed->whereNotNull('closing_date')
                            ->whereDate('closing_date', '>=', $from)
                            ->whereDate('closing_date', '<=', $to);
                    })->orWhere(function (Builder $ended) use ($from, $to) {
                        $ended->whereNull('closing_date')
                            ->whereDate('end_date', '>=', $from)
                            ->whereDate('end_date', '<=', $to);
                    });
                });
        }

        if ($request->filled('filter_expired_from') || $request->filled('filter_expired_to')) {
            $query->whereHas('status', fn (Builder $inner) => $inner->where('name', 'Expired'))
                ->whereDate('end_date', '>=', $request->input('filter_expired_from'))
                ->when($request->filled('filter_expired_to'), fn (Builder $inner) => $inner->whereDate('end_date', '<=', $request->input('filter_expired_to')));
        }

        $this->applyDateRangeFilter(
            $query,
            'termination_notice_date',
            $request->input('filter_notice_from'),
            $request->input('filter_notice_to'),
            billableOnly: true
        );

        if ($request->filled('filter_due_from') || $request->filled('filter_due_to')) {
            $from = $request->input('filter_due_from');
            $to = $request->input('filter_due_to');

            $query->where(function (Builder $inner) use ($from, $to) {
                $inner->where(function (Builder $endDate) use ($from, $to) {
                    $this->applyDateRangeFilter($endDate, 'end_date', $from, $to);
                })->orWhere(function (Builder $noticeDate) use ($from, $to) {
                    $noticeDate->whereHas('status', fn (Builder $status) => $status->whereIn('name', ['Active', 'Swap']))
                        ->whereNotNull('termination_notice_date');
                    $this->applyDateRangeFilter($noticeDate, 'termination_notice_date', $from, $to);
                });
            });
        }

        if ($refundStatus = trim((string) $request->input('filter_refund_status'))) {
            if ($refundStatus === 'refunded') {
                $query->whereHas('depositRefund');
            } elseif ($refundStatus === 'pending') {
                $query->whereHas('status', fn (Builder $inner) => $inner->where('name', 'Terminated'))
                    ->where('deposit_amount', '>', 0)
                    ->whereDoesntHave('depositRefund')
                    ->whereDoesntHave('upgradedToAgreement')
                    ->whereDoesntHave('renewedToAgreement');
            }
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function rowPayload(Agreement $agreement, AgreementUpgradeService $upgradeService): array
    {
        $statusName = (string) optional($agreement->status)->name;
        $statusLower = strtolower($statusName);
        $isBillable = in_array($statusLower, ['active', 'swap'], true);
        $noticeIso = ($isBillable && $agreement->termination_notice_date)
            ? $agreement->termination_notice_date->format('Y-m-d')
            : '';
        $refundStatus = $agreement->depositRefundStatus();
        $showRefundBtn = $refundStatus !== null || $agreement->canRequestDepositRefund();
        $filterRefundStatus = $agreement->depositRefund
            ? 'refunded'
            : (
                $agreement->isClosedForDepositRefund() && (float) $agreement->deposit_amount > 0
                    ? 'pending'
                    : ''
            );

        return [
            'id' => $agreement->id,
            'company' => $agreement->company?->name ?? '—',
            'driver' => $this->driverHtml($agreement),
            'driver_post_code' => $agreement->driver?->post_code ?? '',
            'paying_company_name' => $agreement->paying_company_name,
            'car' => $agreement->car?->registration ?? '—',
            'start_date' => optional($agreement->start_date)->format('M d, Y') ?: '—',
            'end_date' => optional($agreement->end_date)->format('M d, Y') ?: '—',
            'notice_date' => $agreement->termination_notice_date ? $agreement->termination_notice_date->format('M d, Y') : '—',
            'closing_date' => $agreement->closing_date ? $agreement->closing_date->format('M d, Y') : '—',
            'rent' => $agreement->isReplacementVehicle()
                ? 'Replacement'
                : '£'.number_format((float) $agreement->agreed_rent, 2),
            'esign_html' => $this->esignHtml($agreement),
            'status_html' => $this->statusHtml($agreement, $statusName),
            'actions_html' => $this->actionsHtml($agreement, $upgradeService, $refundStatus, $showRefundBtn),
            'filter_start_date' => optional($agreement->start_date)->format('Y-m-d') ?? '',
            'filter_end_date' => optional($agreement->end_date)->format('Y-m-d') ?? '',
            'filter_closing_date' => optional($agreement->closing_date)->format('Y-m-d') ?? '',
            'filter_notice_date' => $noticeIso,
            'filter_is_billable' => $isBillable ? '1' : '0',
            'filter_closed_on' => optional($agreement->effectiveCloseDate())->format('Y-m-d') ?? '',
            'filter_status' => $statusName,
            'filter_refund_status' => $filterRefundStatus,
            'export_esign' => $agreement->hellosign_status ? ucfirst((string) $agreement->hellosign_status) : 'Not Sent',
            'export_status' => $statusName,
        ];
    }

    /**
     * @param  Collection<int, Agreement>  $agreements
     * @return list<array<string, mixed>>
     */
    public function rowsForExport(Collection $agreements, AgreementUpgradeService $upgradeService): array
    {
        return $agreements
            ->map(fn (Agreement $agreement) => $this->rowPayload($agreement, $upgradeService))
            ->values()
            ->all();
    }

    private function esignHtml(Agreement $agreement): string
    {
        if (! $agreement->hellosign_status) {
            return '<span class="badge bg-light text-dark">Not Sent</span>';
        }

        $badge = e($agreement->esign_status_badge);
        $label = e(ucfirst((string) $agreement->hellosign_status));
        $html = '<span class="badge '.$badge.'">'.$label.'</span>';

        if ($agreement->hellosign_status === 'signed' && $agreement->esign_document_path) {
            $url = e(route('agreements.view-signed', ['agreement' => $agreement, 'download' => 1]));

            $html .= '<br><a href="'.$url.'" class="btn btn-sm btn-success mt-1" title="Download Signed Document"><i class="fa fa-download"></i></a>';
        }

        return $html;
    }

    private function statusHtml(Agreement $agreement, string $statusName): string
    {
        $color = e((string) ($agreement->status?->color ?? '#7367f0'));
        $html = '<span class="badge" style="background-color: '.$color.'">'.e($statusName).'</span>';

        if ($agreement->isReplacementVehicle() && $agreement->parentAgreement) {
            $url = e(route('agreements.show', $agreement->parentAgreement));
            $html .= '<br><small class="text-muted">Original: <a href="'.$url.'">#'.$agreement->parentAgreement->id.'</a></small>';
        }

        return $html;
    }

    private function actionsHtml(
        Agreement $agreement,
        AgreementUpgradeService $upgradeService,
        ?string $refundStatus,
        bool $showRefundBtn
    ): string {
        $showUrl = e(route('agreements.show', $agreement));
        $editUrl = e(route('agreements.edit', $agreement));
        $pdfUrl = e(route('agreements.pdf', $agreement));
        $destroyUrl = e(route('agreements.destroy', $agreement));
        $csrf = e(csrf_token());

        $html = '<div class="btn-group" role="group">';
        $html .= '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info js-action-tooltip" data-toggle="tooltip" title="View Agreement"><i class="fa fa-eye"></i></a>';
        $html .= '<a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning js-action-tooltip" data-toggle="tooltip" title="Edit Agreement"><i class="fa fa-edit"></i></a>';

        if ($upgradeService->canRenew($agreement)) {
            $renewUrl = e(route('agreements.renew', $agreement));
            $html .= '<a href="'.$renewUrl.'" class="btn btn-sm btn-outline-primary js-action-tooltip" data-toggle="tooltip" title="Renew Agreement"><i class="fa fa-refresh"></i></a>';
        }

        if ($showRefundBtn) {
            if ($refundStatus === DepositRefund::POSTING_STATUS_PENDING) {
                $html .= '<span class="d-inline-flex js-action-tooltip" data-toggle="tooltip" title="Deposit Refund Pending Daily Financial Sheet Approval"><button type="button" class="btn btn-sm btn-outline-secondary" disabled style="opacity:.45;"><i class="fa fa-undo"></i></button></span>';
            } elseif ($refundStatus === DepositRefund::POSTING_STATUS_POSTED) {
                $html .= '<span class="d-inline-flex js-action-tooltip" data-toggle="tooltip" title="Deposit Already Refunded"><button type="button" class="btn btn-sm btn-outline-secondary" disabled style="opacity:.45;"><i class="fa fa-undo"></i></button></span>';
            } else {
                $previewUrl = e(route('agreements.deposit-settlement-preview', $agreement));
                $refundUrl = e(route('agreements.refund-deposit', $agreement));
                $html .= '<span class="d-inline-flex js-action-tooltip" data-toggle="tooltip" title="Refund Deposit"><button type="button" class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#refundDepositModal" data-refund-deposit-btn data-preview-url="'.$previewUrl.'" data-action="'.$refundUrl.'"><i class="fa fa-undo"></i></button></span>';
            }
        }

        $html .= '<a href="'.$pdfUrl.'" class="btn btn-sm btn-outline-danger js-action-tooltip" target="_blank" data-toggle="tooltip" title="Generate PDF"><i class="fa fa-file-pdf-o"></i></a>';
        $html .= '<form action="'.$destroyUrl.'" method="POST" style="display:inline;"><input type="hidden" name="_token" value="'.$csrf.'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn btn-sm btn-outline-danger js-action-tooltip" data-toggle="tooltip" title="Delete Agreement" onclick="return confirm(\'Are you sure?\')"><i class="fa fa-trash"></i></button></form>';
        $html .= '</div>';

        return $html;
    }

    private function driverHtml(Agreement $agreement): string
    {
        $name = e($agreement->driver?->full_name ?? '—');
        $html = '<strong>'.$name.'</strong>';

        if ($agreement->paying_company_name) {
            $html .= '<br><span class="text-muted">Pays via: '.e($agreement->paying_company_name).'</span>';
        }

        if ($agreement->driver?->post_code) {
            $html .= '<br><span>Post Code: '.e($agreement->driver->post_code).'</span>';
        }

        return $html;
    }

    private function applyDateRangeFilter(
        Builder $query,
        string $column,
        ?string $from,
        ?string $to,
        bool $billableOnly = false
    ): void {
        if (! $from && ! $to) {
            return;
        }

        if ($billableOnly) {
            $query->whereHas('status', fn (Builder $inner) => $inner->whereIn('name', ['Active', 'Swap']));
        }

        if ($from) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }
}
