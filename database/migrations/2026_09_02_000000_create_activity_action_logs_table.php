<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_action_logs', function (Blueprint $table) {
            $table->id();
            // Snapshot ids: keep rows after the activity (or related copy) is deleted.
            $table->unsignedBigInteger('activity_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->unsignedBigInteger('related_activity_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('activity_action_logs')->insertUsing(
            ['activity_id', 'user_id', 'action', 'created_at'],
            DB::table('activities')
                ->select('id', 'user_id', DB::raw("'create'"), 'created_at')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw('1'))
                        ->from('activity_action_logs')
                        ->whereColumn('activity_action_logs.activity_id', 'activities.id')
                        ->where('activity_action_logs.action', 'create');
                }),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_action_logs');
    }
};
