<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->boolean('require_payor')->default(false)->after('time_tracking_mode');
            $table->boolean('require_payee')->default(false)->after('require_payor');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn(['require_payor', 'require_payee']);
        });
    }
};
