<?php

namespace App\Services;

use App\Models\Person;

class DuplicateCheckService
{
    public function find(string $normalizedCnic, ?int $ignorePersonId = null): ?Person
    {
        return Person::query()
            ->withTrashed()
            ->where('cnic', $normalizedCnic)
            ->when($ignorePersonId, fn ($query) => $query->where('id', '!=', $ignorePersonId))
            ->first();
    }

    public function exists(string $normalizedCnic, ?int $ignorePersonId = null): bool
    {
        return $this->find($normalizedCnic, $ignorePersonId) !== null;
    }
}
