<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kfs_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 7)->unique();
            $table->timestamps();
        });

        Schema::create('agreement_kfs_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kfs_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agreement_id', 'kfs_account_id']);
        });

        Schema::create('agreement_organization_kfs_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kfs_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique([
                'agreement_id',
                'organization_id',
                'kfs_account_id',
            ], 'agreement_organization_kfs_unique');
        });

        Schema::table('activity_agreement_funding_sources', function (Blueprint $table) {
            $table->json('kfs_numbers_snapshot')->nullable()->after('source_id');
            $table->string('po_number_snapshot', 32)->nullable()->after('kfs_numbers_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('activity_agreement_funding_sources', function (Blueprint $table) {
            $table->dropColumn(['kfs_numbers_snapshot', 'po_number_snapshot']);
        });

        Schema::dropIfExists('agreement_organization_kfs_account');
        Schema::dropIfExists('agreement_kfs_account');
        Schema::dropIfExists('kfs_accounts');
    }
};
