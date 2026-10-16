<?php

namespace App\Services;

use App\Enums\RecentRecordType;
use App\Models\RecentRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class RecentRecordService
{
    public const MAX_RECORDS = 30;

    public const RETENTION_DAYS = 7;

    public function record(User $user, RecentRecordType $type, int $recordId): void
    {
        RecentRecord::query()->upsert(
            [[
                'user_id' => $user->id,
                'record_type' => $type->value,
                'record_id' => $recordId,
                'last_opened_at' => now(),
            ]],
            ['user_id', 'record_type', 'record_id'],
            ['last_opened_at'],
        );

        $this->prune($user);
    }

    /**
     * Records the user may still open, newest first. Deleted records are removed; records the
     * user can no longer view are hidden without being forgotten.
     *
     * @return Collection<int, array{type: RecentRecordType, title: string, url: string, opened_at: Carbon}>
     */
    public function listFor(User $user): Collection
    {
        $this->prune($user);

        $rows = RecentRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_opened_at')
            ->orderByDesc('id')
            ->get();

        $idsByType = [];
        foreach ($rows as $row) {
            $idsByType[$row->record_type->value][] = $row->record_id;
        }

        $records = [];
        foreach ($idsByType as $type => $ids) {
            $recordType = RecentRecordType::from($type);
            $query = $recordType->modelClass()::query()->whereKey($ids);

            if ($recordType === RecentRecordType::Activity) {
                $query->with('activityType');
            }

            $records[$type] = $query->get()->keyBy('id');
        }

        $gate = Gate::forUser($user);
        $items = [];
        $missingIds = [];

        foreach ($rows as $row) {
            $record = ($records[$row->record_type->value] ?? null)?->get($row->record_id);

            if ($record === null) {
                $missingIds[] = $row->id;

                continue;
            }

            if (! $gate->allows('view', $record)) {
                continue;
            }

            $items[] = [
                'type' => $row->record_type,
                'title' => $row->record_type->titleFor($record),
                'url' => route($row->record_type->showRoute(), $record),
                'opened_at' => $row->last_opened_at,
            ];
        }

        if ($missingIds !== []) {
            RecentRecord::query()->whereKey($missingIds)->delete();
        }

        return collect($items);
    }

    public function clear(User $user): void
    {
        RecentRecord::query()->where('user_id', $user->id)->delete();
    }

    private function prune(User $user): void
    {
        RecentRecord::query()
            ->where('user_id', $user->id)
            ->where('last_opened_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $overflow = RecentRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_opened_at')
            ->orderByDesc('id')
            ->offset(self::MAX_RECORDS)
            ->limit(1000)
            ->pluck('id');

        if ($overflow->isNotEmpty()) {
            RecentRecord::query()->whereKey($overflow)->delete();
        }
    }
}
