<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TeamScope
{
    public static function isLimited(User $u): bool
    {
        return $u->hasRole('atasan') && ! $u->hasAnyRole(['super_admin', 'hrd']);
    }

    /** $relation = nama relasi ke Employee; null jika query-nya sudah langsung di model Employee. */
    public static function restrict(Builder $query, User $u, ?string $relation = 'employee'): Builder
    {
        if (! self::isLimited($u)) {
            return $query;
        }

        $managerId = $u->employee?->id ?? 0;

        return $relation
            ? $query->whereHas($relation, fn ($e) => $e->where('manager_id', $managerId))
            : $query->where('manager_id', $managerId);
    }
}