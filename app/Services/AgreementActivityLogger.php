<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\AgreementActivityLog;
use App\Models\Car;
use App\Models\Company;
use App\Models\Driver;
use App\Models\InsuranceProvider;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AgreementActivityLogger
{
    /** @var array<string, string> */
    private const LABELS = [
        'company_id' => 'Company',
        'driver_id' => 'Driver',
        'car_id' => 'Vehicle',
        'status_id' => 'Status',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'billing_anchor_date' => 'Billing anchor date',
        'agreed_rent' => 'Agreed rent',
        'rent_interval' => 'Rent interval',
        'deposit_amount' => 'Deposit',
        'discount_type' => 'Discount type',
        'discount_value' => 'Discount value',
        'discount_notes' => 'Discount notes',
        'discount_is_one_time' => 'One-time discount',
        'collection_type' => 'Collection type',
        'mileage_out' => 'Mileage out',
        'mileage_in' => 'Mileage in',
        'notes' => 'Notes',
        'paying_company_name' => 'Paying company',
        'termination_notice_date' => 'Termination notice date',
        'termination_available_from_date' => 'Available from',
        'termination_notes' => 'Termination notes',
        'closing_date' => 'Closing date',
        'using_own_insurance' => 'Using own insurance',
        'insurance_provider_id' => 'Insurance provider',
        'own_insurance_provider_name' => 'Own insurance provider',
        'own_insurance_start_date' => 'Own insurance start date',
        'own_insurance_end_date' => 'Own insurance end date',
        'own_insurance_type' => 'Own insurance type',
        'own_insurance_policy_number' => 'Own insurance policy number',
        'swap_reason' => 'Swap reason',
        'swap_reason_notes' => 'Swap notes',
        'condition_report' => 'Condition report',
        'refund_person_name' => 'Refund payee',
        'refund_sort_code' => 'Refund sort code',
        'refund_account_number' => 'Refund account number',
        'payment_bank_account_id' => 'Payment bank account',
        'hellosign_status' => 'Signature status',
    ];

    /** @var list<string> */
    private const SENSITIVE = [
        'refund_sort_code',
        'refund_account_number',
        'payment_bank_account_id',
    ];

    /** @var list<string> */
    private const MONEY = [
        'agreed_rent',
        'deposit_amount',
        'discount_value',
    ];

    /** @var list<string> */
    private const BOOLEANS = [
        'discount_is_one_time',
        'using_own_insurance',
    ];

    /** @var list<string> */
    private const DATETIMES = [
        'start_date',
        'closing_date',
    ];

    /** @var list<string> */
    private const DATES = [
        'end_date',
        'billing_anchor_date',
        'termination_notice_date',
        'termination_available_from_date',
        'own_insurance_start_date',
        'own_insurance_end_date',
    ];

    /** @var list<string> */
    private const CREATED_FIELDS = [
        'company_id',
        'driver_id',
        'car_id',
        'status_id',
        'start_date',
        'end_date',
        'agreed_rent',
        'deposit_amount',
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Agreement $agreement): array
    {
        $snapshot = [];

        foreach (array_keys(self::LABELS) as $field) {
            $snapshot[$field] = $agreement->getAttribute($field);
        }

        return $snapshot;
    }

    public function created(Agreement $agreement, ?string $sentence = null): void
    {
        $before = array_fill_keys(self::CREATED_FIELDS, null);
        $changes = array_values(array_filter(
            $this->diff($agreement, $before, self::CREATED_FIELDS),
            fn (array $change): bool => ($change['to'] ?? 'empty') !== 'empty'
        ));

        $this->record($agreement, 'created', $sentence ?? 'created the agreement.', $changes);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function updated(Agreement $agreement, array $before): void
    {
        $agreement = $agreement->fresh() ?? $agreement;
        $changes = $this->diff($agreement, $before);
        $sentence = 'updated the agreement.';
        $status = collect($changes)->first(
            fn (array $change): bool => ($change['label'] ?? '') === 'Status' && empty($change['sensitive'])
        );

        if (is_array($status)) {
            $sentence = 'updated the agreement and changed the status from '
                .$status['from'].' to '.$status['to'].'.';
        }

        $this->record($agreement, 'updated', $sentence, $changes);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function changed(Agreement $agreement, array $before, string $action, string $sentence): void
    {
        $agreement = $agreement->fresh() ?? $agreement;
        $this->record($agreement, $action, $sentence, $this->diff($agreement, $before));
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    public function record(Agreement $agreement, string $action, string $sentence, array $changes = [], bool $system = false): void
    {
        if (! Schema::hasTable('agreement_activity_logs')) {
            return;
        }

        AgreementActivityLog::query()->create([
            'agreement_id' => $agreement->id,
            'user_id' => $system ? null : Auth::id(),
            'action' => $action,
            'summary' => $this->actorName($system).' '.$sentence,
            'changes' => $changes === [] ? null : array_values($changes),
        ]);
    }

    public function actorName(bool $system = false): string
    {
        if ($system) {
            return 'System';
        }

        $name = trim((string) (Auth::user()?->name ?? ''));

        return $name !== '' ? $name : 'System';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>|null  $only
     * @return list<array<string, mixed>>
     */
    private function diff(Agreement $agreement, array $before, ?array $only = null): array
    {
        $changes = [];
        $fields = $only ?? array_keys(self::LABELS);

        foreach ($fields as $field) {
            $label = self::LABELS[$field] ?? $field;
            $fromRaw = $before[$field] ?? null;
            $toRaw = $agreement->getAttribute($field);

            if (in_array($field, self::SENSITIVE, true)) {
                if ($this->rawString($fromRaw) === $this->rawString($toRaw)) {
                    continue;
                }

                $changes[] = [
                    'label' => $label,
                    'sensitive' => true,
                ];

                continue;
            }

            $from = $this->display($field, $fromRaw);
            $to = $this->display($field, $toRaw);

            if ($from === $to) {
                continue;
            }

            $changes[] = [
                'label' => $label,
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

        if ($field === 'status_id') {
            return $this->lookupName(Status::class, $value) ?? 'empty';
        }

        if ($field === 'driver_id') {
            $driver = Driver::query()->find($value);

            return $driver ? (trim($driver->full_name) ?: 'empty') : 'empty';
        }

        if ($field === 'car_id') {
            $registration = Car::query()->whereKey($value)->value('registration');

            return is_string($registration) && $registration !== '' ? $registration : 'empty';
        }

        if ($field === 'company_id') {
            return $this->lookupName(Company::class, $value) ?? 'empty';
        }

        if ($field === 'insurance_provider_id') {
            return $this->lookupName(InsuranceProvider::class, $value) ?? 'empty';
        }

        if (in_array($field, self::MONEY, true)) {
            return '£'.number_format((float) $value, 2);
        }

        if (in_array($field, self::DATETIMES, true)) {
            return $this->formatDate($value, true);
        }

        if (in_array($field, self::DATES, true)) {
            return $this->formatDate($value, false);
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : 'empty';
    }

    private function formatDate(mixed $value, bool $withTime): string
    {
        try {
            $date = $value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return 'empty';
        }

        if ($withTime && ($date->hour !== 0 || $date->minute !== 0 || $date->second !== 0)) {
            return $date->format('d M Y H:i');
        }

        return $date->format('d M Y');
    }

    private function lookupName(string $model, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $name = $model::query()->whereKey($id)->value('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function rawString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
};
