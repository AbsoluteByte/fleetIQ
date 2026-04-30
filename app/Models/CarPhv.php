<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarPhv extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'car_id', 'counsel_id', 'amount', 'start_date',
        'expiry_date', 'notify_before_expiry', 'document',
        'phv_applied', 'phv_applied_date', 'phv_applied_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiry_date' => 'date',
        'amount' => 'decimal:2',
        'phv_applied' => 'boolean',
        'phv_applied_date' => 'date',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function counsel()
    {
        return $this->belongsTo(Counsel::class);
    }

    public function phvAppliedBy()
    {
        return $this->belongsTo(User::class, 'phv_applied_by');
    }
}
