<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'not_yet_complete')) {
                $table->boolean('not_yet_complete')->default(false)->after('cancelled');
            }
        });

        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('agreement_activity_histories', 'not_yet_complete')) {
                $table->boolean('not_yet_complete')->default(false)->after('cancelled');
            }
        });

        Schema::table('deliverable_contributions', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverable_contributions', 'not_yet_complete')) {
                $table->boolean('not_yet_complete')->default(false)->after('cancelled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliverable_contributions', function (Blueprint $table) {
            if (Schema::hasColumn('deliverable_contributions', 'not_yet_complete')) {
                $table->dropColumn('not_yet_complete');
            }
        });

        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            if (Schema::hasColumn('agreement_activity_histories', 'not_yet_complete')) {
                $table->dropColumn('not_yet_complete');
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'not_yet_complete')) {
                $table->dropColumn('not_yet_complete');
            }
        });
    }
};
