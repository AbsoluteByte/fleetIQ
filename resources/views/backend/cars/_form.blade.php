{{-- Basic Information Section --}}
<div class="row">
    <div class="col-12">
        <h5 class="mb-2"><i class="fa fa-info-circle"></i> Basic Information</h5>
    </div>

    {{-- Company Selection --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="company_id">Select Company <span class="text-danger">*</span></label>
            <select name="company_id" id="company_id" class="form-control @error('company_id') is-invalid @enderror" required>
                <option value="">Select Company</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}"
                        {{ (old('company_id') ?? (isset($model) && $model->id ? $model->company_id : '')) == $company->id ? 'selected' : '' }}>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>
            @error('company_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Car Model Selection --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="car_model_id">Make/Model <span class="text-danger">*</span></label>
            <select name="car_model_id" id="car_model_id" class="form-control @error('car_model_id') is-invalid @enderror" required>
                <option value="">Select Model</option>
                @foreach($carModels as $carModel)
                    <option value="{{ $carModel->id }}"
                        {{ (old('car_model_id') ?? (isset($model) && $model->id ? $model->car_model_id : '')) == $carModel->id ? 'selected' : '' }}>
                        {{ $carModel->name }}
                    </option>
                @endforeach
            </select>
            @error('car_model_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Registration --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="registration">Registration <span class="text-danger">*</span></label>
            <input type="text" name="registration" id="registration"
                   class="form-control @error('registration') is-invalid @enderror"
                   value="{{ old('registration') ?? (isset($model) && $model->id ? $model->registration : '') }}"
                   placeholder="e.g. AB12 CDE" required>
            @error('registration')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Color --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="color">Color <span class="text-danger">*</span></label>
            <select name="color" id="color" class="form-control @error('color') is-invalid @enderror" required>
                <option value="">Select Color</option>
                @php
                    $colors = ['Black', 'White', 'Silver', 'Blue', 'Red', 'Grey', 'Green', 'Yellow', 'Orange', 'Purple', 'Brown', 'Gold'];
                    $selectedColor = old('color') ?? (isset($model) && $model->id ? $model->color : '');
                @endphp
                @foreach($colors as $color)
                    <option value="{{ $color }}" {{ $selectedColor == $color ? 'selected' : '' }}>{{ $color }}</option>
                @endforeach
            </select>
            @error('color')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- VIN --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="vin">VIN Number <span class="text-danger">*</span></label>
            <input type="text" name="vin" id="vin"
                   class="form-control @error('vin') is-invalid @enderror"
                   value="{{ old('vin') ?? (isset($model) && $model->id ? $model->vin : '') }}"
                   placeholder="17-character VIN" required>
            @error('vin')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- V5 Document --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="v5_document">V5 Document</label>
            <input type="file" name="v5_document" id="v5_document"
                   class="form-control @error('v5_document') is-invalid @enderror"
                   accept=".pdf,.jpg,.jpeg,.png">
            @if(isset($model) && $model->id && $model->v5_document)
                <small class="text-muted">Current: <a href="{{ route('cars.download.v5', $model) }}" target="_blank">Download Document</a></small>
            @endif
            @error('v5_document')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Manufacture Year --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="manufacture_year">Manufacture Year <span class="text-danger">*</span></label>
            <input type="number" name="manufacture_year" id="manufacture_year"
                   class="form-control @error('manufacture_year') is-invalid @enderror"
                   value="{{ old('manufacture_year') ?? (isset($model) && $model->id ? $model->manufacture_year : '') }}"
                   min="1900" max="{{ date('Y') }}" required>
            @error('manufacture_year')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Registration Year --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="registration_year">Registration Year <span class="text-danger">*</span></label>
            <input type="number" name="registration_year" id="registration_year"
                   class="form-control @error('registration_year') is-invalid @enderror"
                   value="{{ old('registration_year') ?? (isset($model) && $model->id ? $model->registration_year : '') }}"
                   min="1900" max="{{ date('Y') }}" required>
            @error('registration_year')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Purchase Date --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="purchase_date">Purchase Date <span class="text-danger">*</span></label>
            <input type="date" name="purchase_date" id="purchase_date"
                   class="form-control @error('purchase_date') is-invalid @enderror"
                   value="{{ old('purchase_date') ?? (isset($model) && $model->id && $model->purchase_date ? $model->purchase_date->format('Y-m-d') : '') }}" required>
            @error('purchase_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Purchase Price --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="purchase_price">Purchase Price <span class="text-danger">*</span></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text">£</span>
                </div>
                <input type="number" name="purchase_price" id="purchase_price"
                       class="form-control @error('purchase_price') is-invalid @enderror"
                       value="{{ old('purchase_price') ?? (isset($model) && $model->id ? $model->purchase_price : '') }}"
                       step="0.01" min="0" required>
                @error('purchase_price')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- Purchase Type --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="purchase_type">Purchase Type <span class="text-danger">*</span></label>
            <select name="purchase_type" id="purchase_type" class="form-control @error('purchase_type') is-invalid @enderror" required>
                <option value="">Select Type</option>
                <option value="imported" {{ (old('purchase_type') ?? (isset($model) && $model->id ? $model->purchase_type : '')) == 'imported' ? 'selected' : '' }}>Imported</option>
                <option value="uk" {{ (old('purchase_type') ?? (isset($model) && $model->id ? $model->purchase_type : '')) == 'uk' ? 'selected' : '' }}>UK</option>
            </select>
            @error('purchase_type')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Seller Name --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="seller_name">Seller Name</label>
            <input type="text" name="seller_name" id="seller_name"
                   class="form-control @error('seller_name') is-invalid @enderror"
                   value="{{ old('seller_name') ?? (isset($model) && $model->id ? $model->seller_name : '') }}"
                   placeholder="e.g. John Smith (optional)">
            @error('seller_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @php
        $fleetStatus = old('fleet_status', isset($model) && $model->id ? ($model->fleet_status ?? 'available_for_rent') : 'available_for_rent');
    @endphp
    <div class="col-md-6">
        <div class="form-group">
            <label for="fleet_status">Fleet Status</label>
            <select name="fleet_status" id="fleet_status" class="form-control @error('fleet_status') is-invalid @enderror">
                <option value="available_for_rent" {{ $fleetStatus === 'available_for_rent' ? 'selected' : '' }}>Available for rent</option>
                <option value="damaged" {{ $fleetStatus === 'damaged' ? 'selected' : '' }}>Damaged</option>
                <option value="written_off" {{ $fleetStatus === 'written_off' ? 'selected' : '' }}>Written off</option>
                <option value="stolen" {{ $fleetStatus === 'stolen' ? 'selected' : '' }}>Stolen</option>
                <option value="for_sale" {{ $fleetStatus === 'for_sale' ? 'selected' : '' }}>For sale</option>
                <option value="sold" {{ $fleetStatus === 'sold' ? 'selected' : '' }}>Sold</option>
                <option value="reserved" {{ $fleetStatus === 'reserved' ? 'selected' : '' }}>Reserved</option>
            </select>
            @error('fleet_status')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="available_from_date">Available From</label>
            <input type="date" name="available_from_date" id="available_from_date"
                class="form-control @error('available_from_date') is-invalid @enderror"
                value="{{ old('available_from_date') ?? (isset($model) && $model->id && $model->available_from_date ? $model->available_from_date->format('Y-m-d') : '') }}">
            @error('available_from_date')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="seller_notes">Seller Notes</label>
            <textarea name="seller_notes" id="seller_notes" rows="3" placeholder="Optional"
                class="form-control @error('seller_notes') is-invalid @enderror">{{ old('seller_notes', isset($model) && $model->id ? ($model->seller_notes ?? '') : '') }}</textarea>
            @error('seller_notes')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="form-group mb-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="log_book_applied" name="log_book_applied" value="1"
                    {{ old('log_book_applied', $model->log_book_applied ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="log_book_applied">Log book applied</label>
            </div>
        </div>
    </div>
    <div class="col-12" id="log-book-section"
        style="display: {{ old('log_book_applied', $model->log_book_applied ?? false) ? 'block' : 'none' }};">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="log_book_applied_date">Applied Date</label>
                    @php
                        $logBookDate = old('log_book_applied_date');
                        if ($logBookDate === null) {
                            if (isset($model) && $model->id && $model->log_book_applied_date) {
                                $logBookDate = $model->log_book_applied_date->format('Y-m-d');
                            } else {
                                $logBookDate = '';
                            }
                        }
                    @endphp
                    <input type="date" name="log_book_applied_date" id="log_book_applied_date"
                        class="form-control @error('log_book_applied_date') is-invalid @enderror"
                        value="{{ $logBookDate }}">
                    @error('log_book_applied_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="old_log_book">Old log book</label>
                    <input type="file" name="old_log_book" id="old_log_book"
                        class="form-control @error('old_log_book') is-invalid @enderror"
                        accept=".pdf,.jpg,.jpeg,.png">
                    @if(isset($model) && $model->id && $model->old_log_book)
                        <small class="text-muted">Current: <a href="{{ asset('uploads/cars/log_book/' . $model->old_log_book) }}" target="_blank">View file</a></small>
                    @endif
                    @error('old_log_book')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $isCarEdit = isset($model) && $model->id;
    if (is_array($oldMots = old('mots'))) {
        $motsForMain = collect($oldMots)->values();
        if ($motsForMain->isEmpty()) {
            $motsForMain = collect([[]]);
        }
        $motsOlder = collect();
        $useMotsSplit = false;
    } elseif ($isCarEdit && $model->mots->isNotEmpty()) {
        $motsForMain = $model->mots->take(1);
        $motsOlder = $model->mots->slice(1)->values();
        $useMotsSplit = true;
    } else {
        $motsForMain = collect([[]]);
        $motsOlder = collect();
        $useMotsSplit = false;
    }
    $showMotViewAll = $isCarEdit && $useMotsSplit && $motsOlder->isNotEmpty();
    $motMainCount = $motsForMain->count();
    $motHiddenStartIndex = $motMainCount;
@endphp

{{-- MOT Information Section --}}
<div class="row mt-1">
    <div class="col-12">
        <h5 class="mb-1 d-flex flex-wrap align-items-center justify-content-between">
            <span>
                <i class="fa fa-tools"></i> MOT Information
            </span>
            <span>
                @if($showMotViewAll)
                <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#editMotHistoryModal">
                    View All
                </button>
                @endif
                <button type="button" class="btn btn-sm btn-success" onclick="addMOT()">
                    <i class="fa fa-plus"></i> Add MOT
                </button>
            </span>
        </h5>

        <div class="card">
            <div class="card-body">
                <div id="mots-container">
                    @foreach($motsForMain as $index => $mot)
                        <div class="mot-item row border-bottom pb-3 mb-1" data-index="{{ $index }}">
                            @if(isset($mot->id))
                                <input type="hidden" name="mots[{{ $index }}][id]" value="{{ $mot->id }}">
                            @endif

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" name="mots[{{ $index }}][expiry_date]"
                                           class="form-control @error('mots.'.$index.'.expiry_date') is-invalid @enderror"
                                           value="{{ old('mots.'.$index.'.expiry_date') ?? (isset($mot['expiry_date']) ? \Carbon\Carbon::parse($mot['expiry_date'])->format('Y-m-d') : '') }}"
                                           required>
                                    @error('mots.'.$index.'.expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">£</span>
                                        </div>
                                        <input type="number" name="mots[{{ $index }}][amount]"
                                               class="form-control @error('mots.'.$index.'.amount') is-invalid @enderror"
                                               value="{{ old('mots.'.$index.'.amount') ?? (is_object($mot) && isset($mot->amount) ? $mot->amount : ($mot['amount'] ?? '')) }}"
                                               step="0.01" min="0" required>
                                        @error('mots.'.$index.'.amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Term <span class="text-danger">*</span></label>
                                    <input type="text" name="mots[{{ $index }}][term]"
                                           class="form-control @error('mots.'.$index.'.term') is-invalid @enderror"
                                           value="{{ old('mots.'.$index.'.term') ?? (is_object($mot) && isset($mot->term) ? $mot->term : ($mot['term'] ?? '')) }}"
                                           placeholder="e.g. 12 months" required>
                                    @error('mots.'.$index.'.term')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Document</label>
                                    <input type="file" name="mots[{{ $index }}][document]"
                                           class="form-control @error('mots.'.$index.'.document') is-invalid @enderror"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    @if((is_object($mot) && $mot->document) || (isset($mot['document']) && $mot['document']))
                                        <small class="text-muted">Current:
                                            @if(isset($model) && $model->id && is_object($mot) && isset($mot->id))
                                                <a href="{{ route('cars.mots.download', [$model, $mot->id]) }}" target="_blank">Download</a>
                                            @else
                                                <a href="{{ asset('uploads/cars/mot_documents/' . (is_object($mot) ? $mot->document : $mot['document'])) }}" target="_blank">View</a>
                                            @endif
                                        </small>
                                    @endif
                                    @error('mots.'.$index.'.document')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-1">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        @if($index > 0)
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeMOT(this)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="mots-preserved" class="d-none">
                    @if($isCarEdit && $useMotsSplit && $motsOlder->isNotEmpty())
                        @php $hMot = $motHiddenStartIndex; @endphp
                        @foreach($motsOlder as $motP)
                            <div class="mot-preserved" data-record-id="{{ $motP->id }}">
                                <input type="hidden" name="mots[{{ $hMot }}][id]" value="{{ $motP->id }}">
                                <input type="hidden" name="mots[{{ $hMot }}][expiry_date]" value="{{ $motP->expiry_date->format('Y-m-d') }}">
                                <input type="hidden" name="mots[{{ $hMot }}][amount]" value="{{ $motP->amount }}">
                                <input type="hidden" name="mots[{{ $hMot }}][term]" value="{{ e($motP->term) }}">
                            </div>
                            @php $hMot++; @endphp
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($showMotViewAll)
<div class="modal fade" id="editMotHistoryModal" tabindex="-1" role="dialog" aria-labelledby="editMotHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMotHistoryModalLabel">Previous MOT records</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Expiry Date</th>
                                <th>Amount</th>
                                <th>Term</th>
                                <th>Document</th>
                                <th class="text-right" style="width:80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($motsOlder as $motH)
                            <tr data-hist-mot-id="{{ $motH->id }}">
                                <td>{{ $motH->expiry_date->format('d M, Y') }}</td>
                                <td>£{{ number_format($motH->amount, 2) }}</td>
                                <td>{{ $motH->term }}</td>
                                <td>
                                    @if($motH->document)
                                        <a href="{{ route('cars.mots.download', [$model, $motH->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary">Download</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCarHistoryMot({{ $model->id }}, {{ $motH->id }})" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@php
    if (is_array($oldRts = old('road_taxes'))) {
        $roadTaxesForMain = collect($oldRts)->values();
        if ($roadTaxesForMain->isEmpty()) {
            $roadTaxesForMain = collect([[]]);
        }
        $roadTaxesOlder = collect();
        $useRoadTaxSplit = false;
    } elseif ($isCarEdit && $model->roadTaxes->isNotEmpty()) {
        $roadTaxesForMain = $model->roadTaxes->take(1);
        $roadTaxesOlder = $model->roadTaxes->slice(1)->values();
        $useRoadTaxSplit = true;
    } else {
        $roadTaxesForMain = collect([[]]);
        $roadTaxesOlder = collect();
        $useRoadTaxSplit = false;
    }
    $showRoadTaxViewAll = $isCarEdit && $useRoadTaxSplit && $roadTaxesOlder->isNotEmpty();
    $rtMainCount = $roadTaxesForMain->count();
    $rtHiddenStartIndex = $rtMainCount;
@endphp

{{-- Road Tax Information Section --}}
<div class="row mt-1">
    <div class="col-12">
        <h5 class="mb-1 d-flex flex-wrap align-items-center justify-content-between">
            <span>
                <i class="fa fa-road"></i> Road Tax Information
            </span>
            <span>
                @if($showRoadTaxViewAll)
                <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#editRoadTaxHistoryModal">
                    View All
                </button>
                @endif
                @if($isCarEdit)
                    @if($model->sorn_applied)
                        <button type="button" class="btn btn-sm btn-success mr-1" data-toggle="modal" data-target="#sornDetailsModal" title="View SORN details">
                            <i class="fa fa-check"></i> SORN Applied
                        </button>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-success mr-1" data-toggle="modal" data-target="#applySornModal">
                            <i class="fa fa-road"></i> Apply SORN
                        </button>
                    @endif
                @endif
                <button type="button" class="btn btn-sm btn-success" onclick="addRoadTax()">
                    <i class="fa fa-plus"></i> Add Road Tax
                </button>
            </span>
        </h5>

        <div class="card">
            <div class="card-body">
                <div id="roadtax-container">
                    @foreach($roadTaxesForMain as $index => $roadTax)
                        <div class="roadtax-item row border-bottom pb-3 mb-1" data-index="{{ $index }}">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Start Date <span class="text-danger">*</span></label>
                                    <input type="date" name="road_taxes[{{ $index }}][start_date]"
                                           class="form-control @error('road_taxes.'.$index.'.start_date') is-invalid @enderror"
                                           value="{{ old('road_taxes.'.$index.'.start_date') ?? (isset($roadTax['start_date']) ? \Carbon\Carbon::parse($roadTax['start_date'])->format('Y-m-d') : '') }}"
                                           required>
                                    @error('road_taxes.'.$index.'.start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Term <span class="text-danger">*</span></label>
                                    <select name="road_taxes[{{ $index }}][term]"
                                            class="form-control @error('road_taxes.'.$index.'.term') is-invalid @enderror"
                                            required>
                                        <option value="">Select Term</option>
                                        @php
                                            $selectedTerm = old('road_taxes.'.$index.'.term') ?? (is_object($roadTax) && isset($roadTax->term) ? $roadTax->term : ($roadTax['term'] ?? ''));
                                        @endphp
                                        <option value="6 months" {{ $selectedTerm == '6 months' ? 'selected' : '' }}>6 Months</option>
                                        <option value="12 months" {{ $selectedTerm == '12 months' ? 'selected' : '' }}>12 Months</option>
                                    </select>
                                    @error('road_taxes.'.$index.'.term')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">£</span>
                                        </div>
                                        <input type="number" name="road_taxes[{{ $index }}][amount]"
                                               class="form-control @error('road_taxes.'.$index.'.amount') is-invalid @enderror"
                                               value="{{ old('road_taxes.'.$index.'.amount') ?? (is_object($roadTax) && isset($roadTax->amount) ? $roadTax->amount : ($roadTax['amount'] ?? '')) }}"
                                               step="0.01" min="0" required>
                                        @error('road_taxes.'.$index.'.amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        @if($index > 0)
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRoadTax(this)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="roadtax-preserved" class="d-none">
                    @if($isCarEdit && $useRoadTaxSplit && $roadTaxesOlder->isNotEmpty())
                        @php $hRt = $rtHiddenStartIndex; @endphp
                        @foreach($roadTaxesOlder as $rtP)
                            <div class="roadtax-preserved" data-record-id="{{ $rtP->id }}">
                                <input type="hidden" name="road_taxes[{{ $hRt }}][start_date]" value="{{ $rtP->start_date->format('Y-m-d') }}">
                                <input type="hidden" name="road_taxes[{{ $hRt }}][term]" value="{{ e($rtP->term) }}">
                                <input type="hidden" name="road_taxes[{{ $hRt }}][amount]" value="{{ $rtP->amount }}">
                            </div>
                            @php $hRt++; @endphp
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($isCarEdit && ! $model->sorn_applied)
<div class="modal fade" id="applySornModal" tabindex="-1" role="dialog" aria-labelledby="applySornModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="applySornModalLabel">
                    <i class="fa fa-road text-success mr-50"></i> Apply SORN
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-body">Are you sure you want to apply SORN for this car?</p>
                <p class="small text-muted mt-1 mb-0">If you continue, the vehicle will be recorded as off the road in FleetIQ and you will be redirected to <strong>GOV.UK</strong> to complete the statutory notification (SORN) where required.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="applySornConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>
@endif

@if($isCarEdit && $model->sorn_applied)
@php
    $sornDetailsWho = $model->sornAppliedBy?->name;
    $sornDetailsWhen = $model->sorn_applied_at
        ? $model->sorn_applied_at->format('d M Y') . ' at ' . $model->sorn_applied_at->format('h:i A')
        : null;
@endphp
<div class="modal fade" id="sornDetailsModal" tabindex="-1" role="dialog" aria-labelledby="sornDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 32rem;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="sornDetailsModalLabel">
                    <i class="fa fa-check-circle text-success mr-50"></i> SORN applied
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-body" style="line-height: 1.65;">
                    @if($sornDetailsWho)
                        <strong>{{ $sornDetailsWho }}</strong> applied for SORN for this car
                        @if($sornDetailsWhen)
                            on <strong>{{ $sornDetailsWhen }}</strong>
                        @endif
                        .
                    @else
                        SORN was recorded for this car
                        @if($sornDetailsWhen)
                            on <strong>{{ $sornDetailsWhen }}</strong>
                        @endif
                        .
                    @endif
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif

@if($isCarEdit)
<div id="sornApplyOverlay" class="d-none" style="position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,0.55);align-items:center;justify-content:center;flex-direction:column;">
    <div class="bg-white rounded shadow p-4 text-center" style="min-width:260px;border-radius:12px;">
        <div class="spinner-border text-success mb-3" role="status" aria-hidden="true"></div>
        <div class="font-weight-500 text-dark">Saving…</div>
        <div class="small text-muted mt-1">Please wait</div>
    </div>
</div>
@endif

@if($showRoadTaxViewAll)
<div class="modal fade" id="editRoadTaxHistoryModal" tabindex="-1" role="dialog" aria-labelledby="editRoadTaxHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRoadTaxHistoryModalLabel">Previous road tax records</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Start Date</th>
                                <th>Term</th>
                                <th>Amount</th>
                                <th class="text-right" style="width:80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roadTaxesOlder as $rtH)
                            <tr data-hist-rt-id="{{ $rtH->id }}">
                                <td>{{ $rtH->start_date->format('d M, Y') }}</td>
                                <td>{{ $rtH->term }}</td>
                                <td>£{{ number_format($rtH->amount, 2) }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCarHistoryRoadTax({{ $model->id }}, {{ $rtH->id }})" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@php
    if (is_array($oldPhvs = old('phvs'))) {
        $phvsForMain = collect($oldPhvs)->values();
        if ($phvsForMain->isEmpty()) {
            $phvsForMain = collect([[]]);
        }
        $phvsOlder = collect();
        $usePhvSplit = false;
    } elseif ($isCarEdit && $model->phvs->isNotEmpty()) {
        $phvsForMain = $model->phvs->take(1);
        $phvsOlder = $model->phvs->slice(1)->values();
        $usePhvSplit = true;
    } else {
        $phvsForMain = collect([[]]);
        $phvsOlder = collect();
        $usePhvSplit = false;
    }
    $showPhvViewAll = $isCarEdit && $usePhvSplit && $phvsOlder->isNotEmpty();
    $phvMainCount = $phvsForMain->count();
    $phvHiddenStartIndex = $phvMainCount;
@endphp

{{-- PHV Information Section --}}
<div class="row mt-1">
    <div class="col-12">
        <h5 class="mb-1 d-flex flex-wrap align-items-center justify-content-between">
            <span>
                <i class="fa fa-taxi"></i> PHV Information
            </span>
            <span>
                @if($showPhvViewAll)
                <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#editPhvHistoryModal">
                    View All
                </button>
                @endif
                <button type="button" class="btn btn-sm btn-success" onclick="addPHV()">
                    <i class="fa fa-plus"></i> Add PHV
                </button>
            </span>
        </h5>

        <div class="card">
            <div class="card-body">
                <div id="phv-container">
                    @foreach($phvsForMain as $index => $phv)
                        <div class="phv-item row border-bottom pb-3 mb-1" data-index="{{ $index }}">
                            @if(isset($phv->id))
                                <input type="hidden" name="phvs[{{ $index }}][id]" value="{{ $phv->id }}">
                            @endif

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Counsel <span class="text-danger">*</span></label>
                                    <select name="phvs[{{ $index }}][counsel_id]"
                                            class="form-control @error('phvs.'.$index.'.counsel_id') is-invalid @enderror"
                                            required>
                                        <option value="">Select Counsel</option>
                                        @foreach($counsels as $counsel)
                                            @php
                                                $selectedCounsel = old('phvs.'.$index.'.counsel_id') ?? (is_object($phv) && isset($phv->counsel_id) ? $phv->counsel_id : ($phv['counsel_id'] ?? ''));
                                            @endphp
                                            <option value="{{ $counsel->id }}" {{ $selectedCounsel == $counsel->id ? 'selected' : '' }}>
                                                {{ $counsel->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('phvs.'.$index.'.counsel_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">£</span>
                                        </div>
                                        <input type="number" name="phvs[{{ $index }}][amount]"
                                               class="form-control @error('phvs.'.$index.'.amount') is-invalid @enderror"
                                               value="{{ old('phvs.'.$index.'.amount') ?? (is_object($phv) && isset($phv->amount) ? $phv->amount : ($phv['amount'] ?? '')) }}"
                                               step="0.01" min="0" required>
                                        @error('phvs.'.$index.'.amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Start Date <span class="text-danger">*</span></label>
                                    <input type="date" name="phvs[{{ $index }}][start_date]"
                                           class="form-control @error('phvs.'.$index.'.start_date') is-invalid @enderror"
                                           value="{{ old('phvs.'.$index.'.start_date') ?? (isset($phv['start_date']) ? \Carbon\Carbon::parse($phv['start_date'])->format('Y-m-d') : (is_object($phv) && $phv->start_date ? $phv->start_date->format('Y-m-d') : '')) }}"
                                           required>
                                    @error('phvs.'.$index.'.start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" name="phvs[{{ $index }}][expiry_date]"
                                           class="form-control @error('phvs.'.$index.'.expiry_date') is-invalid @enderror"
                                           value="{{ old('phvs.'.$index.'.expiry_date') ?? (isset($phv['expiry_date']) ? \Carbon\Carbon::parse($phv['expiry_date'])->format('Y-m-d') : (is_object($phv) && $phv->expiry_date ? $phv->expiry_date->format('Y-m-d') : '')) }}"
                                           required>
                                    @error('phvs.'.$index.'.expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Notify (days) <span class="text-danger">*</span></label>
                                    <input type="number" name="phvs[{{ $index }}][notify_before_expiry]"
                                           class="form-control @error('phvs.'.$index.'.notify_before_expiry') is-invalid @enderror"
                                           value="{{ old('phvs.'.$index.'.notify_before_expiry') ?? (is_object($phv) && isset($phv->notify_before_expiry) ? $phv->notify_before_expiry : ($phv['notify_before_expiry'] ?? '')) }}"
                                           min="1" required>
                                    @error('phvs.'.$index.'.notify_before_expiry')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Document</label>
                                    <input type="file" name="phvs[{{ $index }}][document]"
                                           class="form-control @error('phvs.'.$index.'.document') is-invalid @enderror"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    @if((is_object($phv) && $phv->document) || (isset($phv['document']) && $phv['document']))
                                        <small class="text-muted">Current:
                                            @if(isset($model) && $model->id && is_object($phv) && isset($phv->id))
                                                <a href="{{ route('cars.phvs.download', [$model, $phv->id]) }}" target="_blank">Download</a>
                                            @else
                                                <a href="{{ asset('uploads/cars/phv_documents/' . (is_object($phv) ? $phv->document : $phv['document'])) }}" target="_blank">View</a>
                                            @endif
                                        </small>
                                    @endif
                                    @error('phvs.'.$index.'.document')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group mb-2">
                                    <label>&nbsp;</label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input phv-applied-checkbox"
                                               name="phvs[{{ $index }}][phv_applied]" value="1"
                                               {{ old('phvs.'.$index.'.phv_applied', is_object($phv) && ($phv->phv_applied ?? false)) ? 'checked' : '' }}>
                                        <label class="form-check-label">PHV applied</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>PHV Applied Date</label>
                                    <input type="date" name="phvs[{{ $index }}][phv_applied_date]"
                                           class="form-control phv-applied-date @error('phvs.'.$index.'.phv_applied_date') is-invalid @enderror"
                                           value="{{ old('phvs.'.$index.'.phv_applied_date') ?? (is_object($phv) && $phv->phv_applied_date ? $phv->phv_applied_date->format('Y-m-d') : '') }}">
                                    @error('phvs.'.$index.'.phv_applied_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-1">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        @if($index > 0)
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removePHV(this)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="phv-preserved" class="d-none">
                    @if($isCarEdit && $usePhvSplit && $phvsOlder->isNotEmpty())
                        @php $hPhv = $phvHiddenStartIndex; @endphp
                        @foreach($phvsOlder as $phvP)
                            <div class="phv-preserved" data-record-id="{{ $phvP->id }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][id]" value="{{ $phvP->id }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][counsel_id]" value="{{ $phvP->counsel_id }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][amount]" value="{{ $phvP->amount }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][start_date]" value="{{ $phvP->start_date->format('Y-m-d') }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][expiry_date]" value="{{ $phvP->expiry_date->format('Y-m-d') }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][notify_before_expiry]" value="{{ $phvP->notify_before_expiry }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][phv_applied]" value="{{ $phvP->phv_applied ? 1 : 0 }}">
                                <input type="hidden" name="phvs[{{ $hPhv }}][phv_applied_date]" value="{{ $phvP->phv_applied_date ? $phvP->phv_applied_date->format('Y-m-d') : '' }}">
                            </div>
                            @php $hPhv++; @endphp
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($showPhvViewAll)
<div class="modal fade" id="editPhvHistoryModal" tabindex="-1" role="dialog" aria-labelledby="editPhvHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPhvHistoryModalLabel">Previous PHV records</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Counsel</th>
                                <th>Start</th>
                                <th>Expiry</th>
                                <th>Amount</th>
                                <th>Notify</th>
                                <th>Applied</th>
                                <th>Document</th>
                                <th class="text-right" style="width:80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($phvsOlder as $phvH)
                            <tr data-hist-phv-id="{{ $phvH->id }}">
                                <td>{{ $phvH->counsel->name ?? 'N/A' }}</td>
                                <td>{{ $phvH->start_date->format('d M, Y') }}</td>
                                <td>{{ $phvH->expiry_date->format('d M, Y') }}</td>
                                <td>£{{ number_format($phvH->amount, 2) }}</td>
                                <td>{{ $phvH->notify_before_expiry }} days</td>
                                <td>
                                    {{ $phvH->phv_applied ? 'Yes' : 'No' }}
                                    @if($phvH->phv_applied_date)
                                        <br><small>{{ $phvH->phv_applied_date->format('d M, Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($phvH->document)
                                        <a href="{{ route('cars.phvs.download', [$model, $phvH->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary">Download</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCarHistoryPhv({{ $model->id }}, {{ $phvH->id }})" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@php
    $latestInsuranceForForm = null;
    $olderInsurancesForModal = collect();
    $showInsuranceViewAll = false;
    if (isset($model) && $model->id && $model->insurances->isNotEmpty()) {
        $olderInsurancesForModal = $model->insurances->count() > 1 ? $model->insurances->slice(1)->values() : collect();
        $showInsuranceViewAll = $olderInsurancesForModal->isNotEmpty();
        $latestInsuranceForForm = $model->insurances->first();
    }
@endphp

{{-- Insurance Information Section - OPTIONAL --}}
<div class="row mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="d-flex flex-wrap align-items-center mb-1 mb-md-0">
                        <h5 class="card-title mb-0 mr-3">
                            <i class="fa fa-shield-alt"></i> Insurance Information
                        </h5>
                        <div class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="has_insurance" name="has_insurance"
                                {{ (old('has_insurance') ?? (isset($model) && $model->id && $model->insurances->count() > 0)) ? 'checked' : '' }}>
                            <label class="form-check-label" for="has_insurance">
                                <strong>Add Insurance</strong>
                            </label>
                        </div>
                    </div>
                </div>

                @if(isset($model) && $model->id && $showInsuranceViewAll)
                    <button type="button" class="btn btn-sm btn-outline-primary mb-1 mb-md-0" data-toggle="modal" data-target="#editInsuranceHistoryModal">
                        View All
                    </button>
                @endif
                
            </div>
            <div class="card-body" id="insurance-section" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="insurance_provider_id">Insurance Provider</label>
                            <select name="insurance_provider_id" id="insurance_provider_id" class="form-control @error('insurance_provider_id') is-invalid @enderror">
                                <option value="">Select Provider</option>
                                @foreach($insuranceProviders as $provider)
                                    <option value="{{ $provider->id }}"
                                            data-company-id="{{ $provider->company_id }}"
                                        {{ (old('insurance_provider_id') ?? ($latestInsuranceForForm ? $latestInsuranceForForm->insurance_provider_id : '')) == $provider->id ? 'selected' : '' }}>
                                        {{ $provider->provider_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('insurance_provider_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="insurance_status_id">Status</label>
                            <select name="insurance_status_id" id="insurance_status_id" class="form-control @error('insurance_status_id') is-invalid @enderror">
                                <option value="">Select Status</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}"
                                        {{ (old('insurance_status_id') ?? ($latestInsuranceForForm ? $latestInsuranceForForm->status_id : '')) == $status->id ? 'selected' : '' }}>
                                        {{ $status->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('insurance_status_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="insurance_start_date">Start Date</label>
                            <input type="date" name="insurance_start_date" id="insurance_start_date"
                                   class="form-control @error('insurance_start_date') is-invalid @enderror"
                                   value="{{ old('insurance_start_date') ?? ($latestInsuranceForForm ? $latestInsuranceForForm->start_date->format('Y-m-d') : '') }}">
                            @error('insurance_start_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="insurance_expiry_date">Expiry Date</label>
                            <input type="date" name="insurance_expiry_date" id="insurance_expiry_date"
                                   class="form-control @error('insurance_expiry_date') is-invalid @enderror"
                                   value="{{ old('insurance_expiry_date') ?? ($latestInsuranceForForm ? $latestInsuranceForForm->expiry_date->format('Y-m-d') : '') }}">
                            @error('insurance_expiry_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="insurance_notify_before_expiry">Notify Before Expiry (days)</label>
                            <input type="number" name="insurance_notify_before_expiry" id="insurance_notify_before_expiry"
                                   class="form-control @error('insurance_notify_before_expiry') is-invalid @enderror"
                                   value="{{ old('insurance_notify_before_expiry') ?? ($latestInsuranceForForm ? $latestInsuranceForForm->notify_before_expiry : '30') }}"
                                   min="1">
                            @error('insurance_notify_before_expiry')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="insurance_document">Insurance Document</label>
                            <input type="file" name="insurance_document" id="insurance_document"
                                   class="form-control @error('insurance_document') is-invalid @enderror"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            @if($latestInsuranceForForm && $latestInsuranceForForm->insurance_document)
                                <small class="text-muted">Current: <a href="{{ asset('uploads/cars/insurance_documents/' . $latestInsuranceForForm->insurance_document) }}" target="_blank">View Document</a></small>
                            @endif
                            @error('insurance_document')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(isset($model) && $model->id && $showInsuranceViewAll)
<div class="modal fade" id="editInsuranceHistoryModal" tabindex="-1" role="dialog" aria-labelledby="editInsuranceHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editInsuranceHistoryModalLabel">Previous insurance records</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Provider</th>
                                <th>Start</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th>Notify (days)</th>
                                <th>Document</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($olderInsurancesForModal as $insuranceH)
                            <tr>
                                <td>{{ $insuranceH->insuranceProvider->provider_name ?? '—' }}</td>
                                <td>{{ $insuranceH->start_date->format('d M, Y') }}</td>
                                <td>{{ $insuranceH->expiry_date->format('d M, Y') }}</td>
                                <td>{{ $insuranceH->status->name ?? '—' }}</td>
                                <td>{{ $insuranceH->notify_before_expiry }}</td>
                                <td>
                                    @if($insuranceH->insurance_document)
                                        <a href="{{ asset('uploads/cars/insurance_documents/' . $insuranceH->insurance_document) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@php
    $latestServiceForForm = isset($model) && $model->id ? $model->latestService() : null;
    $activeReservationForForm = isset($model) && $model->id ? $model->activeReservation() : null;
    $reserveCarChecked = old('reserve_car', $activeReservationForForm ? 1 : 0);
@endphp

{{-- Service Information --}}
<div class="row mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fa fa-wrench"></i> Service Information</h5>
            </div>
            <div class="card-body">
                @if($latestServiceForForm)
                    <div class="alert alert-info">
                        Latest service: {{ $latestServiceForForm->service_date->format('d M, Y') }}.
                        Next service due: {{ $latestServiceForForm->service_date->copy()->addMonths(3)->format('d M, Y') }}.
                    </div>
                @endif
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="service_date">New Service Date</label>
                            <input type="date" name="service_date" id="service_date" class="form-control @error('service_date') is-invalid @enderror" value="{{ old('service_date') }}">
                            @error('service_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="service_mileage">Mileage</label>
                            <input type="number" name="service_mileage" id="service_mileage" class="form-control @error('service_mileage') is-invalid @enderror" value="{{ old('service_mileage') }}" min="0">
                            @error('service_mileage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="service_document">Service Document</label>
                            <input type="file" name="service_document" id="service_document" class="form-control @error('service_document') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                            @error('service_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label for="service_notes">Service Notes</label>
                            <textarea name="service_notes" id="service_notes" rows="2" class="form-control @error('service_notes') is-invalid @enderror">{{ old('service_notes') }}</textarea>
                            @error('service_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reservation Information --}}
<div class="row mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="reserve_car" name="reserve_car" value="1" {{ $reserveCarChecked ? 'checked' : '' }}>
                    <label class="form-check-label" for="reserve_car"><strong>Reserve this car for a customer</strong></label>
                </div>
            </div>
            <div class="card-body" id="reservation-section" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="reservation_customer_name">Customer Name</label>
                            <input type="text" name="reservation_customer_name" id="reservation_customer_name" class="form-control @error('reservation_customer_name') is-invalid @enderror" value="{{ old('reservation_customer_name', $activeReservationForForm->customer_name ?? '') }}">
                            @error('reservation_customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="reservation_customer_phone">Customer Phone</label>
                            <input type="text" name="reservation_customer_phone" id="reservation_customer_phone" class="form-control @error('reservation_customer_phone') is-invalid @enderror" value="{{ old('reservation_customer_phone', $activeReservationForForm->customer_phone ?? '') }}">
                            @error('reservation_customer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="reservation_customer_email">Customer Email</label>
                            <input type="email" name="reservation_customer_email" id="reservation_customer_email" class="form-control @error('reservation_customer_email') is-invalid @enderror" value="{{ old('reservation_customer_email', $activeReservationForForm->customer_email ?? '') }}">
                            @error('reservation_customer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="reservation_date">Reservation Date</label>
                            <input type="date" name="reservation_date" id="reservation_date" class="form-control @error('reservation_date') is-invalid @enderror" value="{{ old('reservation_date', $activeReservationForForm?->reservation_date?->format('Y-m-d')) }}">
                            @error('reservation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="reservation_available_from_date">Available From</label>
                            <input type="date" name="reservation_available_from_date" id="reservation_available_from_date" class="form-control @error('reservation_available_from_date') is-invalid @enderror" value="{{ old('reservation_available_from_date', $activeReservationForForm?->available_from_date?->format('Y-m-d')) }}">
                            @error('reservation_available_from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label for="reservation_terms_conditions">Terms & Conditions</label>
                            <textarea name="reservation_terms_conditions" id="reservation_terms_conditions" rows="3" class="form-control @error('reservation_terms_conditions') is-invalid @enderror">{{ old('reservation_terms_conditions', $activeReservationForForm->terms_conditions ?? '') }}</textarea>
                            @error('reservation_terms_conditions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Submit Button --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i>
                {{ isset($model) && $model->id ? 'Update Car' : 'Create Car' }}
            </button>
            <a href="{{ route($url . 'index') }}" class="btn btn-secondary ml-2">
                <i class="fa fa-times"></i> Cancel
            </a>
        </div>
    </div>
</div>

@push('js')
    <script>
        let motIndex = {{ $motMainCount + ($useMotsSplit ? $motsOlder->count() : 0) }};
        let roadTaxIndex = {{ $rtMainCount + ($useRoadTaxSplit ? $roadTaxesOlder->count() : 0) }};
        let phvIndex = {{ $phvMainCount + ($usePhvSplit ? $phvsOlder->count() : 0) }};

        const carsApiBase = {!! json_encode(url('/admin/cars')) !!};
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        function deleteCarHistoryMot(carId, motId) {
            if (!confirm('Delete this MOT record?')) return;
            fetch(carsApiBase + '/' + carId + '/mots/' + motId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(function (r) {
                if (!r.ok) throw new Error();
                return r.json();
            }).then(function () {
                window.location.reload();
            }).catch(function () {
                alert('Could not delete this record.');
            });
        }

        function deleteCarHistoryRoadTax(carId, roadTaxId) {
            if (!confirm('Delete this road tax record?')) return;
            fetch(carsApiBase + '/' + carId + '/road-taxes/' + roadTaxId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(function (r) {
                if (!r.ok) throw new Error();
                return r.json();
            }).then(function () {
                window.location.reload();
            }).catch(function () {
                alert('Could not delete this record.');
            });
        }

        function deleteCarHistoryPhv(carId, phvId) {
            if (!confirm('Delete this PHV record?')) return;
            fetch(carsApiBase + '/' + carId + '/phvs/' + phvId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(function (r) {
                if (!r.ok) throw new Error();
                return r.json();
            }).then(function () {
                window.location.reload();
            }).catch(function () {
                alert('Could not delete this record.');
            });
        }

        @if($isCarEdit)
        (function () {
            @if( ! $model->sorn_applied)
            var applySornUrl = {!! json_encode(route('cars.apply-sorn', $model)) !!};
            var sornBtn = document.getElementById('applySornConfirmBtn');
            if (sornBtn) {
                sornBtn.addEventListener('click', function () {
                    var overlay = document.getElementById('sornApplyOverlay');
                    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                        window.jQuery('#applySornModal').modal('hide');
                    } else {
                        var m = document.getElementById('applySornModal');
                        if (m) {
                            m.classList.remove('show');
                            m.setAttribute('aria-hidden', 'true');
                            m.style.display = 'none';
                            document.body.classList.remove('modal-open');
                            var bd = document.querySelectorAll('.modal-backdrop');
                            bd.forEach(function (el) { el.remove(); });
                        }
                    }
                    if (overlay) {
                        overlay.classList.remove('d-none');
                        overlay.classList.add('d-flex');
                    }
                    fetch(applySornUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                    }).then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok) {
                                throw new Error((data && data.message) || 'Request failed');
                            }
                            return data;
                        });
                    }).then(function (data) {
                        if (data.ok && data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            throw new Error();
                        }
                    }).catch(function (err) {
                        if (overlay) {
                            overlay.classList.add('d-none');
                            overlay.classList.remove('d-flex');
                        }
                        alert(err.message || 'Could not apply SORN. Please try again.');
                    });
                });
            }
            @endif
        })();
        @endif

        const todayYmd = new Date().toISOString().slice(0, 10);

        function toggleLogBookSection() {
            const cb = document.getElementById('log_book_applied');
            const section = document.getElementById('log-book-section');
            const dateInput = document.getElementById('log_book_applied_date');
            if (cb.checked) {
                section.style.display = 'block';
                if (dateInput && !dateInput.value) {
                    dateInput.value = todayYmd;
                }
            } else {
                section.style.display = 'none';
            }
        }

        function toggleReservationSection() {
            const cb = document.getElementById('reserve_car');
            const section = document.getElementById('reservation-section');
            const reservationDate = document.getElementById('reservation_date');
            if (!cb || !section) return;
            section.style.display = cb.checked ? 'block' : 'none';
            if (cb.checked && reservationDate && !reservationDate.value) {
                reservationDate.value = todayYmd;
            }
        }

        function bindPhvAppliedDefaults(scope) {
            (scope || document).querySelectorAll('.phv-applied-checkbox').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const row = checkbox.closest('.phv-item');
                    const dateInput = row ? row.querySelector('.phv-applied-date') : null;
                    if (checkbox.checked && dateInput && !dateInput.value) {
                        dateInput.value = todayYmd;
                    }
                });
                checkbox.dispatchEvent(new Event('change'));
            });
        }

        const allInsuranceProviders = @json($insuranceProviders->map(function($provider) {
            return [
                'id' => $provider->id,
                'company_id' => $provider->company_id,
                'provider_name' => $provider->provider_name
            ];
        }));

        function filterInsuranceProviders() {
            const companyId = document.getElementById('company_id').value;
            const insuranceProviderSelect = document.getElementById('insurance_provider_id');
            const selectedProviderId = insuranceProviderSelect.value;

            insuranceProviderSelect.innerHTML = '<option value="">Select Provider</option>';

            if (companyId) {
                const filteredProviders = allInsuranceProviders.filter(provider => provider.company_id == companyId);

                filteredProviders.forEach(provider => {
                    const option = document.createElement('option');
                    option.value = provider.id;
                    option.textContent = provider.provider_name;
                    option.setAttribute('data-company-id', provider.company_id);

                    if (provider.id == selectedProviderId) {
                        option.selected = true;
                    }

                    insuranceProviderSelect.appendChild(option);
                });
            }
        }

        // ✅ Insurance Section Toggle
        function toggleInsuranceSection() {
            const hasInsuranceCheckbox = document.getElementById('has_insurance');
            const insuranceSection = document.getElementById('insurance-section');

            if (hasInsuranceCheckbox.checked) {
                insuranceSection.style.display = 'block';
                document.getElementById('insurance_provider_id').setAttribute('required', 'required');
                document.getElementById('insurance_start_date').setAttribute('required', 'required');
                document.getElementById('insurance_expiry_date').setAttribute('required', 'required');
                document.getElementById('insurance_notify_before_expiry').setAttribute('required', 'required');
                document.getElementById('insurance_status_id').setAttribute('required', 'required');
            } else {
                insuranceSection.style.display = 'none';
                document.getElementById('insurance_provider_id').removeAttribute('required');
                document.getElementById('insurance_start_date').removeAttribute('required');
                document.getElementById('insurance_expiry_date').removeAttribute('required');
                document.getElementById('insurance_notify_before_expiry').removeAttribute('required');
                document.getElementById('insurance_status_id').removeAttribute('required');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterInsuranceProviders();
            toggleInsuranceSection();
            toggleLogBookSection();
            toggleReservationSection();
            bindPhvAppliedDefaults(document);
            (function defaultEmptyAppliedDate() {
                const dateInput = document.getElementById('log_book_applied_date');
                const cb = document.getElementById('log_book_applied');
                if (cb && cb.checked && dateInput && !dateInput.value) {
                    dateInput.value = todayYmd;
                }
            })();

            document.getElementById('company_id').addEventListener('change', filterInsuranceProviders);
            document.getElementById('has_insurance').addEventListener('change', toggleInsuranceSection);
            document.getElementById('log_book_applied').addEventListener('change', toggleLogBookSection);
            document.getElementById('reserve_car').addEventListener('change', toggleReservationSection);
        });

        function addMOT() {
            const container = document.getElementById('mots-container');
            const newMOT = `
        <div class="mot-item row border-bottom pb-3 mb-1" data-index="${motIndex}">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Expiry Date <span class="text-danger">*</span></label>
                    <input type="date" name="mots[${motIndex}][expiry_date]" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">£</span>
                        </div>
                        <input type="number" name="mots[${motIndex}][amount]" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Term <span class="text-danger">*</span></label>
                    <input type="text" name="mots[${motIndex}][term]" class="form-control" placeholder="e.g. 12 months" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Document</label>
                    <input type="file" name="mots[${motIndex}][document]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeMOT(this)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
            container.insertAdjacentHTML('beforeend', newMOT);
            motIndex++;
        }

        function removeMOT(button) {
            button.closest('.mot-item').remove();
        }

        function addRoadTax() {
            const container = document.getElementById('roadtax-container');
            const newRoadTax = `
        <div class="roadtax-item row border-bottom pb-3 mb-1" data-index="${roadTaxIndex}">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="road_taxes[${roadTaxIndex}][start_date]" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Term <span class="text-danger">*</span></label>
                    <select name="road_taxes[${roadTaxIndex}][term]" class="form-control" required>
                        <option value="">Select Term</option>
                        <option value="6 months">6 Months</option>
                        <option value="12 months">12 Months</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">£</span>
                        </div>
                        <input type="number" name="road_taxes[${roadTaxIndex}][amount]" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRoadTax(this)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
            container.insertAdjacentHTML('beforeend', newRoadTax);
            roadTaxIndex++;
        }

        function removeRoadTax(button) {
            button.closest('.roadtax-item').remove();
        }

        function addPHV() {
            const container = document.getElementById('phv-container');
            const counselOptions = @json($counsels->map(function($counsel) {
        return ['id' => $counsel->id, 'name' => $counsel->name];
    }));

            let counselOptionsHtml = '<option value="">Select Counsel</option>';
            counselOptions.forEach(counsel => {
                counselOptionsHtml += `<option value="${counsel.id}">${counsel.name}</option>`;
            });

            const newPHV = `
        <div class="phv-item row border-bottom pb-3 mb-1" data-index="${phvIndex}">
            <div class="col-md-2">
                <div class="form-group">
                    <label>Counsel <span class="text-danger">*</span></label>
                    <select name="phvs[${phvIndex}][counsel_id]" class="form-control" required>
                        ${counselOptionsHtml}
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">£</span>
                        </div>
                        <input type="number" name="phvs[${phvIndex}][amount]" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="phvs[${phvIndex}][start_date]" class="form-control" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Expiry Date <span class="text-danger">*</span></label>
                    <input type="date" name="phvs[${phvIndex}][expiry_date]" class="form-control" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Notify (days) <span class="text-danger">*</span></label>
                    <input type="number" name="phvs[${phvIndex}][notify_before_expiry]" class="form-control" min="1" required>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>Document</label>
                    <input type="file" name="phvs[${phvIndex}][document]" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-2">
                    <label>&nbsp;</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input phv-applied-checkbox" name="phvs[${phvIndex}][phv_applied]" value="1">
                        <label class="form-check-label">PHV applied</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>PHV Applied Date</label>
                    <input type="date" name="phvs[${phvIndex}][phv_applied_date]" class="form-control phv-applied-date">
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removePHV(this)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
            container.insertAdjacentHTML('beforeend', newPHV);
            bindPhvAppliedDefaults(container.lastElementChild);
            phvIndex++;
        }

        function removePHV(button) {
            button.closest('.phv-item').remove();
        }

        // VIN validation
        document.getElementById('vin').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (this.value.length > 17) {
                this.value = this.value.substring(0, 17);
            }
        });

        // Registration formatting
        document.getElementById('registration').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    </script>
@endpush
