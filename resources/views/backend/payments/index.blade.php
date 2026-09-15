@extends('layouts.admin', ['title' => 'Driver Payments'])
@section('content')
    <section id="basic-datatable">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Driver Payments</h4>
                        <a class="btn btn-primary float-right" href="{{ route('payments.create') }}">
                            <i class="fa fa-plus"></i> Add Payment
                        </a>
                    </div>
                    <div class="card-content">
                        <div class="card-body card-dashboard">
                            @include('alerts')
                            <div class="payments-table-toolbar" id="paymentsTableToolbar">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" id="paymentsExportDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa fa-download mr-50"></i> Export
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="paymentsExportDropdown">
                                        <button type="button" class="dropdown-item" id="paymentsExportCsv">Export CSV</button>
                                        <button type="button" class="dropdown-item" id="paymentsExportPdf">Export PDF</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="dataTable" class="table datatable table-bordered table-striped">
                                    <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Pay to</th>
                                        <th>Phone</th>
                                        <th>Invoices</th>
                                        <th>Payments</th>
                                        <th>Payment Due</th>
                                        <th>Last Payment</th>
                                        <th>Total Due</th>
                                        <th>Credit</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="payments-filter-backdrop" id="paymentsFilterBackdrop"></div>
    <aside class="payments-filter-panel" id="paymentsFilterPanel" aria-hidden="true">
        <div class="payments-filter-panel__header">
            <h5 class="mb-0">Advanced Search</h5>
            <button type="button" class="close" id="paymentsFilterClose" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="payments-filter-panel__body">
            <div class="form-group">
                <label for="paymentsFilterStatus">Driver Status</label>
                <select id="paymentsFilterStatus" class="form-control payments-advanced-filter" data-filter-key="driverStatus">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="form-group">
                <label class="d-block mb-50">Reminder between</label>
                <div class="form-row payments-date-row">
                    <div class="col-6">
                        <label class="small text-muted mb-25 d-block" for="paymentsReminderFrom">From</label>
                        <input type="datetime-local" id="paymentsReminderFrom" class="form-control payments-datetime-filter">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-25 d-block" for="paymentsReminderTo">To</label>
                        <input type="datetime-local" id="paymentsReminderTo" class="form-control payments-datetime-filter">
                    </div>
                </div>
                <small class="text-muted d-block mt-50">Uses the driver note/reminder date and time.</small>
            </div>

            <div class="form-group">
                <label class="d-block mb-50">Last payment between</label>
                <div class="form-row payments-date-row">
                    <div class="col-6">
                        <label class="small text-muted mb-25 d-block" for="paymentsLastPaymentFrom">From</label>
                        <input type="date" id="paymentsLastPaymentFrom" class="form-control payments-date-filter">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-25 d-block" for="paymentsLastPaymentTo">To</label>
                        <input type="date" id="paymentsLastPaymentTo" class="form-control payments-date-filter">
                    </div>
                </div>
                <small class="text-muted d-block mt-50">Uses latest posted payment date.</small>
            </div>

            <div class="form-group">
                <label class="d-block mb-50">Latest invoice date between</label>
                <div class="form-row payments-date-row">
                    <div class="col-6">
                        <label class="small text-muted mb-25 d-block" for="paymentsLatestInvoiceFrom">From</label>
                        <input type="date" id="paymentsLatestInvoiceFrom" class="form-control payments-date-filter">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-25 d-block" for="paymentsLatestInvoiceTo">To</label>
                        <input type="date" id="paymentsLatestInvoiceTo" class="form-control payments-date-filter">
                    </div>
                </div>
                <small class="text-muted d-block mt-50">Uses the most recent invoice date for the driver.</small>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-block" id="paymentsFilterReset">Reset Filters</button>
        </div>
    </aside>

    @include('backend.payments.partials.follow-up-modal')
@endsection
@section('css')
    <link rel="stylesheet" href="{{ asset('app-assets/vendors/css/tables/datatable/datatables.min.css') }}">
    <style>
        #dataTable_filter {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .payments-table-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .payments-table-controls {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            margin-left: auto;
        }

        .card-dashboard .dataTables_wrapper .dataTables_filter {
            margin-top: 0;
            float: none;
        }

        #dataTable_filter label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }

        #dataTable_filter input {
            margin-left: .5rem;
        }

        .paying-company-subtitle {
            color: #6e6b7b;
            font-size: 0.875rem;
        }

        .payments-filter-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            margin-left: .5rem;
            margin-top: 1rem;
            border: 1px solid #d8d6de;
            border-radius: .25rem;
            color: #6e6b7b;
            background: #fff;
            cursor: pointer;
        }

        .payments-filter-button:hover,
        .payments-filter-button:focus {
            border-color: #7367f0;
            color: #7367f0;
            outline: none;
        }

        .payments-filter-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1040;
            display: none;
            background: rgba(34, 41, 47, .35);
        }

        .payments-filter-backdrop.is-open {
            display: block;
        }

        .payments-filter-panel {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 1050;
            width: 360px;
            max-width: 92vw;
            height: 100vh;
            background: #fff;
            box-shadow: -8px 0 24px rgba(34, 41, 47, .15);
            transform: translateX(100%);
            transition: transform .2s ease;
        }

        .payments-filter-panel.is-open {
            transform: translateX(0);
        }

        .payments-filter-panel__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #ebe9f1;
        }

        .payments-filter-panel__body {
            height: calc(100vh - 65px);
            padding: 1.25rem;
            overflow-y: auto;
        }

        #paymentsFilterClose {
            padding: 0.3rem 0.7rem;
        }

        .payments-date-row {
            margin-left: 0;
            margin-right: 0;
        }

        .payments-date-row > [class*="col-"] {
            padding-left: 0;
            padding-right: 0.5rem;
        }

        .payments-date-row > [class*="col-"]:last-child {
            padding-right: 0;
            padding-left: 0.5rem;
        }
    </style>
@endsection
@section('js')
    <script src="{{ asset('app-assets/vendors/js/tables/datatable/datatables.min.js') }}"></script>
    <script src="{{ asset('app-assets/vendors/js/tables/datatable/datatables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('app-assets/vendors/js/tables/datatable/pdfmake.min.js') }}"></script>
    <script src="{{ asset('app-assets/vendors/js/tables/datatable/vfs_fonts.js') }}"></script>
    <script>
        $(document).ready(function () {
            function initializeActionTooltips() {
                $('.js-action-tooltip').tooltip({ container: 'body' });
                $('.js-dfs-pending-amount[data-toggle="tooltip"]').tooltip({ container: 'body' });
            }

            const advancedFilters = {
                driverStatus: '',
                reminderFrom: '',
                reminderTo: '',
                lastPaymentFrom: '',
                lastPaymentTo: '',
                latestInvoiceFrom: '',
                latestInvoiceTo: '',
            };

            function syncFiltersFromForm() {
                advancedFilters.driverStatus = $('#paymentsFilterStatus').val() || '';
                advancedFilters.reminderFrom = $('#paymentsReminderFrom').val() || '';
                advancedFilters.reminderTo = $('#paymentsReminderTo').val() || '';
                advancedFilters.lastPaymentFrom = $('#paymentsLastPaymentFrom').val() || '';
                advancedFilters.lastPaymentTo = $('#paymentsLastPaymentTo').val() || '';
                advancedFilters.latestInvoiceFrom = $('#paymentsLatestInvoiceFrom').val() || '';
                advancedFilters.latestInvoiceTo = $('#paymentsLatestInvoiceTo').val() || '';
            }

            function filterAjaxParams() {
                syncFiltersFromForm();

                return {
                    filter_driver_status: advancedFilters.driverStatus,
                    filter_reminder_from: advancedFilters.reminderFrom,
                    filter_reminder_to: advancedFilters.reminderTo,
                    filter_last_payment_from: advancedFilters.lastPaymentFrom,
                    filter_last_payment_to: advancedFilters.lastPaymentTo,
                    filter_latest_invoice_from: advancedFilters.latestInvoiceFrom,
                    filter_latest_invoice_to: advancedFilters.latestInvoiceTo,
                };
            }

            const dataTable = $('#dataTable').DataTable({
                processing: true,
                serverSide: true,
                deferRender: true,
                pageLength: 25,
                responsive: true,
                ajax: {
                    url: '{{ route('payments.index') }}',
                    data: function (params) {
                        return Object.assign(params, filterAjaxParams());
                    },
                },
                columns: [
                    { data: 'driver', name: 'driver', orderable: false, searchable: false },
                    { data: 'vehicle', name: 'vehicle', orderable: false, searchable: false },
                    { data: 'pay_to', name: 'pay_to', orderable: false, searchable: false },
                    { data: 'phone', name: 'phone' },
                    { data: 'invoices_count', name: 'invoices_count', searchable: false },
                    { data: 'payments_count', name: 'payments_count', searchable: false },
                    { data: 'payment_due', name: 'payment_due', orderable: false, searchable: false },
                    { data: 'last_payment', name: 'last_payment', orderable: false, searchable: false },
                    { data: 'total_due_html', name: 'total_due_html', orderable: false, searchable: false },
                    { data: 'credit_html', name: 'credit_html', orderable: false, searchable: false },
                    { data: 'actions_html', name: 'actions_html', orderable: false, searchable: false },
                ],
            });

            initializeActionTooltips();
            dataTable.on('draw.dt responsive-display.dt', function () {
                $('.tooltip').remove();
                initializeActionTooltips();
            });

            const $filter = $('#dataTable_filter');
            const $toolbar = $('#paymentsTableToolbar');
            if ($filter.length && $toolbar.length && !$filter.parent().hasClass('payments-table-controls')) {
                const $controls = $('<div class="payments-table-controls"></div>');
                $filter.before($controls);
                $controls.append($toolbar);
                $controls.append($filter);
            }

            $('#dataTable_filter').append(
                '<button type="button" class="payments-filter-button" id="paymentsFilterOpen" title="Filter" aria-label="Filter"><i class="fa fa-filter"></i></button>'
            );

            function parseDateYmd(value) {
                if (!value) {
                    return null;
                }
                const parts = value.split('-');
                if (parts.length !== 3) {
                    return null;
                }
                const date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                return isNaN(date.getTime()) ? null : date;
            }

            function setFilterPanelOpen(isOpen) {
                $('#paymentsFilterPanel').toggleClass('is-open', isOpen).attr('aria-hidden', isOpen ? 'false' : 'true');
                $('#paymentsFilterBackdrop').toggleClass('is-open', isOpen);
            }

            $(document).on('click', '#paymentsFilterOpen', function () {
                setFilterPanelOpen(true);
            });

            $('#paymentsFilterClose, #paymentsFilterBackdrop').on('click', function () {
                setFilterPanelOpen(false);
            });

            $('.payments-advanced-filter').on('change', function () {
                dataTable.ajax.reload();
            });

            $('.payments-date-filter, .payments-datetime-filter').on('change input', function () {
                dataTable.ajax.reload();
            });

            $('#paymentsFilterReset').on('click', function () {
                $('#paymentsFilterStatus').val('');
                $('#paymentsReminderFrom, #paymentsReminderTo, #paymentsLastPaymentFrom, #paymentsLastPaymentTo, #paymentsLatestInvoiceFrom, #paymentsLatestInvoiceTo').val('');
                dataTable.ajax.reload();
            });

            function formatDisplayDate(iso) {
                if (!iso) {
                    return '';
                }
                const date = parseDateYmd(iso);
                if (!date) {
                    return iso;
                }
                return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            }

            function formatDateRangeLine(label, fromValue, toValue) {
                if (!fromValue && !toValue) {
                    return '';
                }

                const fromLabel = fromValue ? formatDisplayDate(fromValue) || fromValue : 'any';
                const toLabel = toValue ? formatDisplayDate(toValue) || toValue : 'any';

                return label + ': ' + fromLabel + ' to ' + toLabel;
            }

            function formatDatetimeRangeLine(label, fromValue, toValue) {
                if (!fromValue && !toValue) {
                    return '';
                }

                const fromLabel = fromValue || 'any';
                const toLabel = toValue || 'any';

                return label + ': ' + fromLabel + ' to ' + toLabel;
            }

            function selectedOptionText(selectId) {
                const select = document.getElementById(selectId);
                if (!select || !select.value) {
                    return '';
                }

                return select.options[select.selectedIndex]?.text || select.value;
            }

            const paymentsExportHeaders = [
                'Driver',
                'Vehicle',
                'Pay to',
                'Phone',
                'Invoices',
                'Payments',
                'Payment Due',
                'Last Payment',
                'Total Due',
                'Credit',
            ];
            const paymentsExportFilenamePrefix = 'driver-payments';
            const paymentsExportTitle = 'Driver Payments';

            function paymentsExportFilename(extension) {
                return paymentsExportFilenamePrefix + '-' + new Date().toISOString().slice(0, 10) + extension;
            }

            function getPaymentsExportHeaders() {
                return paymentsExportHeaders.slice();
            }

            function buildPaymentsExportMeta() {
                syncFiltersFromForm();

                const lines = [];
                const searchTerm = (dataTable.search() || '').trim();

                if (searchTerm) {
                    lines.push('Search: ' + searchTerm);
                }

                if (advancedFilters.driverStatus) {
                    lines.push('Driver status: ' + selectedOptionText('paymentsFilterStatus'));
                }

                const reminderLine = formatDatetimeRangeLine(
                    'Reminder between',
                    advancedFilters.reminderFrom,
                    advancedFilters.reminderTo
                );
                if (reminderLine) {
                    lines.push(reminderLine);
                }

                const lastPaymentLine = formatDateRangeLine(
                    'Last payment between',
                    advancedFilters.lastPaymentFrom,
                    advancedFilters.lastPaymentTo
                );
                if (lastPaymentLine) {
                    lines.push(lastPaymentLine);
                }

                const latestInvoiceLine = formatDateRangeLine(
                    'Latest invoice date between',
                    advancedFilters.latestInvoiceFrom,
                    advancedFilters.latestInvoiceTo
                );
                if (latestInvoiceLine) {
                    lines.push(latestInvoiceLine);
                }

                if (lines.length === 0) {
                    lines.push('Filters: None');
                }

                return {
                    title: paymentsExportTitle,
                    lines: lines,
                };
            }

            function csvEscape(value) {
                const str = String(value ?? '').replace(/"/g, '""').trim();
                return /[",\n\r]/.test(str) ? '"' + str + '"' : str;
            }

            function downloadCsv(filename, lines) {
                const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }

            function fetchPaymentsExportRows(callback) {
                const params = Object.assign(filterAjaxParams(), {
                    export: 1,
                    search: (dataTable.search() || '').trim(),
                });

                $.ajax({
                    url: '{{ route('payments.index') }}',
                    method: 'GET',
                    data: params,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }).done(function (response) {
                    const rows = (response.rows || []).map(function (cells, index) {
                        return {
                            dfsStatus: (response.dfs_statuses || [])[index] || '',
                            cells: cells,
                        };
                    });
                    callback(rows);
                }).fail(function () {
                    alert('Unable to export driver payments. Please try again.');
                    callback([]);
                });
            }

            function paymentsPdfRowFillColor(dfsStatus) {
                if (dfsStatus === 'pending') {
                    return '#fff8eb';
                }
                if (dfsStatus === 'posted') {
                    return '#ecfdf3';
                }

                return null;
            }

            function getPaymentsPdfAvailableWidth() {
                return 841.89 - 16 - 16;
            }

            function getPaymentsPdfColumnWidths() {
                return [178, 60, 28, 68, 32, 43, 64, 64, 55, 55];
            }

            function getPaymentsPdfTableWidth() {
                return getPaymentsPdfAvailableWidth();
            }

            function getPaymentsPdfCellPadding() {
                return {
                    left: 8,
                    right: 8,
                    top: 9,
                    bottom: 9,
                };
            }

            function formatPaymentsPdfCellText(cell, columnIndex) {
                let value = String(cell ?? '').replace(/\s+/g, ' ').trim();

                if (columnIndex === 0) {
                    value = value.replace(/\s+Pays via:.*$/i, '').trim();
                }

                return value;
            }

            function buildPaymentsPdfTableCell(cell, columnIndex, fillColor) {
                const cellDef = {
                    text: formatPaymentsPdfCellText(cell, columnIndex),
                    style: columnIndex >= 4 ? 'tableCellNumeric' : 'tableCell',
                    noWrap: false,
                };

                if (fillColor) {
                    cellDef.fillColor = fillColor;
                }

                return cellDef;
            }

            function exportPaymentsCsv() {
                const exportMeta = buildPaymentsExportMeta();
                const exportHeaders = getPaymentsExportHeaders();

                fetchPaymentsExportRows(function (bodyRows) {
                    if (bodyRows.length === 0) {
                        alert('No records to export. Adjust your search or filters and try again.');
                        return;
                    }

                    const lines = [csvEscape(exportMeta.title)];
                    exportMeta.lines.forEach(function (line) {
                        lines.push(csvEscape(line));
                    });
                    lines.push('');
                    lines.push(exportHeaders.map(csvEscape).join(','));
                    bodyRows.forEach(function (entry) {
                        lines.push(entry.cells.map(csvEscape).join(','));
                    });

                    downloadCsv(paymentsExportFilename('.csv'), lines);
                });
            }

            function exportPaymentsPdf() {
                const exportMeta = buildPaymentsExportMeta();
                const exportHeaders = getPaymentsExportHeaders();

                if (typeof pdfMake === 'undefined') {
                    alert('PDF export is not available. Please refresh the page and try again.');
                    return;
                }

                fetchPaymentsExportRows(function (bodyRows) {
                    if (bodyRows.length === 0) {
                        alert('No records to export. Adjust your search or filters and try again.');
                        return;
                    }

                    const tableBody = [
                        getPaymentsExportHeaders().map(function (header, columnIndex) {
                            return {
                                text: header,
                                style: columnIndex >= 4 ? 'tableHeaderNumeric' : 'tableHeader',
                                noWrap: false,
                            };
                        }),
                    ];

                    const hasPendingRows = bodyRows.some(function (entry) {
                        return entry.dfsStatus === 'pending';
                    });
                    const hasPostedRows = bodyRows.some(function (entry) {
                        return entry.dfsStatus === 'posted';
                    });

                    bodyRows.forEach(function (entry) {
                        const fillColor = paymentsPdfRowFillColor(entry.dfsStatus);
                        tableBody.push(entry.cells.map(function (cell, columnIndex) {
                            return buildPaymentsPdfTableCell(cell, columnIndex, fillColor);
                        }));
                    });

                    const content = [
                        {
                            text: exportMeta.title + ' — ' + new Date().toISOString().slice(0, 10),
                            style: 'title',
                            margin: [0, 0, 0, 4],
                        },
                        ...exportMeta.lines.map(function (line) {
                            return {
                                text: line,
                                style: 'subtitle',
                                margin: [0, 0, 0, 2],
                            };
                        }),
                    ];

                    if (hasPendingRows || hasPostedRows) {
                        if (hasPendingRows) {
                            content.push({
                                text: 'Light yellow rows: payment recorded, pending daily financial sheet approval.',
                                style: 'subtitle',
                                margin: [0, 0, 0, 2],
                            });
                        }
                        if (hasPostedRows) {
                            content.push({
                                text: 'Light green rows: payments approved in daily financial sheet.',
                                style: 'subtitle',
                                margin: [0, 0, 0, 2],
                            });
                        }
                    }

                    content.push({
                        text: '',
                        margin: [0, 0, 0, 8],
                    });
                    content.push({
                        table: {
                            headerRows: 1,
                            widths: getPaymentsPdfColumnWidths(),
                            body: tableBody,
                        },
                        layout: {
                            hLineWidth: function () { return 0.5; },
                            vLineWidth: function () { return 0; },
                            hLineColor: function () { return '#dfe3e8'; },
                            paddingLeft: function () { return getPaymentsPdfCellPadding().left; },
                            paddingRight: function () { return getPaymentsPdfCellPadding().right; },
                            paddingTop: function () { return getPaymentsPdfCellPadding().top; },
                            paddingBottom: function () { return getPaymentsPdfCellPadding().bottom; },
                        },
                        width: getPaymentsPdfTableWidth(),
                    });

                    const doc = {
                        pageSize: 'A4',
                        pageOrientation: 'landscape',
                        pageMargins: [16, 40, 16, 28],
                        content: content,
                        styles: {
                            title: { fontSize: 14, bold: true },
                            subtitle: { fontSize: 9, color: '#5e5873' },
                            tableHeader: { fontSize: 9, bold: true, fillColor: '#f3f2f7' },
                            tableHeaderNumeric: { fontSize: 9, bold: true, fillColor: '#f3f2f7', alignment: 'right' },
                            tableCell: { fontSize: 8, lineHeight: 1.25 },
                            tableCellNumeric: { fontSize: 8, lineHeight: 1.25, alignment: 'right' },
                        },
                        defaultStyle: { fontSize: 8 },
                        footer: function (currentPage, pageCount) {
                            return {
                                text: 'Page ' + currentPage + ' of ' + pageCount,
                                alignment: 'center',
                                fontSize: 8,
                                color: '#5e5873',
                                margin: [0, 8, 0, 0],
                            };
                        },
                    };

                    pdfMake.createPdf(doc).download(paymentsExportFilename('.pdf'));
                });
            }

            $('#paymentsExportCsv').on('click', exportPaymentsCsv);
            $('#paymentsExportPdf').on('click', exportPaymentsPdf);

            const $modal = $('#driverFollowUpModal');
            const $form = $('#driverFollowUpForm');
            const $notes = $('#driverFollowUpNotes');
            const $setReminder = $('#driverFollowUpSetReminder');
            const $remindAtGroup = $('#driverFollowUpRemindAtGroup');
            const $remindAt = $('#driverFollowUpRemindAt');
            const $error = $('#driverFollowUpError');
            const $subtitle = $('#driverFollowUpModalSubtitle');
            let activeButton = null;

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            /** Convert ISO/UTC (or datetime-local) to input[type=datetime-local] value in the browser's local timezone. */
            function toDatetimeLocalValue(value) {
                if (!value) {
                    return '';
                }
                const date = new Date(value);
                if (isNaN(date.getTime())) {
                    return String(value).slice(0, 16);
                }
                return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate()) +
                    'T' + pad2(date.getHours()) + ':' + pad2(date.getMinutes());
            }

            /** datetime-local is timezone-naive; send real instant as ISO so server UTC matches the clock the user picked. */
            function datetimeLocalToIso(value) {
                if (!value) {
                    return null;
                }
                const date = new Date(value);
                if (isNaN(date.getTime())) {
                    return value;
                }
                return date.toISOString();
            }

            function toggleRemindAt() {
                if ($setReminder.is(':checked')) {
                    $remindAtGroup.show();
                } else {
                    $remindAtGroup.hide();
                    $remindAt.val('');
                }
            }

            $setReminder.on('change', toggleRemindAt);

            $(document).on('click', '.js-driver-follow-up', function () {
                activeButton = $(this);
                $error.hide().text('');
                $form.attr('action', activeButton.data('update-url'));
                $subtitle.text(activeButton.data('driver-name') || '');
                $notes.val(activeButton.attr('data-notes') || '');
                const remindAt = activeButton.attr('data-remind-at') || '';
                $setReminder.prop('checked', !!remindAt);
                $remindAt.val(toDatetimeLocalValue(remindAt));
                toggleRemindAt();
                $modal.modal('show');
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                $error.hide().text('');

                const payload = {
                    notes: $notes.val(),
                    set_reminder: $setReminder.is(':checked') ? 1 : 0,
                    remind_at: $setReminder.is(':checked') ? datetimeLocalToIso($remindAt.val()) : null,
                    _method: 'PATCH',
                    _token: '{{ csrf_token() }}'
                };

                $('#driverFollowUpSaveBtn').prop('disabled', true);

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: payload,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    const driver = response.driver || {};
                    if (activeButton && activeButton.length) {
                        activeButton.attr('data-notes', driver.notes || '');
                        activeButton.attr('data-remind-at', driver.remind_at || '');
                        activeButton
                            .removeClass('btn-warning btn-outline-secondary')
                            .addClass(driver.has_note || driver.has_reminder ? 'btn-warning' : 'btn-outline-secondary');
                    }
                    if (window.clearPaymentFollowUpSnooze && driver.id) {
                        window.clearPaymentFollowUpSnooze(driver.id);
                    }
                    $modal.modal('hide');
                    if (window.toastr) {
                        toastr.success(response.message || 'Saved');
                    }
                }).fail(function (xhr) {
                    let message = 'Unable to save note / reminder.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        message = Object.values(xhr.responseJSON.errors).flat().join(' ');
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    $error.text(message).show();
                }).always(function () {
                    $('#driverFollowUpSaveBtn').prop('disabled', false);
                });
            });
        });
    </script>
@endsection
