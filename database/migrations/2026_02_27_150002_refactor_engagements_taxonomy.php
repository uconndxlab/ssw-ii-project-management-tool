<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ContactFamily;
use App\Models\ActivityType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add activity_type_id column (nullable temporarily for migration)
        Schema::table('engagements', function (Blueprint $table) {
            $table->foreignId('activity_type_id')->nullable()->after('engagement_date')->constrained()->cascadeOnDelete();
        });

        // Step 2: Migrate existing data
        $this->migrateExistingData();

        // Step 3: Make activity_type_id NOT NULL and drop old columns
        Schema::table('engagements', function (Blueprint $table) {
            $table->foreignId('activity_type_id')->nullable(false)->change();
            $table->dropColumn(['activity_type', 'deliverable_bucket']);
        });
    }

    /**
     * Migrate existing string-based data to the new taxonomy structure.
     */
    private function migrateExistingData(): void
    {
        // Get all unique combinations of deliverable_bucket and activity_type
        $combinations = DB::table('engagements')
            ->select('deliverable_bucket', 'activity_type')
            ->whereNotNull('deliverable_bucket')
            ->whereNotNull('activity_type')
            ->distinct()
            ->get();

        // Create/find contact families and activity types, then update engagements
        foreach ($combinations as $combo) {
            // Create or find contact family (using deliverable_bucket as the family name)
            $familyName = $this->formatName($combo->deliverable_bucket);
            $contactFamily = ContactFamily::firstOrCreate(
                ['name' => $familyName],
                ['active' => true, 'sort_order' => 0]
            );

            // Create or find activity type
            $activityName = $this->formatName($combo->activity_type);
            $activityType = ActivityType::firstOrCreate(
                [
                    'contact_family_id' => $contactFamily->id,
                    'name' => $activityName,
                ],
                ['active' => true, 'sort_order' => 0]
            );

            // Update all engagements with this combination
            DB::table('engagements')
                ->where('deliverable_bucket', $combo->deliverable_bucket)
                ->where('activity_type', $combo->activity_type)
                ->update(['activity_type_id' => $activityType->id]);
        }

        // Handle any engagements with NULL values - create "Uncategorized" fallback
        $nullCount = DB::table('engagements')
            ->whereNull('activity_type_id')
            ->count();

        if ($nullCount > 0) {
            $uncategorizedFamily = ContactFamily::firstOrCreate(
                ['name' => 'Uncategorized'],
                ['active' => true, 'sort_order' => 9999]
            );

            $unspecifiedType = ActivityType::firstOrCreate(
                [
                    'contact_family_id' => $uncategorizedFamily->id,
                    'name' => 'Unspecified',
                ],
                ['active' => true, 'sort_order' => 9999]
            );

            DB::table('engagements')
                ->whereNull('activity_type_id')
                ->update(['activity_type_id' => $unspecifiedType->id]);
        }
    }

    /**
     * Format underscore-separated strings to Title Case
     */
    private function formatName(string $value): string
    {
        return str_replace('_', ' ', ucwords($value, '_'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Re-add the old columns
        Schema::table('engagements', function (Blueprint $table) {
            $table->string('activity_type')->nullable()->after('engagement_date');
            $table->string('deliverable_bucket')->nullable()->after('activity_type');
        });

        // Step 2: Attempt to restore data from relationships
        $engagements = DB::table('engagements')
            ->join('activity_types', 'engagements.activity_type_id', '=', 'activity_types.id')
            ->join('contact_families', 'activity_types.contact_family_id', '=', 'contact_families.id')
            ->select(
                'engagements.id',
                'activity_types.name as activity_name',
                'contact_families.name as family_name'
            )
            ->get();

        foreach ($engagements as $engagement) {
            DB::table('engagements')
                ->where('id', $engagement->id)
                ->update([
                    'activity_type' => strtolower(str_replace(' ', '_', $engagement->activity_name)),
                    'deliverable_bucket' => strtolower(str_replace(' ', '_', $engagement->family_name)),
                ]);
        }

        // Step 3: Drop the activity_type_id column
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropForeign(['activity_type_id']);
            $table->dropColumn('activity_type_id');
        });
    }
};
