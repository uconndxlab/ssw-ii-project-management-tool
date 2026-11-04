<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retired rows become plain inactive rows so admins can reactivate them. Nothing is deleted.
     */
    public function up(): void
    {
        foreach (['certification_tools', 'certificates'] as $table) {
            DB::table($table)->whereNotNull('retired_at')->update(['retired_at' => null, 'active' => false]);
        }
    }

    /**
     * The original retirement timestamps are not recoverable, so there is nothing to reverse.
     */
    public function down(): void
    {
        //
    }
};
