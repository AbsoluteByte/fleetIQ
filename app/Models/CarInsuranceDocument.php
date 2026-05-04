<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarInsuranceDocument extends Model
{
    protected $fillable = [
        'tenant_id',
        'car_id',
        'car_insurance_coverage_period_id',
        'insurance_provider_id',
        'document',
        'original_name',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function coveragePeriod(): BelongsTo
    {
        return $this->belongsTo(CarInsuranceCoveragePeriod::class, 'car_insurance_coverage_period_id');
    }

    public function insuranceProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function publicUrl(): string
    {
        return asset('uploads/cars/insurance_documents/'.$this->document);
    }
}
