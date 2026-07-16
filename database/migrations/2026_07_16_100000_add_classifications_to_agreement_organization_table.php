<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agreement_organization', function (Blueprint $table) {
            $table->boolean('payor_source')->default(false)->after('organization_id');
            $table->boolean('recipient')->default(false)->after('payor_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreement_organization', function (Blueprint $table) {
            $table->dropColumn(['payor_source', 'recipient']);
        });
    }
};
