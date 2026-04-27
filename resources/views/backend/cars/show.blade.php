@extends('layouts.admin', ['title' => 'Car Details'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Car Details - {{ $car->registration }}</h3>
                        <div>
                            <a href="{{ route($url . 'edit', $car->id) }}" class="btn btn-primary btn-sm">
                                <i class="fa fa-edit"></i> Edit
                            </a>
                            <a href="{{ route($url . 'index') }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="border-bottom pb-2 mb-3">Basic Information</h4>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Company:</strong>
                                <p class="mb-0">{{ $car->company->name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Car Model:</strong>
                                <p class="mb-0">{{ $car->carModel->name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Registration:</strong>
                                <p class="mb-0">{{ $car->registration }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Color:</strong>
                                <p class="mb-0">{{ $car->color }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>VIN:</strong>
                                <p class="mb-0">{{ $car->vin }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Manufacture Year:</strong>
                                <p class="mb-0">{{ $car->manufacture_year }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Registration Year:</strong>
                                <p class="mb-0">{{ $car->registration_year }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Purchase Date:</strong>
                                <p class="mb-0">{{ \Carbon\Carbon::parse($car->purchase_date)->format('d M, Y') }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Purchase Price:</strong>
                                <p class="mb-0">£{{ number_format($car->purchase_price, 2) }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Purchase Type:</strong>
                                <p class="mb-0">
                                <span class="badge badge-{{ $car->purchase_type == 'imported' ? 'info' : 'success' }}">
                                    {{ ucfirst($car->purchase_type) }}
                                </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Seller Name:</strong>
                                <p class="mb-0">{{ $car->seller_name ?? '—' }}</p>
                            </div>
                            @if($car->seller_notes)
                                <div class="col-12 mb-3">
                                    <strong>Seller Notes:</strong>
                                    <p class="mb-0" style="white-space: pre-wrap;">{{ $car->seller_notes }}</p>
                                </div>
                            @endif
                            @if($car->log_book_applied)
                                <div class="col-md-6 mb-3">
                                    <strong>Log book applied:</strong>
                                    <p class="mb-0"><span class="badge badge-success">Yes</span></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Applied Date:</strong>
                                    <p class="mb-0">{{ $car->log_book_applied_date ? $car->log_book_applied_date->format('d M, Y') : '—' }}</p>
                                </div>
                                @if($car->logBookAppliedBy)
                                    <div class="col-md-6 mb-3">
                                        <strong>Log book applied by:</strong>
                                        <p class="mb-0">{{ $car->logBookAppliedBy->name ?? '—' }}</p>
                                    </div>
                                @endif
                                @if($car->old_log_book)
                                    <div class="col-md-6 mb-3">
                                        <strong>Old log book:</strong>
                                        <p class="mb-0">
                                            <a href="{{ asset('uploads/cars/log_book/' . $car->old_log_book) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-file"></i> View file
                                            </a>
                                        </p>
                                    </div>
                                @endif
                            @endif
                            @if($car->v5_document)
                                <div class="col-md-6 mb-3">
                                    <strong>V5 Document:</strong>
                                    <p class="mb-0">
                                        <a href="{{ asset('uploads/cars/' . $car->v5_document) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-file-pdf"></i> View Document
                                        </a>
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- MOT Information -->
                        @php
                            $motsSorted = $car->mots;
                            $latestMot = $motsSorted->count() > 0 ? $motsSorted->first() : null;
                            $olderMots = $motsSorted->count() > 1 ? $motsSorted->slice(1) : collect();
                        @endphp
                        <div class="row mb-4">
                            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <h4 class="mb-0">MOT Information</h4>
                                @if($olderMots->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#showMotHistoryModal">View All</button>
                                @endif
                            </div>
                            @if($latestMot)
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="thead-light">
                                            <tr>
                                                <th>Expiry Date</th>
                                                <th>Amount</th>
                                                <th>Term</th>
                                                <th>Document</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>{{ $latestMot->expiry_date->format('d M, Y') }}</td>
                                                    <td>£{{ number_format($latestMot->amount, 2) }}</td>
                                                    <td>{{ $latestMot->term }}</td>
                                                    <td>
                                                        @if($latestMot->document)
                                                            <a href="{{ asset('uploads/cars/mot_documents/' . $latestMot->document) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fa fa-file"></i> View
                                                            </a>
                                                        @else
                                                            <span class="text-muted">No Document</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @else
                                <div class="col-12">
                                    <p class="text-muted">No MOT records available</p>
                                </div>
                            @endif
                        </div>
                        @if($olderMots->isNotEmpty())
                        <div class="modal fade" id="showMotHistoryModal" tabindex="-1" role="dialog" aria-labelledby="showMotHistoryModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="showMotHistoryModalLabel">Previous MOT records</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
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
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($olderMots as $mot)
                                                    <tr>
                                                        <td>{{ $mot->expiry_date->format('d M, Y') }}</td>
                                                        <td>£{{ number_format($mot->amount, 2) }}</td>
                                                        <td>{{ $mot->term }}</td>
                                                        <td>
                                                            @if($mot->document)
                                                                <a href="{{ asset('uploads/cars/mot_documents/' . $mot->document) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
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

                        <!-- Road Tax Information -->
                        @php
                            $rtsSorted = $car->roadTaxes;
                            $latestRt = $rtsSorted->count() > 0 ? $rtsSorted->first() : null;
                            $olderRts = $rtsSorted->count() > 1 ? $rtsSorted->slice(1) : collect();
                        @endphp
                        <div class="row mb-4">
                            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <h4 class="mb-0">Road Tax Information</h4>
                                @if($olderRts->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#showRoadTaxHistoryModal">View All</button>
                                @endif
                            </div>
                            @if($latestRt)
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="thead-light">
                                            <tr>
                                                <th>Start Date</th>
                                                <th>Term</th>
                                                <th>Amount</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>{{ $latestRt->start_date->format('d M, Y') }}</td>
                                                    <td>{{ $latestRt->term }}</td>
                                                    <td>£{{ number_format($latestRt->amount, 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @else
                                <div class="col-12">
                                    <p class="text-muted">No Road Tax records available</p>
                                </div>
                            @endif
                        </div>
                        @if($olderRts->isNotEmpty())
                        <div class="modal fade" id="showRoadTaxHistoryModal" tabindex="-1" role="dialog" aria-labelledby="showRoadTaxHistoryModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="showRoadTaxHistoryModalLabel">Previous road tax records</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Start Date</th>
                                                        <th>Term</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($olderRts as $roadTax)
                                                    <tr>
                                                        <td>{{ $roadTax->start_date->format('d M, Y') }}</td>
                                                        <td>{{ $roadTax->term }}</td>
                                                        <td>£{{ number_format($roadTax->amount, 2) }}</td>
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

                        <!-- PHV License Information -->
                        @php
                            $phvsSorted = $car->phvs;
                            $latestPhv = $phvsSorted->count() > 0 ? $phvsSorted->first() : null;
                            $olderPhvs = $phvsSorted->count() > 1 ? $phvsSorted->slice(1) : collect();
                        @endphp
                        <div class="row mb-4">
                            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <h4 class="mb-0">PHV License Information</h4>
                                @if($olderPhvs->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#showPhvHistoryModal">View All</button>
                                @endif
                            </div>
                            @if($latestPhv)
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="thead-light">
                                            <tr>
                                                <th>Counsel</th>
                                                <th>Start Date</th>
                                                <th>Expiry Date</th>
                                                <th>Amount</th>
                                                <th>Notify Before</th>
                                                <th>Document</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>{{ $latestPhv->counsel->name ?? 'N/A' }}</td>
                                                    <td>{{ $latestPhv->start_date->format('d M, Y') }}</td>
                                                    <td>{{ $latestPhv->expiry_date->format('d M, Y') }}</td>
                                                    <td>£{{ number_format($latestPhv->amount, 2) }}</td>
                                                    <td>{{ $latestPhv->notify_before_expiry }} days</td>
                                                    <td>
                                                        @if($latestPhv->document)
                                                            <a href="{{ asset('uploads/cars/phv_documents/' . $latestPhv->document) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fa fa-file"></i> View
                                                            </a>
                                                        @else
                                                            <span class="text-muted">No Document</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @else
                                <div class="col-12">
                                    <p class="text-muted">No PHV records available</p>
                                </div>
                            @endif
                        </div>
                        @if($olderPhvs->isNotEmpty())
                        <div class="modal fade" id="showPhvHistoryModal" tabindex="-1" role="dialog" aria-labelledby="showPhvHistoryModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="showPhvHistoryModalLabel">Previous PHV records</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
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
                                                        <th>Document</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($olderPhvs as $phv)
                                                    <tr>
                                                        <td>{{ $phv->counsel->name ?? 'N/A' }}</td>
                                                        <td>{{ $phv->start_date->format('d M, Y') }}</td>
                                                        <td>{{ $phv->expiry_date->format('d M, Y') }}</td>
                                                        <td>£{{ number_format($phv->amount, 2) }}</td>
                                                        <td>{{ $phv->notify_before_expiry }} days</td>
                                                        <td>
                                                            @if($phv->document)
                                                                <a href="{{ asset('uploads/cars/phv_documents/' . $phv->document) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
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

                        <!-- Insurance Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="border-bottom pb-2 mb-3">Insurance Information</h4>
                            </div>
                            @if($car->insurances && $car->insurances->count() > 0)
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Provider</th>
                                                <th>Start Date</th>
                                                <th>Expiry Date</th>
                                                <th>Notify Before</th>
                                                <th>Status</th>
                                                <th>Document</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($car->insurances as $index => $insurance)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>{{ $insurance->insuranceProvider->provider_name ?? 'N/A' }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($insurance->start_date)->format('d M, Y') }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($insurance->expiry_date)->format('d M, Y') }}</td>
                                                    <td>{{ $insurance->notify_before_expiry }} days</td>
                                                    <td>
                                                    <span class="badge badge-{{ $insurance->status->name == 'Active' ? 'success' : 'warning' }}">
                                                        {{ $insurance->status->name ?? 'N/A' }}
                                                    </span>
                                                    </td>
                                                    <td>
                                                        @if($insurance->insurance_document)
                                                            <a href="{{ asset('uploads/cars/insurance_documents/' . $insurance->insurance_document) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fa fa-file"></i> View
                                                            </a>
                                                        @else
                                                            <span class="text-muted">No Document</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @else
                                <div class="col-12">
                                    <p class="text-muted">No Insurance records available</p>
                                </div>
                            @endif
                        </div>

                        <!-- Timestamps -->
                        <div class="row">
                            <div class="col-12">
                                <h4 class="border-bottom pb-2 mb-3">Record Information</h4>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Created At:</strong>
                                <p class="mb-0">{{ $car->created_at->format('d M, Y h:i A') }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Last Updated:</strong>
                                <p class="mb-0">{{ $car->updated_at->format('d M, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
    </script>
@endsection
