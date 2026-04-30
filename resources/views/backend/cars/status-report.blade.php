@extends('layouts.admin', ['title' => $statusLabel . ' Cars'])

@section('content')
    <section id="basic-datatable">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ $statusLabel }} Cars</h4>
                        <a class="btn btn-secondary float-right" href="{{ route('cars.index') }}">Back to Fleet</a>
                    </div>
                    <hr>
                    <div class="card-content">
                        <div class="card-body card-dashboard">
                            @include('alerts')
                            <div class="table-responsive">
                                <table id="dataTable" class="table datatable table-bordered table-striped">
                                    <thead>
                                    <tr>
                                        <th>Registration</th>
                                        <th>Company</th>
                                        <th>Model</th>
                                        <th>PHV Council</th>
                                        <th>Available From</th>
                                        <th>Reservation</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($cars as $car)
                                        @php $reservation = $car->activeReservation(); @endphp
                                        <tr>
                                            <td><strong>{{ $car->registration }}</strong></td>
                                            <td>{{ $car->company->name ?? '—' }}</td>
                                            <td>{{ $car->carModel->name ?? '—' }}</td>
                                            <td>{{ $car->latestPhvCounselName() ?? '—' }}</td>
                                            <td>{{ $car->available_from_date ? $car->available_from_date->format('d M, Y') : 'Now' }}</td>
                                            <td>{{ $reservation ? $reservation->customer_name : '—' }}</td>
                                            <td>
                                                <a href="{{ route('cars.show', $car) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a>
                                                <a href="{{ route('cars.edit', $car) }}" class="btn btn-sm btn-outline-warning"><i class="fa fa-edit"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">No {{ strtolower($statusLabel) }} cars found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('app-assets/vendors/css/tables/datatable/datatables.min.css') }}">
@endsection

@section('js')
    <script src="{{ asset('app-assets/vendors/js/tables/datatable/datatables.min.js') }}"></script>
    <script src="{{ asset('app-assets/vendors/js/tables/datatable/datatables.bootstrap4.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#dataTable').DataTable({ processing: true, responsive: true });
        });
    </script>
@endsection
