<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('po_number', 7)->nullable()->unique()->after('active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('po_number', 7)->nullable()->unique()->after('active');
        });

        DB::table('organizations')->update([
            'po_number' => DB::raw('kfs_number'),
        ]);

        DB::table('users')->update([
            'po_number' => DB::raw('kfs_number'),
        ]);

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['kfs_number']);
            $table->dropColumn('kfs_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['kfs_number']);
            $table->dropColumn('kfs_number');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('kfs_number', 7)->nullable()->unique()->after('active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('kfs_number', 7)->nullable()->unique()->after('active');
        });

        DB::table('organizations')->update([
            'kfs_number' => DB::raw('po_number'),
        ]);

        DB::table('users')->update([
            'kfs_number' => DB::raw('po_number'),
        ]);

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['po_number']);
            $table->dropColumn('po_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['po_number']);
            $table->dropColumn('po_number');
        });
    }
};
