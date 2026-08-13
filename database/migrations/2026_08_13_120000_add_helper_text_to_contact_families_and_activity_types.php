<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_families', function (Blueprint $table) {
            $table->text('helper_text')->nullable()->after('name');
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->text('helper_text')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('contact_families', function (Blueprint $table) {
            $table->dropColumn('helper_text');
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn('helper_text');
        });
    }
};