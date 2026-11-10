<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementActivityLog extends Model
{
    protected $fillable = [
        'agreement_id',
        'user_id',
        'action',
        'summary',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
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

            if (! empty($change['sensitive'])) {
                $lines[] = $change['label'].': changed';

                continue;
            }

            $from = $change['from'] ?? 'empty';
            $to = $change['to'] ?? 'empty';
            $lines[] = $change['label'].': '.$from.' → '.$to;
        }

        return $lines;
    }
};
