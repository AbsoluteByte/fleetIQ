<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarInsuranceCoveragePeriod extends Model
{
    protected $fillable = [
        'tenant_id',
        'car_id',
        'insurance_provider_id',
        'activated_at',
        'deactivated_at',
        'end_date_pending',
        'activated_by_user_id',
        'deactivated_by_user_id',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'end_date_pending' => 'boolean',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function documents()
    {
        return $this->hasMany(CarInsuranceDocument::class, 'car_insurance_coverage_period_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by_user_id');
    }
}
