<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementCarRentalInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'agreement_id',
        'company_id',
        'serial_prefix',
        'serial_number',
        'invoice_date',
        'include_cash',
        'bank_account_filter_id',
        'weekly_insurance_amount',
        'vat_rate',
        'vat_registration_number',
        'rental_start_date',
        'rental_end_date',
        'payment_snapshot',
        'totals_snapshot',
        'generated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'include_cash' => 'boolean',
        'weekly_insurance_amount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'rental_start_date' => 'date',
        'rental_end_date' => 'date',
        'payment_snapshot' => 'array',
        'totals_snapshot' => 'array',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function bankAccountFilter(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_filter_id');
    }

    public function displaySerial(): string
    {
        return $this->serial_number;
    }
}
