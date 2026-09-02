<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logging_field_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logging_field_answers', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logging_field_answers', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logging_field_answers', 'file_name')) {
                $table->dropColumn('file_name');
            }
        });
    }
};
