<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $agreements = DB::table('agreements')
            ->select('id', 'certification_candidates')
            ->whereNotNull('certification_candidates')
            ->get();

        foreach ($agreements as $agreement) {
            $names = collect(preg_split('/\r\n|\r|\n/', (string) $agreement->certification_candidates))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values();

            foreach ($names as $name) {
                DB::table('agreement_certification_candidates')->insert([
                    'agreement_id' => $agreement->id,
                    'name' => $name,
                    'program_id' => null,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('certification_candidates');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->text('certification_candidates')->nullable()->after('extended_end_date');
        });

        $agreements = DB::table('agreements')->select('id')->get();

        foreach ($agreements as $agreement) {
            $value = DB::table('agreement_certification_candidates')
                ->where('agreement_id', $agreement->id)
                ->orderBy('id')
                ->pluck('name')
                ->filter(fn ($name) => filled($name))
                ->implode("\n");

            DB::table('agreements')
                ->where('id', $agreement->id)
                ->update([
                    'certification_candidates' => $value !== '' ? $value : null,
                ]);
        }
    }
};