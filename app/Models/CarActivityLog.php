<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarActivityLog extends Model
{
    protected $fillable = [
        'car_id',
        'user_id',
        'action',
        'summary',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public function changeLines(): array
    {
        $lines = [];

        foreach ($this->getAttribute('changes') ?? [] as $change) {
            if (! is_array($change) || empty($change['label'])) {
                continue;
            }

            $from = $change['from'] ?? 'empty';
            $to = $change['to'] ?? 'empty';
            $lines[] = $change['label'].': '.$from.' → '.$to;
        }

        return $lines;
    }
}
