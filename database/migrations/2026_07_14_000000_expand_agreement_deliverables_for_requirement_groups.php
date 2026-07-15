<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_deliverables', function (Blueprint $table) {
            if (!Schema::hasColumn('agreement_deliverables', 'program_id')) {
                $table->foreignId('program_id')->nullable()->after('contact_family_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('agreement_deliverables', 'metric_type')) {
                $table->string('metric_type')->nullable()->after('program_id');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'contribution_basis')) {
                $table->string('contribution_basis')->nullable()->after('metric_type');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'user_grouping_mode')) {
                $table->string('user_grouping_mode')->nullable()->after('contribution_basis');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'include_additional_time')) {
                $table->boolean('include_additional_time')->default(false)->after('user_grouping_mode');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'target_quantity')) {
                $table->decimal('target_quantity', 10, 2)->nullable()->after('include_additional_time');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'suggested_due_date')) {
                $table->date('suggested_due_date')->nullable()->after('program_id');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('suggested_due_date');
            }
            if (!Schema::hasColumn('agreement_deliverables', 'retired_at')) {
                $table->timestamp('retired_at')->nullable()->after('notes');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('agreement_deliverables', 'required_hours') ? 'required_hours' : null,
                Schema::hasColumn('agreement_deliverables', 'required_activities') ? 'required_activities' : null,
            ]));

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        if (!Schema::hasTable('deliverable_user')) {
            Schema::create('deliverable_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_deliverable_id')->constrained('agreement_deliverables')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('source_team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('unassigned_at')->nullable();
                $table->timestamps();

                $table->unique(['agreement_deliverable_id', 'user_id'], 'deliverable_user_unique');
            });
        } else {
            Schema::table('deliverable_user', function (Blueprint $table) {
                if (!Schema::hasColumn('deliverable_user', 'source_team_id')) {
                    $table->foreignId('source_team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
                }
                if (!Schema::hasColumn('deliverable_user', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->after('source_team_id');
                }
                if (!Schema::hasColumn('deliverable_user', 'unassigned_at')) {
                    $table->timestamp('unassigned_at')->nullable()->after('assigned_at');
                }
            });
        }

        if (!Schema::hasTable('deliverable_team')) {
            Schema::create('deliverable_team', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_deliverable_id')->constrained('agreement_deliverables')->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('unassigned_at')->nullable();
                $table->timestamps();

                $table->unique(['agreement_deliverable_id', 'team_id'], 'deliverable_team_unique');
            });
        }

        if (!Schema::hasTable('agreement_activity_histories')) {
            Schema::create('agreement_activity_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
                $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contact_family_id')->constrained('contact_families')->cascadeOnDelete();
                $table->foreignId('activity_type_id')->nullable()->constrained('activity_types')->nullOnDelete();
                $table->foreignId('contributor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('activity_date');
                $table->string('contribution_kind');
                $table->decimal('completion_units', 10, 2)->nullable();
                $table->decimal('activity_hours', 10, 2)->nullable();
                $table->decimal('prep_hours', 10, 2)->default(0);
                $table->decimal('follow_up_hours', 10, 2)->default(0);
                $table->json('program_ids_snapshot')->nullable();
                $table->json('team_ids_snapshot')->nullable();
                $table->timestamps();

                $table->index(['agreement_id', 'activity_id'], 'agreement_activity_histories_activity_idx');
                $table->index(['agreement_id', 'contact_family_id', 'activity_type_id'], 'agreement_activity_histories_context_idx');
                $table->index(['contributor_user_id', 'contribution_kind'], 'agreement_activity_histories_user_kind_idx');
            });
        }

        if (!Schema::hasTable('deliverable_contributions')) {
            Schema::create('deliverable_contributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_activity_history_id')->nullable()->constrained('agreement_activity_histories')->nullOnDelete();
                $table->foreignId('agreement_deliverable_id')->constrained('agreement_deliverables')->cascadeOnDelete();
                $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
                $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contributor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('contribution_kind');
                $table->string('source_assignment_type');
                $table->string('counted_attribution_basis');
                $table->decimal('credited_units', 10, 2)->nullable();
                $table->decimal('credited_hours', 10, 2)->nullable();
                $table->decimal('prep_hours', 10, 2)->default(0);
                $table->decimal('follow_up_hours', 10, 2)->default(0);
                $table->string('rules_fingerprint')->nullable();
                $table->timestamps();

                $table->index(['agreement_id', 'activity_id'], 'deliverable_contributions_activity_idx');
                $table->index(['agreement_deliverable_id', 'contribution_kind'], 'deliverable_contributions_deliverable_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_contributions');
        Schema::dropIfExists('agreement_activity_histories');
        Schema::dropIfExists('deliverable_team');
        Schema::dropIfExists('deliverable_user');

        Schema::table('agreement_deliverables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn([
                'metric_type',
                'contribution_basis',
                'user_grouping_mode',
                'include_additional_time',
                'target_quantity',
                'suggested_due_date',
                'sort_order',
                'retired_at',
            ]);

            $table->decimal('required_hours', 8, 2)->nullable();
            $table->integer('required_activities')->nullable();
        });
    }
};
