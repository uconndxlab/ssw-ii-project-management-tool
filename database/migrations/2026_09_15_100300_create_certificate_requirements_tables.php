<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateGroupSatisfyMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_requirement_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->string('phase')->default(CertificateRequirementPhase::Initial->value);
            $table->string('label')->nullable();
            $table->string('satisfy_mode')->default(CertificateGroupSatisfyMode::All->value);
            $table->unsignedInteger('required_count')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['certificate_id', 'phase']);
        });

        Schema::create('certificate_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_requirement_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phase')->default(CertificateRequirementPhase::Initial->value);
            $table->string('kind')->default(CertificateRequirementKind::ActivityCount->value);
            $table->foreignId('contact_family_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('certification_tool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('certification_role_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('target_count')->default(1);
            $table->boolean('requires_passing')->default(true);
            // Displayed to the coach as guidance; the system never compares scores against it.
            $table->string('threshold_note')->nullable();
            // Overrides the certificate's default rolling window when set.
            $table->unsignedInteger('window_months')->nullable();
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['certificate_id', 'phase']);
            $table->index('kind');
        });

        Schema::create('certificate_requirement_dimension_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_tool_dimension_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default(CertificateDimensionRuleMode::Coverage->value);
            $table->json('option_ids')->nullable();
            $table->unsignedInteger('min_count')->nullable();
            $table->timestamps();

            $table->index('certificate_requirement_id', 'cert_req_dimension_rule_req_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_requirement_dimension_rules');
        Schema::dropIfExists('certificate_requirements');
        Schema::dropIfExists('certificate_requirement_groups');
    }
};
