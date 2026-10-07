<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class KeywordSearch
{
    public static function apply(Builder $query, ?string $search, array $columns): void
    {
        $keywords = preg_split('/\s+/u', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($keywords === []) {
            return;
        }

        foreach ($keywords as $keyword) {
            $query->where(function (Builder $match) use ($columns, $keyword) {
                foreach ($columns as $column) {
                    $match->orWhereRaw(
                        'LOWER('.$column.') LIKE ?',
                        ['%'.mb_strtolower($keyword, 'UTF-8').'%']
                    );
                }
            });
        }
    }
}
