<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'agreement_logging_field_assignments' => 'available_in_agreements',
            'contact_family_logging_field_assignments' => 'available_in_contact_families',
            'activity_type_logging_field_assignments' => 'available_in_activities',
        ] as $table => $flag) {
            DB::table($table)->whereIn('logging_field_id', DB::table('logging_fields')
                ->select('id')->where($flag, false)->orWhere('is_active', false))->delete();
        }
    }

    /** Removed assignments cannot be reconstructed safely. */
    public function down(): void {}
};
