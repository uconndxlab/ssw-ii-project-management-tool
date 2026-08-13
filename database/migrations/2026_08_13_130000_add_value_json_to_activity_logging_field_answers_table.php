<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logging_field_answers', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logging_field_answers', 'value_json')) {
                $table->json('value_json')->nullable()->after('value_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logging_field_answers', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logging_field_answers', 'value_json')) {
                $table->dropColumn('value_json');
            }
        });
    }
};
