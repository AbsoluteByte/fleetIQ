<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarInsuranceCoveragePeriod;
use App\Models\CarInsuranceDocument;
use App\Models\CarModel;
use App\Models\CarMot;
use App\Models\CarPhv;
use App\Models\CarRoadTax;
use App\Models\Company;
use App\Models\Counsel;
use App\Models\InsuranceProvider;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    protected $url = 'cars.';

    protected $dir = 'backend.cars.';

    protected $name = 'Cars';

    public function __construct()
    {
        $this->middleware('role:admin|manager|user');
        view()->share('url', $this->url);
        view()->share('dir', $this->dir);
        view()->share('singular', Str::singular($this->name));
        view()->share('plural', Str::plural($this->name));
    }

    // ✅ Updated Index
    public function index()
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->route('dashboard')
                ->with('error', 'No active company found! Please contact administrator.');
        }

        $cars = Car::where('tenant_id', $tenant->id)
            ->with([
                'company',
                'carModel',
                'phvs.counsel',
                'insurances.status',
                'services',
                'reservations',
                'agreements',
            ])
            ->latest()
            ->get();

        return view($this->dir.'index', compact('cars'));
    }

    // ✅ Updated Create
    public function create()
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->route('dashboard')
                ->with('error', 'No active company found!');
        }

        $model = new Car;

        // ✅ Filter by tenant
        $companies = Company::where('tenant_id', $tenant->id)->get();
        $carModels = CarModel::where('tenant_id', $tenant->id)->get();
        $counsels = Counsel::where('tenant_id', $tenant->id)->get();
        $insuranceProviders = InsuranceProvider::where('tenant_id', $tenant->id)->get();
        $carInsuranceStatuses = Status::where('type', 'insurance')->whereIn('name', ['Active', 'Inactive'])->get()
            ->sortBy(fn (Status $s) => $s->name === 'Active' ? 0 : 1)
            ->values();
        $carInsuranceActiveStatusId = Status::where('type', 'insurance')->where('name', 'Active')->value('id');
        $carInsuranceInactiveStatusId = Status::where('type', 'insurance')->where('name', 'Inactive')->value('id');

        return view($this->dir.'create', compact('model', 'companies', 'carModels', 'counsels', 'insuranceProviders', 'carInsuranceStatuses', 'carInsuranceActiveStatusId', 'carInsuranceInactiveStatusId'));
    }

    // ✅ Updated Store
    public function store(Request $request)
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->back()
                ->with('error', 'No active company found!');
        }

        $activeCoverageStatusId = Status::where('type', 'insurance')->where('name', 'Active')->value('id');

        $this->normalizeInsuranceDocumentsUploads($request);

        // Build validation rules dynamically
        $rules = [
            'company_id' => 'required|exists:companies,id',
            'car_model_id' => 'required|exists:car_models,id',
            'registration' => 'required|string|unique:cars',
            'color' => 'required|string',
            'vin' => 'required|string',
            'v5_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'manufacture_year' => 'required|integer|min:1900|max:'.date('Y'),
            'registration_year' => 'required|integer|min:1900|max:'.date('Y'),
            'purchase_date' => 'required|date',
            'purchase_price' => 'required|numeric|min:0',
            'purchase_type' => 'required|in:imported,uk',
            'seller_name' => 'nullable|string|max:255',
            'seller_notes' => 'nullable|string',
            'damaged_notes' => 'nullable|string',
            'phv_status' => 'nullable|in:need_to_apply,applied,phv_active',
            'phv_applied_date' => 'nullable|date',
            'log_book_applied' => 'nullable|boolean',
            'log_book_applied_date' => 'nullable|date',
            'old_log_book' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'fleet_status' => 'nullable|in:available_for_rent,damaged,written_off,stolen,for_sale,sold,reserved',
            'available_from_date' => 'nullable|date',
            'service_date' => 'nullable|date',
            'service_mileage' => 'nullable|integer|min:0',
            'service_notes' => 'nullable|string',
            'service_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'reserve_car' => 'nullable|boolean',
            'reservation_customer_name' => 'required_if:reserve_car,1|nullable|string|max:255',
            'reservation_customer_phone' => 'nullable|string|max:50',
            'reservation_customer_email' => 'nullable|email|max:255',
            'reservation_date' => 'nullable|date',
            'reservation_available_from_date' => 'nullable|date',
            'reservation_terms_conditions' => 'nullable|string',

            'mots.*.expiry_date' => 'nullable|date',
            'mots.*.amount' => 'nullable|numeric|min:0',
            'mots.*.term' => 'nullable|string',
            'mots.*.document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',

            'road_taxes.*.start_date' => 'nullable|date',
            'road_taxes.*.term' => 'nullable|string',
            'road_taxes.*.amount' => 'nullable|numeric|min:0',

            'phvs.*.counsel_id' => 'nullable|exists:counsels,id',
            'phvs.*.amount' => 'nullable|numeric|min:0',
            'phvs.*.start_date' => 'nullable|date',
            'phvs.*.expiry_date' => 'nullable|date',
            'phvs.*.notify_before_expiry' => 'nullable|integer|min:1',
            'phvs.*.document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'phvs.*.phv_applied' => 'nullable|boolean',
            'phvs.*.phv_applied_date' => 'nullable|date',
        ];

        if ($request->has('has_insurance')) {
            $rules = array_merge($rules, [
                'insurance_provider_id' => [
                    'nullable',
                    Rule::exists('insurance_providers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
                    Rule::requiredIf(fn () => (int) $request->input('insurance_status_id') === (int) $activeCoverageStatusId),
                ],
                'insurance_documents' => 'nullable|array',
                'insurance_documents.*' => $this->insuranceDocumentItemValidationRules(),
                'insurance_coverage_start_date' => 'nullable|date',
                'insurance_coverage_end_date' => 'nullable|date',
                'insurance_status_id' => [
                    'required',
                    Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('type', 'insurance')->whereIn('name', ['Active', 'Inactive'])),
                ],
            ]);
        }

        $validated = $request->validate($rules);

        try {
            $car = DB::transaction(function () use ($validated, $request, $tenant) {
                if ($request->hasFile('v5_document')) {
                    $validated['v5_document'] = $this->uploadFile($request->file('v5_document'), 'uploads/cars');
                }

                $carData = $this->carMassAssignmentFromValidated($validated, $request);
                $carData = $this->mergeLogBookCarData($request, $carData, null);
                $carData['tenant_id'] = $tenant->id;
                $carData['createdBy'] = Auth::id();
                $car = Car::create($carData);

                // Store MOTs
                if ($request->has('mots')) {
                    foreach ($request->input('mots') as $index => $motData) {
                        if (! $this->historyRowHasValues($motData, ['expiry_date', 'amount', 'term'])) {
                            continue;
                        }

                        if ($request->hasFile("mots.{$index}.document")) {
                            $motData['document'] = $this->uploadFile(
                                $request->file("mots.{$index}.document"),
                                'uploads/cars/mot_documents'
                            );
                        }
                        $car->mots()->create($motData);
                    }
                }

                // Store Road Taxes
                if ($request->has('road_taxes')) {
                    foreach ($request->input('road_taxes') as $roadTaxData) {
                        if (! $this->historyRowHasValues($roadTaxData, ['start_date', 'term', 'amount'])) {
                            continue;
                        }

                        $car->roadTaxes()->create($roadTaxData);
                    }
                }

                // Store PHVs
                $newFuturePhvAdded = false;
                if ($request->has('phvs')) {
                    foreach ($request->input('phvs') as $index => $phvData) {
                        if (! $this->historyRowHasValues($phvData, ['counsel_id', 'amount', 'start_date', 'expiry_date', 'notify_before_expiry'])) {
                            continue;
                        }

                        if ($request->hasFile("phvs.{$index}.document")) {
                            $phvData['document'] = $this->uploadFile(
                                $request->file("phvs.{$index}.document"),
                                'uploads/cars/phv_documents'
                            );
                        }
                        $phvData = $this->mergePhvAppliedData($phvData, null);
                        $car->phvs()->create($phvData);
                        $newFuturePhvAdded = $newFuturePhvAdded || $this->hasFuturePhvExpiry($phvData);
                    }
                }

                $this->syncCarPhvStatus($request, $car, $newFuturePhvAdded);
                $this->storeServiceIfPresent($request, $car, $tenant);
                $this->syncReservation($request, $car, $tenant);

                // Store Insurance (provider + Active/Inactive + document — policy dates/notifications live on Insurance Provider)
                if ($request->has('has_insurance')) {
                    $coverageStatus = Status::findOrFail($validated['insurance_status_id']);
                    $uploadedDocs = $this->storeInsuranceDocumentFiles($request);
                    $latestStoredName = $uploadedDocs !== [] ? end($uploadedDocs)['stored'] : null;

                    $insuranceData = [
                        'tenant_id' => $tenant->id,
                        'insurance_provider_id' => $validated['insurance_provider_id'] ?? null,
                        'start_date' => null,
                        'expiry_date' => null,
                        'notify_before_expiry' => null,
                        'status_id' => $validated['insurance_status_id'],
                        'insurance_document' => $latestStoredName,
                    ];

                    $car->insurances()->create($insuranceData);
                    $this->syncInsuranceCoveragePeriods(
                        $car,
                        null,
                        null,
                        $coverageStatus->name,
                        $insuranceData['insurance_provider_id'],
                        Auth::id(),
                        $validated['insurance_coverage_start_date'] ?? null,
                        $validated['insurance_coverage_end_date'] ?? null
                    );

                    $this->appendCarInsuranceDocumentsFromUploads(
                        $car->fresh(),
                        $uploadedDocs,
                        $insuranceData['insurance_provider_id']
                    );

                    $this->removeCarInsuranceSnapshotIfInactiveWithRecordedEnd(
                        $coverageStatus,
                        $validated['insurance_coverage_end_date'] ?? null,
                        $car
                    );
                }

                return $car;
            });

            return redirect()->route($this->url.'index')
                ->with('success', 'Car added successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating car: '.$e->getMessage());
        }
    }

    // ✅ Updated Show
    public function show(Car $car)
    {
        $tenant = Auth::user()->currentTenant();

        // ✅ Check ownership
        if ($car->tenant_id !== $tenant->id) {
            abort(403, 'Unauthorized access to this car');
        }

        $car->load(['company', 'carModel', 'mots', 'roadTaxes', 'phvs.counsel', 'phvs.phvAppliedBy', 'insurances.insuranceProvider', 'insurances.status', 'insuranceCoveragePeriods.insuranceProvider', 'insuranceCoveragePeriods.activatedBy', 'insuranceCoveragePeriods.deactivatedBy', 'insuranceDocuments.insuranceProvider', 'logBookAppliedBy', 'services.createdBy', 'reservations.createdBy', 'agreements']);
        $this->sortCarHistoryRelations($car);

        return view($this->dir.'show', compact('car'));
    }

    // ✅ Updated Edit
    public function edit($id)
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->route('dashboard')
                ->with('error', 'No active company found!');
        }

        $model = Car::where('tenant_id', $tenant->id)
            ->with(['mots', 'roadTaxes', 'phvs.counsel', 'phvs.phvAppliedBy', 'insurances.insuranceProvider', 'insurances.status', 'insuranceCoveragePeriods.insuranceProvider', 'insuranceCoveragePeriods.activatedBy', 'insuranceCoveragePeriods.deactivatedBy', 'insuranceDocuments.insuranceProvider', 'sornAppliedBy', 'services', 'reservations'])
            ->findOrFail($id);
        $this->sortCarHistoryRelations($model);

        // ✅ Filter by tenant
        $companies = Company::where('tenant_id', $tenant->id)->get();
        $carModels = CarModel::where('tenant_id', $tenant->id)->get();
        $counsels = Counsel::where('tenant_id', $tenant->id)->get();
        $insuranceProviders = InsuranceProvider::where('tenant_id', $tenant->id)->get();
        $carInsuranceStatuses = Status::where('type', 'insurance')->whereIn('name', ['Active', 'Inactive'])->get()
            ->sortBy(fn (Status $s) => $s->name === 'Active' ? 0 : 1)
            ->values();
        $carInsuranceActiveStatusId = Status::where('type', 'insurance')->where('name', 'Active')->value('id');
        $carInsuranceInactiveStatusId = Status::where('type', 'insurance')->where('name', 'Inactive')->value('id');

        return view($this->dir.'edit', compact('model', 'companies', 'carModels', 'counsels', 'insuranceProviders', 'carInsuranceStatuses', 'carInsuranceActiveStatusId', 'carInsuranceInactiveStatusId'));
    }

    // ✅ Updated Update
    public function update(Request $request, Car $car)
    {
        $tenant = Auth::user()->currentTenant();

        if (! $tenant) {
            return redirect()->back()
                ->with('error', 'No active company found!');
        }

        if ($car->tenant_id !== $tenant->id) {
            abort(403, 'Unauthorized access to this car.');
        }

        $activeCoverageStatusId = Status::where('type', 'insurance')->where('name', 'Active')->value('id');

        $this->normalizeInsuranceDocumentsUploads($request);

        $rules = [
            'company_id' => 'required|exists:companies,id',
            'car_model_id' => 'required|exists:car_models,id',
            'registration' => 'required|string|unique:cars,registration,'.$car->id,
            'color' => 'required|string',
            'vin' => 'required|string',
            'v5_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'manufacture_year' => 'required|integer|min:1900|max:'.date('Y'),
            'registration_year' => 'required|integer|min:1900|max:'.date('Y'),
            'purchase_date' => 'required|date',
            'purchase_price' => 'required|numeric|min:0',
            'purchase_type' => 'required|in:imported,uk',
            'seller_name' => 'nullable|string|max:255',
            'seller_notes' => 'nullable|string',
            'damaged_notes' => 'nullable|string',
            'phv_status' => 'nullable|in:need_to_apply,applied,phv_active',
            'phv_applied_date' => 'nullable|date',
            'log_book_applied' => 'nullable|boolean',
            'log_book_applied_date' => 'nullable|date',
            'old_log_book' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'fleet_status' => 'nullable|in:available_for_rent,damaged,written_off,stolen,for_sale,sold,reserved',
            'available_from_date' => 'nullable|date',
            'service_date' => 'nullable|date',
            'service_mileage' => 'nullable|integer|min:0',
            'service_notes' => 'nullable|string',
            'service_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'reserve_car' => 'nullable|boolean',
            'reservation_customer_name' => 'required_if:reserve_car,1|nullable|string|max:255',
            'reservation_customer_phone' => 'nullable|string|max:50',
            'reservation_customer_email' => 'nullable|email|max:255',
            'reservation_date' => 'nullable|date',
            'reservation_available_from_date' => 'nullable|date',
            'reservation_terms_conditions' => 'nullable|string',

            'mots.*.id' => 'nullable|exists:car_mots,id',
            'mots.*.expiry_date' => 'nullable|date',
            'mots.*.amount' => 'nullable|numeric|min:0',
            'mots.*.term' => 'nullable|string',
            'mots.*.document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',

            'road_taxes.*.start_date' => 'nullable|date',
            'road_taxes.*.term' => 'nullable|string',
            'road_taxes.*.amount' => 'nullable|numeric|min:0',

            'phvs.*.id' => 'nullable|exists:car_phvs,id',
            'phvs.*.counsel_id' => 'nullable|exists:counsels,id',
            'phvs.*.amount' => 'nullable|numeric|min:0',
            'phvs.*.start_date' => 'nullable|date',
            'phvs.*.expiry_date' => 'nullable|date',
            'phvs.*.notify_before_expiry' => 'nullable|integer|min:1',
            'phvs.*.document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'phvs.*.phv_applied' => 'nullable|boolean',
            'phvs.*.phv_applied_date' => 'nullable|date',
        ];

        if ($request->has('has_insurance')) {
            $rules = array_merge($rules, [
                'insurance_provider_id' => [
                    'nullable',
                    Rule::exists('insurance_providers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
                    Rule::requiredIf(fn () => (int) $request->input('insurance_status_id') === (int) $activeCoverageStatusId),
                ],
                'insurance_documents' => 'nullable|array',
                'insurance_documents.*' => $this->insuranceDocumentItemValidationRules(),
                'insurance_coverage_start_date' => 'nullable|date',
                'insurance_coverage_end_date' => 'nullable|date',
                'insurance_status_id' => [
                    'required',
                    Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('type', 'insurance')->whereIn('name', ['Active', 'Inactive'])),
                ],
            ]);
        }

        $validated = $request->validate($rules);

        if ($request->has('has_insurance') && isset($validated['insurance_status_id'])
            && (int) $validated['insurance_status_id'] === (int) $activeCoverageStatusId) {
            if (CarInsuranceCoveragePeriod::where('car_id', $car->id)->where('end_date_pending', true)->exists()) {
                return redirect()->back()
                    ->withErrors([
                        'insurance_status_id' => 'Set the coverage end date for the previous inactive period before selecting Active again.',
                    ])
                    ->withInput();
            }
        }

        try {
            $updatedCar = DB::transaction(function () use ($validated, $request, $car, $tenant) {

                if ($request->hasFile('v5_document')) {
                    $oldV5Document = $car->v5_document;
                    $validated['v5_document'] = $this->uploadFile(
                        $request->file('v5_document'),
                        'uploads/cars'
                    );
                    if ($oldV5Document) {
                        $this->deleteFile($oldV5Document, 'uploads/cars');
                    }
                }

                $carData = $this->carMassAssignmentFromValidated($validated, $request);
                $carData = $this->mergeLogBookCarData($request, $carData, $car);
                $carData['tenant_id'] = $tenant->id;
                $carData['updatedBy'] = Auth::id();
                $car->update($carData);

                $car->load(['mots', 'phvs', 'insurances']);
                $this->sortCarHistoryRelations($car);

                // ==================== Update MOTs ====================
                $existingMots = $car->mots->keyBy('id');
                $processedMotIds = [];

                if ($request->has('mots')) {
                    foreach ($request->input('mots') as $index => $motData) {
                        $motId = $motData['id'] ?? null;
                        $existingMot = $motId ? $existingMots->get($motId) : null;

                        if (! $existingMot && ! $this->historyRowHasValues($motData, ['expiry_date', 'amount', 'term'])) {
                            continue;
                        }

                        if ($request->hasFile("mots.{$index}.document")) {
                            $motData['document'] = $this->uploadFile(
                                $request->file("mots.{$index}.document"),
                                'uploads/cars/mot_documents'
                            );

                            if ($existingMot && $existingMot->document) {
                                $this->deleteFile($existingMot->document, 'uploads/cars/mot_documents');
                            }
                        } elseif ($existingMot && $existingMot->document) {
                            $motData['document'] = $existingMot->document;
                        }

                        unset($motData['id']);

                        if ($existingMot) {
                            $existingMot->update($motData);
                            $processedMotIds[] = $existingMot->id;
                        } else {
                            $newMot = $car->mots()->create($motData);
                            $processedMotIds[] = $newMot->id;
                        }
                    }
                }

                $motsToDelete = $existingMots->keys()->diff($processedMotIds);
                foreach ($motsToDelete as $motId) {
                    $motToDelete = $existingMots->get($motId);
                    if ($motToDelete->document) {
                        $this->deleteFile($motToDelete->document, 'uploads/cars/mot_documents');
                    }
                    $motToDelete->delete();
                }

                // ==================== Update Road Taxes ====================
                $car->roadTaxes()->delete();
                if ($request->has('road_taxes')) {
                    foreach ($request->input('road_taxes') as $roadTaxData) {
                        if (! $this->historyRowHasValues($roadTaxData, ['start_date', 'term', 'amount'])) {
                            continue;
                        }

                        $car->roadTaxes()->create($roadTaxData);
                    }
                }

                // ==================== Update PHVs ====================
                $existingPhvs = $car->phvs->keyBy('id');
                $processedPhvIds = [];
                $newFuturePhvAdded = false;

                if ($request->has('phvs')) {
                    foreach ($request->input('phvs') as $index => $phvData) {
                        $phvId = $phvData['id'] ?? null;
                        $existingPhv = $phvId ? $existingPhvs->get($phvId) : null;

                        if (! $existingPhv && ! $this->historyRowHasValues($phvData, ['counsel_id', 'amount', 'start_date', 'expiry_date', 'notify_before_expiry'])) {
                            continue;
                        }

                        if ($request->hasFile("phvs.{$index}.document")) {
                            $phvData['document'] = $this->uploadFile(
                                $request->file("phvs.{$index}.document"),
                                'uploads/cars/phv_documents'
                            );

                            if ($existingPhv && $existingPhv->document) {
                                $this->deleteFile($existingPhv->document, 'uploads/cars/phv_documents');
                            }
                        } elseif ($existingPhv && $existingPhv->document) {
                            $phvData['document'] = $existingPhv->document;
                        }

                        $phvData = $this->mergePhvAppliedData($phvData, $existingPhv);
                        unset($phvData['id']);

                        if ($existingPhv) {
                            $existingPhv->update($phvData);
                            $processedPhvIds[] = $existingPhv->id;
                        } else {
                            $newPhv = $car->phvs()->create($phvData);
                            $processedPhvIds[] = $newPhv->id;
                            $newFuturePhvAdded = $newFuturePhvAdded || $this->hasFuturePhvExpiry($phvData);
                        }
                    }
                }

                $phvsToDelete = $existingPhvs->keys()->diff($processedPhvIds);
                foreach ($phvsToDelete as $phvId) {
                    $phvToDelete = $existingPhvs->get($phvId);
                    if ($phvToDelete->document) {
                        $this->deleteFile($phvToDelete->document, 'uploads/cars/phv_documents');
                    }
                    $phvToDelete->delete();
                }

                $this->syncCarPhvStatus($request, $car, $newFuturePhvAdded);
                $this->storeServiceIfPresent($request, $car, $tenant);
                $this->syncReservation($request, $car, $tenant);

                // ==================== Update Insurance ====================
                $car->load(['insurances.status']);
                $this->sortCarHistoryRelations($car);
                $latestInsurance = $car->insurances->first();
                $prevStatusName = optional($latestInsurance?->status)->name;
                $prevProviderId = $latestInsurance?->insurance_provider_id;

                if ($request->has('has_insurance')) {
                    $coverageStatus = Status::findOrFail($validated['insurance_status_id']);
                    $uploadedDocs = $this->storeInsuranceDocumentFiles($request);

                    $insuranceData = [
                        'tenant_id' => $tenant->id,
                        'insurance_provider_id' => $validated['insurance_provider_id'] ?? null,
                        'start_date' => null,
                        'expiry_date' => null,
                        'notify_before_expiry' => null,
                        'status_id' => $validated['insurance_status_id'],
                    ];

                    if ($uploadedDocs !== []) {
                        $insuranceData['insurance_document'] = end($uploadedDocs)['stored'];
                    } elseif ($latestInsurance && $latestInsurance->insurance_document) {
                        $insuranceData['insurance_document'] = $latestInsurance->insurance_document;
                    } else {
                        $insuranceData['insurance_document'] = null;
                    }

                    if ($latestInsurance) {
                        $latestInsurance->update($insuranceData);
                    } else {
                        $car->insurances()->create($insuranceData);
                    }

                    $this->syncInsuranceCoveragePeriods(
                        $car,
                        $prevStatusName,
                        $prevProviderId,
                        $coverageStatus->name,
                        $insuranceData['insurance_provider_id'],
                        Auth::id(),
                        $validated['insurance_coverage_start_date'] ?? null,
                        $validated['insurance_coverage_end_date'] ?? null
                    );

                    $this->appendCarInsuranceDocumentsFromUploads(
                        $car->fresh(),
                        $uploadedDocs,
                        $insuranceData['insurance_provider_id']
                    );

                    $this->removeCarInsuranceSnapshotIfInactiveWithRecordedEnd(
                        $coverageStatus,
                        $validated['insurance_coverage_end_date'] ?? null,
                        $car
                    );
                } else {
                    CarInsuranceCoveragePeriod::where('car_id', $car->id)->whereNull('deactivated_at')->update([
                        'deactivated_at' => now(),
                        'deactivated_by_user_id' => Auth::id(),
                        'end_date_pending' => false,
                    ]);
                    $car->insurances()->delete();
                }

                return $car;
            });

            return redirect()
                ->route($this->url.'edit', $updatedCar)
                ->with('success', 'Car updated successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating car: '.$e->getMessage());
        }
    }

    // ✅ Updated Destroy
    public function destroy(Car $car)
    {
        $tenant = Auth::user()->currentTenant();

        // ✅ Check ownership
        if ($car->tenant_id !== $tenant->id) {
            abort(403, 'Unauthorized access');
        }

        try {
            DB::transaction(function () use ($car) {
                $car->load(['mots', 'phvs', 'insurances', 'insuranceDocuments', 'services']);
                $this->deleteCarFiles($car);
                $car->mots()->delete();
                $car->roadTaxes()->delete();
                $car->phvs()->delete();
                $car->insurances()->delete();
                $car->services()->delete();
                $car->reservations()->delete();
                $car->delete();
            });

            return redirect()->route($this->url.'index')
                ->with('success', 'Car deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error deleting car: '.$e->getMessage());
        }
    }

    /**
     * Most recent first: MOT and PHV by expiry date, road tax by start date.
     */
    private function sortCarHistoryRelations(Car $car): void
    {
        $mots = $car->mots
            ->sortByDesc(function ($m) {
                return optional($m->expiry_date)->timestamp ?? 0;
            })
            ->values();
        $car->setRelation('mots', $mots);

        $roadTaxes = $car->roadTaxes
            ->sortByDesc(function ($r) {
                return optional($r->start_date)->timestamp ?? 0;
            })
            ->values();
        $car->setRelation('roadTaxes', $roadTaxes);

        $phvs = $car->phvs
            ->sortByDesc(function ($p) {
                return optional($p->expiry_date)->timestamp ?? 0;
            })
            ->values();
        $car->setRelation('phvs', $phvs);

        $insurances = $car->insurances
            ->sortByDesc(function ($i) {
                return $i->id;
            })
            ->values();
        $car->setRelation('insurances', $insurances);
    }

    public function applySorn(Car $car)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $car->tenant_id !== $tenant->id) {
            abort(403);
        }
        if ($car->sorn_applied) {
            return response()->json([
                'ok' => false,
                'message' => 'SORN is already applied for this car.',
            ], 422);
        }

        $car->update([
            'sorn_applied' => true,
            'sorn_applied_at' => now(),
            'sorn_applied_by' => Auth::id(),
            'updatedBy' => Auth::id(),
        ]);

        return response()->json([
            'ok' => true,
            'gov_sorn_url' => 'https://www.gov.uk/make-a-sorn',
            'sorn_applied_by_name' => Auth::user()?->name,
            'sorn_applied_at_formatted' => now()->format('d M Y').' at '.now()->format('h:i A'),
        ]);
    }

    public function endSorn(Car $car)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $car->tenant_id !== $tenant->id) {
            abort(403);
        }
        if (! $car->sorn_applied) {
            return response()->json([
                'ok' => false,
                'message' => 'SORN is not applied for this car.',
            ], 422);
        }

        $car->update([
            'sorn_applied' => false,
            'sorn_applied_at' => null,
            'sorn_applied_by' => null,
            'updatedBy' => Auth::id(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroyMot(Car $car, int $car_mot)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $car->tenant_id !== $tenant->id) {
            abort(403);
        }
        $record = CarMot::where('car_id', $car->id)->where('id', $car_mot)->firstOrFail();
        if ($record->document) {
            $this->deleteFile($record->document, 'uploads/cars/mot_documents');
        }
        $record->delete();

        return response()->json(['ok' => true]);
    }

    public function destroyRoadTax(Car $car, int $car_road_tax)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $car->tenant_id !== $tenant->id) {
            abort(403);
        }
        $record = CarRoadTax::where('car_id', $car->id)->where('id', $car_road_tax)->firstOrFail();
        $record->delete();

        return response()->json(['ok' => true]);
    }

    public function destroyPhv(Car $car, int $car_phv)
    {
        $tenant = Auth::user()->currentTenant();
        if (! $tenant || $car->tenant_id !== $tenant->id) {
            abort(403);
        }
        $record = CarPhv::where('car_id', $car->id)->where('id', $car_phv)->firstOrFail();
        if ($record->document) {
            $this->deleteFile($record->document, 'uploads/cars/phv_documents');
        }
        $record->delete();

        return response()->json(['ok' => true]);
    }

    public function statusReport(string $status)
    {
        $tenant = Auth::user()->currentTenant();
        $statuses = $this->fleetStatuses();

        abort_unless($tenant && array_key_exists($status, $statuses), 404);

        $cars = Car::where('tenant_id', $tenant->id)
            ->where('fleet_status', $status)
            ->with(['company', 'carModel', 'phvs.counsel', 'reservations'])
            ->latest()
            ->get();

        return view($this->dir.'status-report', [
            'cars' => $cars,
            'status' => $status,
            'statusLabel' => $statuses[$status],
        ]);
    }

    public function availableByPhv()
    {
        $tenant = Auth::user()->currentTenant();
        abort_unless($tenant, 403);

        $cars = Car::where('tenant_id', $tenant->id)
            ->with(['company', 'carModel', 'phvs.counsel', 'insurances.status', 'services', 'reservations', 'agreements'])
            ->get()
            ->filter(fn (Car $car) => $car->isAvailableForRent())
            ->groupBy(fn (Car $car) => $car->latestPhvCounselName() ?: 'No PHV Council');

        return view($this->dir.'available-by-phv', compact('cars'));
    }

    public function awaitingPhv()
    {
        $tenant = Auth::user()->currentTenant();
        abort_unless($tenant, 403);

        $cars = Car::where('tenant_id', $tenant->id)
            ->doesntHave('phvs')
            ->with(['company', 'carModel', 'reservations'])
            ->latest()
            ->get();

        return view($this->dir.'status-report', [
            'cars' => $cars,
            'status' => 'awaiting_phv',
            'statusLabel' => 'Awaiting PHV',
        ]);
    }

    public function downloadV5(Car $car)
    {
        return $this->downloadCarFile($car, 'uploads/cars', $car->v5_document, 'v5');
    }

    public function downloadMot(Car $car, int $car_mot)
    {
        $record = CarMot::where('car_id', $car->id)->where('id', $car_mot)->firstOrFail();

        return $this->downloadCarFile($car, 'uploads/cars/mot_documents', $record->document, 'mot');
    }

    public function downloadPhv(Car $car, int $car_phv)
    {
        $record = CarPhv::where('car_id', $car->id)->where('id', $car_phv)->firstOrFail();

        return $this->downloadCarFile($car, 'uploads/cars/phv_documents', $record->document, 'phv');
    }

    /**
     * Only pass real car columns to create/update (not nested mots/phvs/insurance keys from validate()).
     */
    private function carMassAssignmentFromValidated(array $validated, Request $request): array
    {
        $keys = [
            'company_id', 'car_model_id', 'registration', 'color', 'vin', 'v5_document',
            'manufacture_year', 'registration_year', 'purchase_date', 'purchase_price',
            'purchase_type', 'seller_name', 'seller_notes', 'damaged_notes',
            'phv_status', 'phv_applied_date', 'fleet_status', 'available_from_date',
        ];

        $data = array_intersect_key($validated, array_flip($keys));

        if (! array_key_exists('seller_name', $data) && $request->has('seller_name')) {
            $data['seller_name'] = $request->string('seller_name')->value();
        }

        if (! array_key_exists('seller_notes', $data) && $request->has('seller_notes')) {
            $data['seller_notes'] = $request->string('seller_notes')->value();
        }

        if (! array_key_exists('damaged_notes', $data) && $request->has('damaged_notes')) {
            $data['damaged_notes'] = $request->string('damaged_notes')->value();
        }

        $data['fleet_status'] = $data['fleet_status'] ?? 'available_for_rent';
        if (($data['available_from_date'] ?? '') === '') {
            $data['available_from_date'] = null;
        }

        return $data;
    }

    private function mergePhvAppliedData(array $phvData, ?CarPhv $existing): array
    {
        if ($existing && ! array_key_exists('phv_applied', $phvData) && ! array_key_exists('phv_applied_date', $phvData)) {
            $phvData['phv_applied'] = $existing->phv_applied;
            $phvData['phv_applied_date'] = $existing->phv_applied_date?->format('Y-m-d');
            $phvData['phv_applied_by'] = $existing->phv_applied_by;

            return $phvData;
        }

        $isApplied = (bool) ($phvData['phv_applied'] ?? false);
        $phvData['phv_applied'] = $isApplied;
        $phvData['phv_applied_date'] = $isApplied ? ($phvData['phv_applied_date'] ?? null) : null;

        if ($isApplied) {
            $phvData['phv_applied_by'] = $existing && $existing->phv_applied
                ? $existing->phv_applied_by
                : Auth::id();
        } else {
            $phvData['phv_applied_by'] = null;
        }

        return $phvData;
    }

    private function historyRowHasValues(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private function hasFuturePhvExpiry(array $phvData): bool
    {
        if (empty($phvData['expiry_date'])) {
            return false;
        }

        return Carbon::parse($phvData['expiry_date'])->startOfDay()->gte(now()->startOfDay());
    }

    private function syncCarPhvStatus(Request $request, Car $car, bool $newFuturePhvAdded): void
    {
        if ($newFuturePhvAdded) {
            $car->update([
                'phv_status' => 'phv_active',
                'phv_applied_date' => null,
                'phv_applied_by' => null,
            ]);

            return;
        }

        $status = $request->input('phv_status', $car->phv_status ?: 'need_to_apply');
        $appliedDate = $status === 'applied' ? ($request->input('phv_applied_date') ?: null) : null;

        $car->update([
            'phv_status' => $status,
            'phv_applied_date' => $appliedDate,
            'phv_applied_by' => $status === 'applied'
                ? ($car->phv_status === 'applied' && $car->phv_applied_by ? $car->phv_applied_by : Auth::id())
                : null,
        ]);
    }

    private function storeServiceIfPresent(Request $request, Car $car, $tenant): void
    {
        if (! $request->filled('service_date')) {
            return;
        }

        $serviceData = [
            'tenant_id' => $tenant->id,
            'service_date' => $request->input('service_date'),
            'mileage' => $request->input('service_mileage'),
            'notes' => $request->input('service_notes'),
            'created_by' => Auth::id(),
        ];

        if ($request->hasFile('service_document')) {
            $serviceData['document'] = $this->uploadFile($request->file('service_document'), 'uploads/cars/service_documents');
        }

        $alreadyExists = $car->services()
            ->whereDate('service_date', $serviceData['service_date'])
            ->exists();

        if (! $alreadyExists) {
            $car->services()->create($serviceData);
        }
    }

    private function syncReservation(Request $request, Car $car, $tenant): void
    {
        $activeReservation = $car->reservations()->where('status', 'active')->latest()->first();

        if ($car->fleet_status === 'damaged') {
            if ($activeReservation) {
                $activeReservation->update(['status' => 'cancelled']);
            }

            return;
        }

        if (! $request->boolean('reserve_car')) {
            if ($activeReservation) {
                $activeReservation->update(['status' => 'cancelled']);
            }
            if ($car->fleet_status === 'reserved') {
                $car->update(['fleet_status' => 'available_for_rent']);
            }

            return;
        }

        $reservationData = [
            'tenant_id' => $tenant->id,
            'customer_name' => $request->input('reservation_customer_name'),
            'customer_phone' => $request->input('reservation_customer_phone'),
            'customer_email' => $request->input('reservation_customer_email'),
            'reservation_date' => $request->input('reservation_date') ?: now()->toDateString(),
            'available_from_date' => $request->input('reservation_available_from_date'),
            'terms_conditions' => $request->input('reservation_terms_conditions'),
            'status' => 'active',
            'created_by' => Auth::id(),
        ];

        if ($activeReservation) {
            $activeReservation->update($reservationData);
        } else {
            $car->reservations()->create($reservationData);
        }

        $car->update([
            'fleet_status' => 'reserved',
            'available_from_date' => $reservationData['available_from_date'],
        ]);
    }

    /**
     * Log book fields, optional file, and who first enabled "log book applied".
     */
    private function mergeLogBookCarData(Request $request, array $carData, ?Car $existing): array
    {
        $isApplied = $request->boolean('log_book_applied');
        $carData['log_book_applied'] = $isApplied;

        if (array_key_exists('seller_notes', $carData) && $carData['seller_notes'] === '') {
            $carData['seller_notes'] = null;
        }

        if (! $isApplied) {
            $carData['log_book_applied_date'] = null;
            $carData['log_book_applied_by'] = null;
            if ($existing?->old_log_book) {
                $this->deleteFile($existing->old_log_book, 'uploads/cars/log_book');
            }
            $carData['old_log_book'] = null;

            return $carData;
        }

        $rawDate = $request->input('log_book_applied_date');
        $carData['log_book_applied_date'] = ($rawDate !== null && $rawDate !== '') ? $rawDate : null;

        if ($existing === null || ! $existing->log_book_applied) {
            $carData['log_book_applied_by'] = Auth::id();
        } else {
            $carData['log_book_applied_by'] = $existing->log_book_applied_by;
        }

        if ($request->hasFile('old_log_book')) {
            $name = $this->uploadFile($request->file('old_log_book'), 'uploads/cars/log_book');
            if ($existing?->old_log_book) {
                $this->deleteFile($existing->old_log_book, 'uploads/cars/log_book');
            }
            $carData['old_log_book'] = $name;
        } elseif ($existing) {
            $carData['old_log_book'] = $existing->old_log_book;
        }

        return $carData;
    }

    /**
     * Drop empty PHP slots. Re-wrap uploads that have UPLOAD_ERR_OK but fail is_uploaded_file() (seen on
     * some Local/tunnel/nginx setups) using test mode so validation and move() still work safely on the same temp path.
     */
    private function normalizeInsuranceDocumentsUploads(Request $request): void
    {
        if (! $request->files->has('insurance_documents')) {
            return;
        }

        $raw = $request->file('insurance_documents');
        $files = $raw instanceof UploadedFile ? [$raw] : (is_array($raw) ? array_values($raw) : []);

        $out = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                $out[] = $file;

                continue;
            }

            if ($file->isValid()) {
                $out[] = $file;

                continue;
            }

            $path = $file->getRealPath() ?: $file->getPathname();
            if ($path === '' || ! is_readable($path)) {
                $out[] = $file;

                continue;
            }

            $out[] = new UploadedFile(
                $file->getPathname(),
                $file->getClientOriginalName(),
                $file->getClientMimeType(),
                $file->getError(),
                true
            );
        }

        $request->files->remove('insurance_documents');

        if ($out !== []) {
            $request->files->set('insurance_documents', $out);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function insuranceDocumentItemValidationRules(): array
    {
        return [
            'required',
            'file',
            'max:10240',
            /* mimes:png alone fails PNGs guessed as application/octet-stream over some proxies */
            'extensions:pdf,jpg,jpeg,png',
            'mimetypes:image/jpeg,image/png,image/x-png,application/pdf,application/octet-stream',
        ];
    }

    /**
     * Trusted extension from file bytes (preferred over client name so we do not
     * save e.g. JPEG under .png — browsers show a broken image for that mismatch).
     */
    private function extensionFromUploadedFileBytes(string $pathname): ?string
    {
        $fh = @fopen($pathname, 'rb');
        if ($fh === false) {
            return null;
        }

        try {
            $head = fread($fh, 12) ?: '';

            return match (true) {
                str_starts_with($head, "\x89PNG\r\n\x1a\n") => 'png',
                strncmp($head, "\xFF\xD8\xFF", 3) === 0 => 'jpg',
                strncmp($head, '%PDF', 4) === 0 => 'pdf',
                default => null,
            };
        } finally {
            fclose($fh);
        }
    }

    /**
     * Extension to store on disk. Octet-stream / tunnels may yield bogus client
     * extension; trust binary headers before the original filename extension.
     */
    private function persistedUploadExtensionFromFile(UploadedFile $file): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();

        if ($path !== '' && is_readable($path)) {
            $fromBytes = $this->extensionFromUploadedFileBytes($path);
            if ($fromBytes !== null) {
                return $fromBytes;
            }

            $info = @getimagesize($path);
            if ($info !== false && isset($info[2])) {
                return match ($info[2]) {
                    \IMAGETYPE_JPEG => 'jpg',
                    \IMAGETYPE_PNG => 'png',
                    default => 'jpg',
                };
            }
        }

        $client = strtolower($file->getClientOriginalExtension() ?: '');
        $client = $client === 'jpeg' ? 'jpg' : $client;
        if (in_array($client, ['pdf', 'jpg', 'png'], true)) {
            return $client;
        }

        $guess = strtolower((string) $file->guessExtension());
        $guess = $guess === 'jpeg' ? 'jpg' : $guess;
        if (in_array($guess, ['pdf', 'jpg', 'png'], true)) {
            return $guess;
        }

        $ext = strtolower((string) $file->extension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (in_array($ext, ['pdf', 'jpg', 'png'], true)) {
            return $ext;
        }

        $mime = strtolower((string) $file->getMimeType());
        if (str_contains($mime, 'png')) {
            return 'png';
        }
        if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
            return 'jpg';
        }
        if (str_contains($mime, 'pdf')) {
            return 'pdf';
        }

        return 'jpg';
    }

    // ✅ Keep your existing helper methods
    private function uploadFile($file, $directory)
    {
        $ext = $this->persistedUploadExtensionFromFile($file);
        $mimeType = $file->getMimeType();

        if (str_starts_with($mimeType, 'image/') || in_array($ext, ['jpg', 'png'], true)) {
            $dims = @getimagesize($file->getRealPath() ?: $file->getPathname());
            if ($dims !== false && isset($dims[0], $dims[1])) {
                $width = $dims[0];
                $height = $dims[1];
                $name = time().'-'.uniqid().'-'.$width.'-'.$height.'.'.$ext;
            } else {
                $name = time().'-'.uniqid().'.'.$ext;
            }
        } else {
            $name = time().'-'.uniqid().'.'.$ext;
        }

        $path = public_path($directory);

        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }

        if ($file->move($path, $name)) {
            return $name;
        }

        throw new \Exception('Failed to upload file');
    }

    private function deleteFile($filename, $directory)
    {
        if ($filename) {
            $filePath = public_path($directory.'/'.$filename);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }
    }

    private function fleetStatuses(): array
    {
        return [
            'available_for_rent' => 'Available for rent',
            'damaged' => 'Damaged',
            'written_off' => 'Written off',
            'stolen' => 'Stolen',
            'for_sale' => 'For sale',
            'sold' => 'Sold',
            'reserved' => 'Reserved',
        ];
    }

    /**
     * Normalized list of uploads from insurance_documents[] (single or multiple files per request).
     *
     * @return array<int, array{stored: string, original: string|null}>
     */
    private function storeInsuranceDocumentFiles(Request $request): array
    {
        $raw = $request->file('insurance_documents');
        if ($raw === null) {
            return [];
        }

        $files = is_array($raw) ? $raw : [$raw];

        $out = [];
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $out[] = [
                'stored' => $this->uploadFile($file, 'uploads/cars/insurance_documents'),
                'original' => $file->getClientOriginalName(),
            ];
        }

        return $out;
    }

    /**
     * Persist archive rows for each uploaded file (older files kept on disk and in history).
     *
     * @param  array<int, array{stored: string, original: string|null}>  $uploads
     */
    private function appendCarInsuranceDocumentsFromUploads(Car $car, array $uploads, ?int $providerId): void
    {
        if ($uploads === []) {
            return;
        }

        $openPeriodId = CarInsuranceCoveragePeriod::where('car_id', $car->id)
            ->whereNull('deactivated_at')
            ->where('end_date_pending', false)
            ->value('id');

        foreach ($uploads as $item) {
            CarInsuranceDocument::create([
                'tenant_id' => $car->tenant_id,
                'car_id' => $car->id,
                'car_insurance_coverage_period_id' => $openPeriodId,
                'insurance_provider_id' => $providerId,
                'document' => $item['stored'],
                'original_name' => $item['original'],
            ]);
        }
    }

    /**
     * Inactive plus a recorded coverage end date (no pending end): remove car_insurances so the UI
     * behaves like "Add Insurance" unchecked while keeping periods/documents history on the car.
     */
    private function removeCarInsuranceSnapshotIfInactiveWithRecordedEnd(
        Status $coverageStatus,
        ?string $endDateYmd,
        Car $car
    ): void {
        if (strcasecmp($coverageStatus->name ?? '', 'Inactive') !== 0 || ! filled($endDateYmd)) {
            return;
        }

        if ($car->insuranceCoverageNeedsEndDate()) {
            return;
        }

        $car->insurances()->delete();
    }

    private function syncInsuranceCoveragePeriods(
        Car $car,
        ?string $prevStatusName,
        ?int $prevProviderId,
        string $newStatusName,
        ?int $newProviderId,
        ?int $actingUserId,
        ?string $coverageStartDateYmd = null,
        ?string $coverageEndDateYmd = null
    ): void {
        $tenantId = $car->tenant_id;
        $wasActive = $prevStatusName !== null && strcasecmp($prevStatusName, 'Active') === 0;
        $isActiveNow = strcasecmp($newStatusName, 'Active') === 0;

        $startDay = $this->parseInsuranceCoverageDay($coverageStartDateYmd);
        $endDay = $this->parseInsuranceCoverageDay($coverageEndDateYmd);

        if ($wasActive && ! $isActiveNow) {
            $needsPendingEndDate = $endDay === null;
            CarInsuranceCoveragePeriod::where('car_id', $car->id)
                ->whereNull('deactivated_at')
                ->where('end_date_pending', false)
                ->update([
                    'deactivated_at' => $needsPendingEndDate ? null : $endDay,
                    'deactivated_by_user_id' => $actingUserId,
                    'end_date_pending' => $needsPendingEndDate,
                ]);

            return;
        }

        if (! $isActiveNow) {
            $this->applyPendingInsuranceCoverageEndDate($car, $endDay, $actingUserId);

            return;
        }

        if ($wasActive) {
            $prevPid = $prevProviderId !== null ? (int) $prevProviderId : 0;
            $newPid = $newProviderId !== null ? (int) $newProviderId : 0;
            if ($prevPid !== $newPid) {
                CarInsuranceCoveragePeriod::where('car_id', $car->id)
                    ->whereNull('deactivated_at')
                    ->where('end_date_pending', false)
                    ->update([
                        'deactivated_at' => Carbon::now(),
                        'deactivated_by_user_id' => $actingUserId,
                        'end_date_pending' => false,
                    ]);
                CarInsuranceCoveragePeriod::create([
                    'tenant_id' => $tenantId,
                    'car_id' => $car->id,
                    'insurance_provider_id' => $newProviderId,
                    'activated_at' => $startDay ?? Carbon::now(),
                    'deactivated_at' => null,
                    'end_date_pending' => false,
                    'activated_by_user_id' => $actingUserId,
                ]);
            } else {
                $this->updateOpenCoverageActivatedAtIfUnset($car, $startDay);
            }

            return;
        }

        CarInsuranceCoveragePeriod::create([
            'tenant_id' => $tenantId,
            'car_id' => $car->id,
            'insurance_provider_id' => $newProviderId,
            'activated_at' => $startDay,
            'deactivated_at' => null,
            'end_date_pending' => false,
            'activated_by_user_id' => $actingUserId,
        ]);
    }

    private function parseInsuranceCoverageDay(?string $ymd): ?Carbon
    {
        if ($ymd === null || $ymd === '') {
            return null;
        }

        return Carbon::parse($ymd, config('app.timezone'))->startOfDay();
    }

    /**
     * User stayed Inactive but supplied coverage end date to complete a prior inactive-without-end row.
     */
    private function applyPendingInsuranceCoverageEndDate(Car $car, ?Carbon $endDay, ?int $actingUserId): void
    {
        if ($endDay === null) {
            return;
        }

        $period = CarInsuranceCoveragePeriod::where('car_id', $car->id)
            ->where('end_date_pending', true)
            ->orderByDesc('id')
            ->first();

        if ($period === null) {
            return;
        }

        $period->update([
            'deactivated_at' => $endDay,
            'end_date_pending' => false,
            'deactivated_by_user_id' => $actingUserId ?? $period->deactivated_by_user_id,
        ]);
    }

    private function updateOpenCoverageActivatedAtIfUnset(Car $car, ?Carbon $startDay): void
    {
        if ($startDay === null) {
            return;
        }

        $period = CarInsuranceCoveragePeriod::where('car_id', $car->id)
            ->whereNull('deactivated_at')
            ->where('end_date_pending', false)
            ->first();

        if ($period === null || $period->activated_at !== null) {
            return;
        }

        $period->update(['activated_at' => $startDay]);
    }

    private function downloadCarFile(Car $car, string $directory, ?string $filename, string $type)
    {
        $tenant = Auth::user()->currentTenant();
        abort_unless($tenant && $car->tenant_id === $tenant->id, 403);
        abort_unless($filename, 404);

        $path = public_path($directory.'/'.$filename);
        abort_unless(File::exists($path), 404);

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $registration = preg_replace('/[^A-Za-z0-9]/', '', $car->registration);

        return response()->download($path, $registration.'-'.$type.'.'.$extension);
    }

    private function deleteCarFiles($car)
    {
        $filesToDelete = [
            $car->v5_document ? public_path('uploads/cars/'.$car->v5_document) : null,
            $car->old_log_book ? public_path('uploads/cars/log_book/'.$car->old_log_book) : null,
        ];

        foreach ($car->mots as $mot) {
            if ($mot->document) {
                $filesToDelete[] = public_path('uploads/cars/mot_documents/'.$mot->document);
            }
        }

        foreach ($car->phvs as $phv) {
            if ($phv->document) {
                $filesToDelete[] = public_path('uploads/cars/phv_documents/'.$phv->document);
            }
        }

        foreach ($car->insuranceDocuments as $insuranceDocArch) {
            if ($insuranceDocArch->document) {
                $filesToDelete[] = public_path('uploads/cars/insurance_documents/'.$insuranceDocArch->document);
            }
        }

        foreach ($car->insurances as $insurance) {
            if ($insurance->insurance_document) {
                $filesToDelete[] = public_path('uploads/cars/insurance_documents/'.$insurance->insurance_document);
            }
        }

        foreach ($car->services as $service) {
            if ($service->document) {
                $filesToDelete[] = public_path('uploads/cars/service_documents/'.$service->document);
            }
        }

        foreach (array_filter($filesToDelete) as $filePath) {
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }
    }
}
