<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarActivityLog;
use App\Models\CarModel;
use App\Models\Company;
use App\Models\InsuranceProvider;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CarActivityLogger
{
    /** @var array<string, string> */
    private const LABELS = [
        'company_id' => 'Company',
        'car_model_id' => 'Model',
        'registration' => 'Registration',
        'color' => 'Color',
        'vin' => 'VIN',
        'manufacture_year' => 'Manufacture year',
        'purchase_date' => 'Purchase date',
        'purchase_price' => 'Purchase price',
        'purchase_type' => 'Purchase type',
        'fleet_status' => 'Fleet status',
        'phv_status' => 'PHV status',
        'tracker_installed' => 'Tracker installed',
        'dashcam_installed' => 'Dashcam installed',
        'tag_installed' => 'Tag installed',
        'sorn_applied' => 'SORN applied',
        'available_from_date' => 'Available from',
        'insurance_provider_id' => 'Insurance provider',
        'insurance_status_id' => 'Insurance status',
        'insurance_start_date' => 'Insurance start date',
        'insurance_expiry_date' => 'Insurance expiry date',
        'insurance_applied_date' => 'Insurance applied date',
        'insurance_canceled_date' => 'Insurance canceled date',
    ];

    /** @var list<string> */
    private const MONEY = [
        'purchase_price',
    ];

    /** @var list<string> */
    private const BOOLEANS = [
        'tracker_installed',
        'dashcam_installed',
        'tag_installed',
        'sorn_applied',
    ];

    /** @var list<string> */
    private const DATES = [
        'purchase_date',
        'available_from_date',
        'insurance_start_date',
        'insurance_expiry_date',
        'insurance_applied_date',
        'insurance_canceled_date',
    ];

    /** @var list<string> */
    private const CREATED_FIELDS = [
        'company_id',
        'car_model_id',
        'registration',
        'color',
        'fleet_status',
        'insurance_provider_id',
        'insurance_status_id',
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Car $car): array
    {
        $snapshot = [];

        foreach (array_keys(self::LABELS) as $field) {
            if (str_starts_with($field, 'insurance_')) {
                continue;
            }

            $snapshot[$field] = $car->getAttribute($field);
        }

        $insurance = $car->insurances()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $snapshot['insurance_provider_id'] = $insurance?->insurance_provider_id;
        $snapshot['insurance_status_id'] = $insurance?->status_id;
        $snapshot['insurance_start_date'] = $insurance?->start_date;
        $snapshot['insurance_expiry_date'] = $insurance?->expiry_date;
        $snapshot['insurance_applied_date'] = $insurance?->applied_date;
        $snapshot['insurance_canceled_date'] = $insurance?->canceled_date;

        return $snapshot;
    }

    public function created(Car $car): void
    {
        $before = array_fill_keys(self::CREATED_FIELDS, null);
        $changes = array_values(array_filter(
            $this->diff($car, $before, self::CREATED_FIELDS),
            fn (array $change): bool => ($change['to'] ?? 'empty') !== 'empty'
        ));

        $this->record($car, 'created', 'created the car.', $changes);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function updated(Car $car, array $before): void
    {
        $car = $car->fresh() ?? $car;
        $changes = $this->diff($car, $before);
        $sentence = 'updated the car.';
        $status = collect($changes)->first(
            fn (array $change): bool => ($change['label'] ?? '') === 'Insurance status'
        );

        if (is_array($status)) {
            $sentence = 'updated the car and changed the insurance status from '
                .$status['from'].' to '.$status['to'].'.';
        }

        $this->record($car, 'updated', $sentence, $changes);
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    public function record(Car $car, string $action, string $sentence, array $changes = []): void
    {
        if (! Schema::hasTable('car_activity_logs')) {
            return;
        }

        CarActivityLog::query()->create([
            'car_id' => $car->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'summary' => $this->actorName().' '.$sentence,
            'changes' => $changes === [] ? null : array_values($changes),
        ]);
    }

    public function actorName(): string
    {
        $name = trim((string) (Auth::user()?->name ?? ''));

        return $name !== '' ? $name : 'System';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>|null  $only
     * @return list<array<string, mixed>>
     */
    private function diff(Car $car, array $before, ?array $only = null): array
    {
        $after = $this->snapshot($car);
        $changes = [];

        foreach ($only ?? array_keys(self::LABELS) as $field) {
            $from = $this->display($field, $before[$field] ?? null);
            $to = $this->display($field, $after[$field] ?? null);

            if ($from === $to) {
                continue;
            }

            $changes[] = [
                'label' => self::LABELS[$field] ?? $field,
                'from' => $from,
                'to' => $to,
            ];
        }

        return $changes;
    }

    private function display(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return in_array($field, self::BOOLEANS, true) ? 'No' : 'empty';
        }

        if (in_array($field, self::BOOLEANS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if ($field === 'company_id') {
            $name = Company::query()->whereKey($value)->value('name');

            return is_string($name) && trim($name) !== '' ? trim($name) : 'empty';
        }

        if ($field === 'car_model_id') {
            $name = CarModel::query()->whereKey($value)->value('name');

            return is_string($name) && trim($name) !== '' ? trim($name) : 'empty';
        }

        if ($field === 'insurance_provider_id') {
            $name = InsuranceProvider::query()->whereKey($value)->value('provider_name');

            return is_string($name) && trim($name) !== '' ? trim($name) : 'empty';
        }

        if ($field === 'insurance_status_id') {
            $name = Status::query()->whereKey($value)->value('name');

            return is_string($name) && trim($name) !== '' ? trim($name) : 'empty';
        }

        if ($field === 'fleet_status') {
            $labels = Car::fleetStatusLabels();
            $key = (string) $value;

            return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        if (in_array($field, self::MONEY, true)) {
            return '£'.number_format((float) $value, 2);
        }

        if (in_array($field, self::DATES, true)) {
            try {
                $date = $value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value);
            } catch (\Throwable) {
                return 'empty';
            }

            return $date->format('d M Y');
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : 'empty';
    }
}
