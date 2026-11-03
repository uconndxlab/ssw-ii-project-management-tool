<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $groups = DB::table('certification_candidates')->orderBy('id')->lockForUpdate()->get()
                ->groupBy(fn (object $candidate) => Str::lower(Str::squish($candidate->name)))
                ->filter(fn (Collection $group, int|string $name) => $name !== '' && $group->count() > 1 && $group->contains(fn (object $candidate) => blank($candidate->email)));

            foreach ($groups as $name => $people) {
                $canonical = $people->first(fn (object $person) => filled($person->email)) ?? $people->first();
                $ids = $people->pluck('id')->all();
                $identity = [];
                foreach (['email', 'phone', 'start_date'] as $field) {
                    $values = $people->pluck($field)->filter(fn ($value) => filled($value))->unique()->values();
                    if ($values->count() > 1) {
                        throw new RuntimeException("Cannot reconcile {$name}: conflicting {$field} values. No changes were retained.");
                    }
                    $identity[$field] = $values->first();
                }

                $rosters = DB::table('agreement_certification_candidates')->whereIn('certification_candidate_id', $ids)->orderBy('id')->lockForUpdate()->get();
                foreach ($rosters->groupBy('agreement_id') as $links) {
                    $programIds = $links->pluck('program_id')->filter(fn ($id) => $id !== null)->unique();
                    if ($programIds->count() > 1) {
                        throw new RuntimeException("Cannot reconcile {$name}: conflicting roster programs. No changes were retained.");
                    }
                    $kept = $links->first();
                    DB::table('agreement_certification_candidates')->whereIn('id', $links->pluck('id')->reject(fn ($id) => $id === $kept->id))->delete();
                    DB::table('agreement_certification_candidates')->where('id', $kept->id)->update([
                        'certification_candidate_id' => $canonical->id,
                        'program_id' => $programIds->first(),
                        'notes' => $this->combinedNotes($links),
                        'sort_order' => $links->min('sort_order'),
                    ]);
                }

                $enrollments = DB::table('certificate_enrollments')->whereIn('certification_candidate_id', $ids)->orderBy('id')->lockForUpdate()->get();
                foreach ($enrollments->groupBy('certificate_id') as $assignments) {
                    if ($assignments->count() > 1) {
                        $unstarted = $assignments->every(fn (object $row) => $row->status === 'not_started' && $row->phase === 'initial' && (int) $row->cycle === 1);
                        foreach (['supervising_coach_user_id', 'sponsoring_agreement_id'] as $field) {
                            if ($assignments->pluck($field)->filter(fn ($value) => $value !== null)->unique()->count() > 1) {
                                throw new RuntimeException("Cannot reconcile {$name}: conflicting assignment {$field}. No changes were retained.");
                            }
                        }
                        if (! $unstarted) {
                            throw new RuntimeException("Cannot reconcile {$name}: duplicate assignments have lifecycle history. No changes were retained.");
                        }
                    }
                    $kept = $assignments->first();
                    DB::table('certificate_enrollments')->whereIn('id', $assignments->pluck('id')->reject(fn ($id) => $id === $kept->id))->delete();
                    DB::table('certificate_enrollments')->where('id', $kept->id)->update([
                        'certification_candidate_id' => $canonical->id,
                        'supervising_coach_user_id' => $assignments->pluck('supervising_coach_user_id')->first(fn ($id) => $id !== null),
                        'sponsoring_agreement_id' => $assignments->pluck('sponsoring_agreement_id')->first(fn ($id) => $id !== null),
                        'enrolled_at' => $assignments->min('enrolled_at'),
                        'notes' => $this->combinedNotes($assignments),
                    ]);
                }

                DB::table('certification_candidates')->whereIn('id', $ids)->where('id', '!=', $canonical->id)->delete();
                DB::table('certification_candidates')->where('id', $canonical->id)->update([
                    ...$identity,
                    'name' => Str::squish($canonical->name),
                    'notes' => $this->combinedNotes($people),
                    'active' => $people->contains(fn (object $person) => (bool) $person->active),
                    'created_at' => $people->min('created_at'),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function combinedNotes(Collection $rows): ?string
    {
        $notes = $rows->pluck('notes')->filter(fn ($note) => filled($note))->unique()->implode("\n\n");

        return $notes !== '' ? $notes : null;
    }

    public function down(): void
    {
        throw new RuntimeException('Candidate reconciliation cannot be reversed. Use a backup or a forward correction.');
    }
};
