<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverStatementExportService
{
    public function invoices(Driver $driver, string $format)
    {
        $invoices = $driver->invoices()
            ->with(['sourceAgreement.paymentBankAccount'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Invoice No',
            'Type',
            'Agreement',
            'Pays via',
            'Pay to',
            'Invoice Date',
            'Due Date',
            'Total',
            'Paid',
            'Balance',
            'Status',
            'Notes',
        ];

        $rows = $invoices->map(function (Invoice $invoice) {
            $agreementId = $invoice->linkedAgreementId();

            return [
                $invoice->invoice_no,
                ucfirst(str_replace('_', ' ', (string) $invoice->invoice_type)),
                $agreementId ? 'Agreement ID #'.$agreementId : '',
                $invoice->payingCompanyNameLabel() ?? '',
                $invoice->sourceAgreement?->paymentBankAccount?->paymentDisplayName() ?: '',
                optional($invoice->invoice_date)->format('d M Y') ?? '',
                optional($invoice->due_date)->format('d M Y') ?? '',
                $this->money($invoice->total_amount),
                $this->money($invoice->paid_amount),
                $this->money($invoice->balance_amount),
                ucfirst((string) $invoice->status),
                trim((string) ($invoice->notes ?? '')),
            ];
        })->all();

        return $this->download(
            $driver,
            'invoices',
            'Invoices',
            $format,
            $headers,
            $rows,
            'Total invoices',
            $this->money($invoices->sum(fn (Invoice $invoice) => (float) $invoice->total_amount))
        );
    }

    public function payments(Driver $driver, string $format)
    {
        $payments = $driver->payments()
            ->with('bankAccount')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Payment No',
            'Date',
            'Method',
            'Bank',
            'Amount',
            'Allocated',
            'Credit',
            'Status',
            'Notes',
        ];

        $rows = $payments->map(function (Payment $payment) {
            $bank = Payment::requiresBankAccount($payment->payment_method)
                ? trim((string) ($payment->bankAccount?->bank_name ?? ''))
                : '';

            return [
                $payment->payment_no,
                optional($payment->payment_date)->format('d M Y') ?? '',
                (string) $payment->payment_method,
                $bank,
                $this->money($payment->amount),
                $this->money($payment->allocated_amount),
                $this->money($payment->unallocated_amount),
                $payment->isPending() ? 'Pending' : 'Posted',
                trim((string) ($payment->notes ?? '')),
            ];
        })->all();

        return $this->download(
            $driver,
            'payments',
            'Payments',
            $format,
            $headers,
            $rows,
            'Total payments',
            $this->money($payments->sum(fn (Payment $payment) => (float) $payment->amount))
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function download(
        Driver $driver,
        string $kind,
        string $title,
        string $format,
        array $headers,
        array $rows,
        string $totalLabel,
        string $totalValue
    ) {
        $filename = $this->filename($driver, $kind, $format);

        if ($format === 'csv') {
            return $this->csv($filename, $headers, $rows, $totalLabel, $totalValue);
        }

        if ($format === 'pdf') {
            return $this->pdf($filename, $driver, $title, $headers, $rows, $totalLabel, $totalValue);
        }

        abort(404);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function csv(string $filename, array $headers, array $rows, string $totalLabel, string $totalValue): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $totalLabel, $totalValue) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fputcsv($handle, []);
            fputcsv($handle, [$totalLabel, $totalValue]);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function pdf(
        string $filename,
        Driver $driver,
        string $title,
        array $headers,
        array $rows,
        string $totalLabel,
        string $totalValue
    ) {
        $pdf = Pdf::loadView('backend.payments.driver-statement-pdf', [
            'driverName' => trim($driver->full_name) ?: 'Driver',
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'totalLabel' => $totalLabel,
            'totalValue' => '£'.$totalValue,
            'generatedAt' => now()->format('d M Y H:i'),
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($filename);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function filename(Driver $driver, string $kind, string $extension): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '_', trim($driver->full_name));
        $name = trim((string) $name, '_');

        return ($name !== '' ? $name : 'driver').'_'.$kind.'.'.$extension;
    }
};
