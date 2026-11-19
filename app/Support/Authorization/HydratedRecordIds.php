<?php

namespace App\Support\Authorization;

use Illuminate\Database\Eloquent\Model;

/**
 * Request-scoped ids collected by VisibleToUser's retrieved event, including eager loads.
 * Collection does no authorization work. On a UserAccess cache miss, the collected ids
 * are checked together using the same visibility filter as lists, preventing rule drift.
 * Answers belong to each UserAccess instance for the request; ids reset with the scoped
 * container binding on each request/job/Octane tick. To debug, inspect the query log:
 * expect one IN (...) query per type and predicate, per chunk of up to 1000 pending ids.
 */
class HydratedRecordIds
{
    /** @var array<class-string<Model>, array<int, true>> */
    private array $ids = [];

    /** @param class-string<Model> $class */
    public function add(string $class, int $id): void
    {
        $this->ids[$class][$id] = true;
    }

    /**
     * @param  class-string<Model>  $class
     * @return list<int>
     */
    public function forType(string $class): array
    {
        return array_keys($this->ids[$class] ?? []);
    }
}
