<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class DriverKeywordSearch
{
    public static function applyToDriverRelation(Builder $driver, string $keyword): void
    {
        $driver->where(function (Builder $match) use ($keyword) {
            self::applyNameAndPostcodeMatch($match, $keyword);
        });
    }

    public static function applyNameAndPostcodeMatch(Builder $match, string $keyword): void
    {
        $keyword = trim($keyword);
        $postcodeNormalized = strtoupper(preg_replace('/\s+/', '', $keyword) ?? '');

        $match->where('first_name', 'like', "%{$keyword}%")
            ->orWhere('middle_name', 'like', "%{$keyword}%")
            ->orWhere('last_name', 'like', "%{$keyword}%")
            ->orWhere('post_code', 'like', "%{$keyword}%");

        if ($postcodeNormalized !== '') {
            $match->orWhereRaw(
                "REPLACE(UPPER(COALESCE(post_code, '')), ' ', '') LIKE ?",
                ['%'.$postcodeNormalized.'%']
            );
        }

        $parts = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($parts) && count($parts) >= 2) {
            $first = $parts[0];
            $last = $parts[array_key_last($parts)];
            $match->orWhere(function (Builder $name) use ($first, $last) {
                $name->where('first_name', 'like', "%{$first}%")
                    ->where('last_name', 'like', "%{$last}%");
            });
        }
    }
}
