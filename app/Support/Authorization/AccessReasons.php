<?php

namespace App\Support\Authorization;

use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AccessReasons
{
    /**
     * Project the same predicates used by list visibility for the entire page in one query.
     *
     * @param  Collection<int, Activity>|Collection<int, Agreement>  $records
     * @return array<int, list<array{label: string, explanation: string}>>
     */
    public static function forPage(Collection $records, User $user): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $activity = $records->first() instanceof Activity;
        $class = $activity ? Activity::class : Agreement::class;
        $table = $activity ? 'activities' : 'agreements';
        $access = $user->access();
        $predicates = $activity ? $access->activityVisibilityPredicates() : $access->agreementVisibilityPredicates();
        unset($predicates['Privilege']);
        $explanations = [
            'Logged' => 'You logged this activity.',
            'Delivered by' => 'You are listed as delivering this activity.',
            'Reviewer' => 'You were asked to review this activity, or you coach a candidate tagged on it.',
            'Supervisor' => $activity
                ? 'A direct report logged, delivered, or reviews this activity.'
                : 'A direct report is on the staff or an attached team for this agreement.',
            'Staff' => 'You are on the staff for this agreement.',
            'Team' => 'A team you belong to is on this agreement.',
        ];
        $reasons = [];
        foreach ($predicates as $label => $predicate) {
            $reasons[] = ['label' => $label, 'explanation' => $explanations[$label], 'predicate' => $predicate];
        }

        if ($access->hasView()) {
            foreach (self::privilegeScopes($access) as $scope) {
                $scoped = $activity ? $access->activityVisibilityPredicates($scope['ids']) : $access->agreementVisibilityPredicates($scope['ids']);
                if (! isset($scoped['Privilege'])) {
                    continue;
                }
                $recordScope = $activity ? 'this activity or one of its agreements' : 'this agreement';
                $level = $scope['admin'] ? 'an admin' : 'a viewer';
                $reasons[] = [
                    'label' => ($scope['admin'] ? 'Admin' : 'Viewer').' · '.$scope['name'],
                    'explanation' => "You are {$level} for {$scope['name']}, which covers a program on {$recordScope}.",
                    'predicate' => $scoped['Privilege'],
                ];
            }
        }

        $query = DB::table($table, 'access_rows')->whereIn('access_rows.id', $records->modelKeys())->select('access_rows.id');
        foreach ($reasons as $index => $reason) {
            $match = $class::query()->selectRaw('1')
                ->whereColumn($table.'.id', 'access_rows.id')
                ->where($reason['predicate'])->limit(1);
            // Membership alone cannot explain access to an inactive agreement.
            if (! $activity && in_array($reason['label'], ['Staff', 'Team', 'Supervisor'], true)) {
                $access->applyActiveAgreement($match);
            }
            $query->selectSub($match, 'reason_'.$index);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $matches = [];
            foreach ($reasons as $index => $reason) {
                if ($row->{'reason_'.$index}) {
                    $matches[] = ['label' => $reason['label'], 'explanation' => $reason['explanation']];
                }
            }
            if ($access->hasSystemView()) {
                $matches[] = [
                    'label' => $access->isSystemAdmin() ? 'System admin' : 'System viewer',
                    'explanation' => $access->isSystemAdmin() ? 'You have admin access across the system.' : 'You have view access across the system.',
                ];
            }
            $result[(int) $row->id] = $matches;
        }

        return $result;
    }

    /** @return list<array{name: string, ids: list<int>, admin: bool}> */
    private static function privilegeScopes(UserAccess $access): array
    {
        $privileges = $access->privileges()->reject(fn ($privilege) => $privilege->scope_type === PrivilegeScopeType::System)
            ->groupBy(fn ($privilege) => $privilege->scope_type->value.':'.$privilege->scope_id)
            ->map(fn ($group) => $group->firstWhere('capability', PrivilegeCapability::Admin) ?? $group->firstOrFail());
        if ($privileges->isEmpty()) {
            return [];
        }

        $programs = Program::query()->whereIn('id', $access->viewProgramIds())->with('projects:id,name')->get(['id', 'name']);
        $scopes = [];
        foreach ($privileges as $privilege) {
            if ($privilege->scope_type === PrivilegeScopeType::Program) {
                $program = $programs->find($privilege->scope_id);
                if ($program === null) {
                    continue;
                }
                $name = $program->name;
                $ids = [$program->id];
            } else {
                $matching = $programs->filter(fn (Program $program) => $program->projects->contains('id', $privilege->scope_id));
                $project = $matching->first()?->projects->find($privilege->scope_id);
                if ($project === null) {
                    continue;
                }
                $name = $project->name;
                $ids = $matching->modelKeys();
            }
            $scopes[] = ['name' => $name, 'ids' => array_values($ids), 'admin' => $privilege->capability === PrivilegeCapability::Admin];
        }

        usort($scopes, fn (array $a, array $b) => ($b['admin'] <=> $a['admin']) ?: strnatcasecmp($a['name'], $b['name']));

        return $scopes;
    }
}
