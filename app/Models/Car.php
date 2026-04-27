<?php
// app/Models/Car.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id','company_id', 'car_model_id', 'registration', 'color',
        'vin', 'v5_document', 'manufacture_year', 'registration_year',
        'purchase_date', 'purchase_price', 'purchase_type', 'seller_name',
        'seller_notes', 'log_book_applied', 'log_book_applied_date', 'old_log_book',
        'log_book_applied_by',
        'sorn_applied', 'sorn_applied_at', 'sorn_applied_by',
        'createdBy', 'updatedBy',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'log_book_applied' => 'boolean',
        'log_book_applied_date' => 'date',
        'sorn_applied' => 'boolean',
        'sorn_applied_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function carModel()
    {
        return $this->belongsTo(CarModel::class);
    }

    public function logBookAppliedBy()
    {
        return $this->belongsTo(User::class, 'log_book_applied_by');
    }

    public function sornAppliedBy()
    {
        return $this->belongsTo(User::class, 'sorn_applied_by');
    }

    public function agreements()
    {
        return $this->hasMany(Agreement::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function mots()
    {
        return $this->hasMany(CarMot::class);
    }

    public function roadTaxes()
    {
        return $this->hasMany(CarRoadTax::class);
    }

    public function phvs()
    {
        return $this->hasMany(CarPhv::class);
    }

    public function insurances()
    {
        return $this->hasMany(CarInsurance::class);
    }

    // ==================== SCOPES ====================

    // ✅ Scope for specific tenant
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ✅ Scope for current user's tenant
    public function scopeForCurrentTenant($query)
    {
        $tenant = auth()->user()->currentTenant();
        return $query->where('tenant_id', $tenant->id ?? 0);
    }

    /**
     * Counsel name from the PHV row with the latest expiry date (current or most recent).
     */
    public function latestPhvCounselName(): ?string
    {
        $phv = $this->phvs
            ->sortByDesc(fn (CarPhv $p) => optional($p->expiry_date)->timestamp ?? 0)
            ->first();

        return $phv?->counsel?->name;
    }

    /**
     * Insurance is shown as Active when the latest-by-expiry policy has status "Active" and is not past its expiry date.
     */
    public function isInsuranceCurrentlyActive(): bool
    {
        $insurance = $this->insurances
            ->sortByDesc(fn (CarInsurance $i) => optional($i->expiry_date)->timestamp ?? 0)
            ->first();

        if (! $insurance?->status || ! $insurance->expiry_date) {
            return false;
        }

        if (strcasecmp($insurance->status->name, 'Active') !== 0) {
            return false;
        }

        return $insurance->expiry_date->copy()->startOfDay()->gte(now()->startOfDay());
    }
}
