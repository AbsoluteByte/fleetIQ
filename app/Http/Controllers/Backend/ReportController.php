<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarMot;
use App\Models\CarPhv;
use App\Models\Company;
use App\Models\InsuranceProvider;
use App\Services\InsuranceDateRangeReportService;
use App\Services\InsuranceReconciliationService;
use App\Services\TicketTrackingReportService;
use App\Services\VehicleProfitLossReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PDF;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected $dir = 'backend.reports.';

    public function __construct(
        private readonly InsuranceDateRangeReportService $insuranceReportService,
        private readonly TicketTrackingReportService $ticketTrackingService,
        private readonly InsuranceReconciliationService $insuranceReconciliationService,
        private readonly VehicleProfitLossReportService $vehicleProfitLossReportService,
    ) {
        $this->middleware('role:admin|manager|user');
        view()->share('dir', $this->dir);
    }

    public function index(Request $request)
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->route('dashboard')
                ->with('error', 'No active company found! Please contact administrator.');
        }

        $data = $this->reportsIndexData($request, $tenant->id) + [
            'reconciliation' => null,
            'reconciliationError' => null,
            'reconciliationFrom' => null,
            'reconciliationTo' => null,
        ];

        $export = (string) $request->query('export', '');
        $vehicleReport = $data['vehicleProfitLossReport'] ?? null;

        if ($export === 'pl_csv' && is_array($vehicleReport) && empty($vehicleReport['error'])) {
            return $this->vehicleProfitLossCsvResponse($vehicleReport);
        }

        if ($export === 'pl_pdf' && is_array($vehicleReport) && empty($vehicleReport['error'])) {
            return $this->vehicleProfitLossPdfResponse($vehicleReport);
        }

        return view($this->dir.'index', $data);
    }

    public function runInsuranceReconciliation(Request $request)
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->route('dashboard')
                ->with('error', 'No active company found! Please contact administrator.');
        }

        $validated = $request->validate([
            'reconciliation_from' => 'required|date',
            'reconciliation_to' => 'required|date|after_or_equal:reconciliation_from',
            'policy_schedule_pdf' => 'required|file|mimes:pdf|max:51200',
        ]);

        $from = Carbon::parse($validated['reconciliation_from'])->startOfDay();
        $to = Carbon::parse($validated['reconciliation_to'])->startOfDay();
        $uploaded = $request->file('policy_schedule_pdf');
        $reconciliation = null;
        $reconciliationError = null;

        try {
            $reconciliation = $this->insuranceReconciliationService->reconcile(
                $tenant->id,
                $from,
                $to,
                $uploaded->getRealPath()
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $reconciliationError = $e->getMessage() ?: 'Unable to reconcile the uploaded policy schedule.';
        }

        return view($this->dir.'index', $this->reportsIndexData($request, $tenant->id) + [
            'reconciliation' => $reconciliation,
            'reconciliationError' => $reconciliationError,
            'reconciliationFrom' => $from->toDateString(),
            'reconciliationTo' => $to->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportsIndexData(Request $request, int $tenantId): array
    {
        $cars = Car::where('tenant_id', $tenantId)
            ->with([
                'company',
                'carModel',
                'phvs.counsel',
                'insurances.status',
                'mots',
            ])
            ->latest()
            ->get();

        $cars = $cars->map(function (Car $car) {
            $latestMot = $this->latestMotForCar($car);
            $motExpiry = $latestMot?->expiry_date;
            $car->report_latest_mot = $latestMot;
            $car->report_mot_expiry = $motExpiry;
            $car->report_mot_missing = ! $motExpiry;
            $car->report_mot_status = $this->expiryStatusLabel($motExpiry);

            $latestPhv = $this->latestPhvForCar($car);
            $phvExpiry = $latestPhv?->expiry_date;
            $car->report_latest_phv = $latestPhv;
            $car->report_phv_expiry = $phvExpiry;
            $car->report_phv_missing = ! $phvExpiry;
            $car->report_phv_status = $this->expiryStatusLabel($phvExpiry);

            return $car;
        });

        $insuranceFrom = $request->query('insurance_from');
        $insuranceTo = $request->query('insurance_to');
        $insuranceCompanyId = $request->filled('insurance_company_id')
            ? (int) $request->query('insurance_company_id')
            : null;
        $insuranceProviderId = $request->filled('insurance_provider_id')
            ? (int) $request->query('insurance_provider_id')
            : null;
        $insuranceDateError = null;
        $insuranceRemovedInRange = collect();
        $insuranceActivatedInRange = collect();
        $insuranceActivatedOrRemovedInRange = collect();
        $insuranceActiveOnInsurance = collect();

        $reportCompanies = Company::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $reportInsuranceProviders = InsuranceProvider::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('provider_name')
            ->get(['id', 'provider_name', 'expiry_date']);

        if ($insuranceCompanyId && ! $reportCompanies->contains('id', $insuranceCompanyId)) {
            $insuranceCompanyId = null;
        }

        if ($insuranceProviderId && ! $reportInsuranceProviders->contains('id', $insuranceProviderId)) {
            $insuranceProviderId = null;
        }

        if ($insuranceFrom !== null || $insuranceTo !== null) {
            $parsedRange = $this->insuranceReportService->parseDateRange($insuranceFrom, $insuranceTo);

            if ($parsedRange === null) {
                $insuranceDateError = 'Please select a valid date range (From must be on or before To).';
            } else {
                [$from, $to] = $parsedRange;
                $insuranceRemovedInRange = $this->insuranceReportService->removedInRange(
                    $tenantId, $from, $to, $insuranceCompanyId, $insuranceProviderId
                );
                $insuranceActivatedInRange = $this->insuranceReportService->activatedInRange(
                    $tenantId, $from, $to, $insuranceCompanyId, $insuranceProviderId
                );
                $insuranceActivatedOrRemovedInRange = $this->insuranceReportService->activatedOrRemovedInRange(
                    $tenantId, $from, $to, $insuranceCompanyId, $insuranceProviderId
                );
                $insuranceActiveOnInsurance = $this->insuranceReportService->activeOnInsurance(
                    $tenantId, $insuranceCompanyId, $insuranceProviderId
                );
            }
        }

        $selectedInsuranceCompany = $insuranceCompanyId
            ? $reportCompanies->firstWhere('id', $insuranceCompanyId)
            : null;
        $selectedInsuranceProvider = $insuranceProviderId
            ? $reportInsuranceProviders->firstWhere('id', $insuranceProviderId)
            : null;

        $ticketCarId = $request->filled('ticket_car_id')
            ? (int) $request->query('ticket_car_id')
            : null;
        $ticketAt = $request->query('ticket_at');
        $ticketTrackingError = null;
        $ticketTrackingResult = null;
        $ticketTrackingReady = false;
        $ticketTrackingQueriedAt = null;
        $ticketCars = $cars->sortBy('registration')->values();

        if ($ticketCarId !== null || $ticketAt !== null) {
            if (! $ticketCarId || ! filled($ticketAt)) {
                $ticketTrackingError = 'Please select a vehicle and date & time.';
            } else {
                try {
                    $parsedAt = Carbon::parse($ticketAt);
                } catch (\Throwable) {
                    $parsedAt = null;
                    $ticketTrackingError = 'Please enter a valid date and time.';
                }

                if ($parsedAt && ! $ticketCars->contains('id', $ticketCarId)) {
                    $ticketTrackingError = 'The selected vehicle is not valid.';
                } elseif ($parsedAt) {
                    $ticketTrackingResult = $this->ticketTrackingService->findAssignment(
                        $tenantId,
                        $ticketCarId,
                        $parsedAt
                    );
                    $ticketTrackingQueriedAt = $parsedAt;
                    $ticketTrackingReady = true;
                }
            }
        }

        $plCarId = $request->filled('pl_car_id') ? (int) $request->query('pl_car_id') : null;
        $plFrom = $request->query('pl_from');
        $plTo = $request->query('pl_to');
        $plPosting = (string) $request->query('pl_posting', VehicleProfitLossReportService::POSTING_ALL);
        if (! in_array($plPosting, [
            VehicleProfitLossReportService::POSTING_ALL,
            VehicleProfitLossReportService::POSTING_POSTED,
            VehicleProfitLossReportService::POSTING_PENDING,
        ], true)) {
            $plPosting = VehicleProfitLossReportService::POSTING_ALL;
        }

        [$plFromCarbon, $plToCarbon, $plDateError] = $this->vehicleProfitLossReportService->parseDateRange(
            is_string($plFrom) ? $plFrom : null,
            is_string($plTo) ? $plTo : null
        );

        $vehicleProfitLossReport = null;
        $plReportReady = false;

        if ($plCarId) {
            $plCar = $cars->firstWhere('id', $plCarId);
            if ($plCar instanceof Car) {
                $vehicleProfitLossReport = $this->vehicleProfitLossReportService->build(
                    $plCar,
                    $tenantId,
                    $plFromCarbon,
                    $plToCarbon,
                    $plPosting
                );
                $plReportReady = $plDateError === null && empty($vehicleProfitLossReport['error']);
            } else {
                $vehicleProfitLossReport = [
                    'car' => null,
                    'from' => $plFromCarbon,
                    'to' => $plToCarbon,
                    'posting_filter' => $plPosting,
                    'lines' => [],
                    'summary' => $this->vehicleProfitLossReportService->summarize([]),
                    'error' => 'The selected vehicle is not valid.',
                ];
            }
        }

        if ($plDateError) {
            $vehicleProfitLossReport = $vehicleProfitLossReport ?? [
                'car' => null,
                'from' => null,
                'to' => null,
                'posting_filter' => $plPosting,
                'lines' => [],
                'summary' => $this->vehicleProfitLossReportService->summarize([]),
                'error' => $plDateError,
            ];
            $plReportReady = false;
        }

        return compact(
            'cars',
            'insuranceFrom',
            'insuranceTo',
            'insuranceCompanyId',
            'insuranceProviderId',
            'insuranceDateError',
            'insuranceRemovedInRange',
            'insuranceActivatedInRange',
            'insuranceActivatedOrRemovedInRange',
            'insuranceActiveOnInsurance',
            'reportCompanies',
            'reportInsuranceProviders',
            'selectedInsuranceCompany',
            'selectedInsuranceProvider',
            'ticketCarId',
            'ticketAt',
            'ticketTrackingError',
            'ticketTrackingResult',
            'ticketTrackingReady',
            'ticketTrackingQueriedAt',
            'ticketCars',
            'plCarId',
            'plFrom',
            'plTo',
            'plPosting',
            'plDateError',
            'plReportReady',
            'vehicleProfitLossReport',
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function vehicleProfitLossCsvResponse(array $report): StreamedResponse
    {
        $registration = $report['car']?->registration ?? 'vehicle';
        $filename = 'vehicle-pl-'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $registration).'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Direction', 'Category', 'Description', 'Amount', 'Status', 'Source']);

            foreach ($report['lines'] as $line) {
                fputcsv($handle, [
                    $line['date'] ?? '',
                    $line['direction'] ?? '',
                    $line['category_label'] ?? '',
                    $line['description'] ?? '',
                    number_format((float) ($line['amount'] ?? 0), 2, '.', ''),
                    $line['posting_status'] ?? '—',
                    $line['source_label'] ?? '',
                ]);
            }

            fputcsv($handle, []);
            $summary = $report['summary'] ?? [];
            foreach ($summary as $key => $value) {
                fputcsv($handle, [$key, number_format((float) $value, 2, '.', '')]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function vehicleProfitLossPdfResponse(array $report)
    {
        $pdf = PDF::loadView('backend.reports.vehicle_profit_loss_pdf', [
            'report' => $report,
        ])->setPaper('a4', 'portrait');

        $registration = $report['car']?->registration ?? 'vehicle';
        $filename = 'vehicle-pl-'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $registration).'.pdf';

        return $pdf->download($filename);
    }

    private function latestMotForCar(Car $car): ?CarMot
    {
        return $car->mots
            ->sortByDesc(fn (CarMot $m) => [optional($m->expiry_date)->timestamp ?? 0, $m->id])
            ->first();
    }

    private function latestPhvForCar(Car $car): ?CarPhv
    {
        return $car->phvs
            ->sortByDesc(fn (CarPhv $p) => [optional($p->expiry_date)->timestamp ?? 0, $p->id])
            ->first();
    }

    private function expiryStatusLabel(?Carbon $expiry): string
    {
        if (! $expiry) {
            return 'Missing';
        }

        $today = now()->startOfDay();
        $expiryDay = $expiry->copy()->startOfDay();

        if ($expiryDay->lt($today)) {
            return 'Expired';
        }

        if ($expiryDay->lte($today->copy()->addDays(30))) {
            return 'Expiring';
        }

        return 'OK';
    }
}
