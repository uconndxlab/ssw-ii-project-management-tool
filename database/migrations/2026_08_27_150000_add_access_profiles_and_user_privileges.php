<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_profile')->default(AccessProfile::Member->value)->after('password');
            $table->boolean('is_supervisor')->default(false)->after('access_profile');
        });

        Schema::create('user_privileges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 16);
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'capability', 'scope_type', 'scope_id'], 'user_privileges_unique');
        });

        $adminIds = DB::table('users')->where('role', 'admin')->pluck('id');

        DB::table('users')->where('role', 'admin')->update([
            'access_profile' => AccessProfile::AdminViewer->value,
        ]);

        DB::table('users')->where('role', '!=', 'admin')->orWhereNull('role')->update([
            'access_profile' => AccessProfile::Member->value,
        ]);

        $supervisorIds = DB::table('users')
            ->whereNotNull('supervisor_id')
            ->distinct()
            ->pluck('supervisor_id');

        if ($supervisorIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $supervisorIds)->update([
                'is_supervisor' => true,
            ]);
        }

        $now = now();
        foreach ($adminIds as $userId) {
            DB::table('user_privileges')->insert([
                'user_id' => $userId,
                'capability' => PrivilegeCapability::Admin->value,
                'scope_type' => PrivilegeScopeType::System->value,
                'scope_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('password');
        });

        $systemAdminIds = DB::table('user_privileges')
            ->where('capability', PrivilegeCapability::Admin->value)
            ->where('scope_type', PrivilegeScopeType::System->value)
            ->pluck('user_id');

        DB::table('users')->update(['role' => 'staff']);
        DB::table('users')->whereIn('id', $systemAdminIds)->update(['role' => 'admin']);

        Schema::dropIfExists('user_privileges');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_profile', 'is_supervisor']);
        });
    }
};
