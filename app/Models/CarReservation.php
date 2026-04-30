<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'reservation_date',
        'available_from_date',
        'terms_conditions',
        'status',
        'created_by',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'available_from_date' => 'date',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
