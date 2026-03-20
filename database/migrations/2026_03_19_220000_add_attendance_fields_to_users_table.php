<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default(UserRole::Member->value)->after('email');
            $table->string('employee_code')->nullable()->unique()->after('role');
            $table->string('position')->nullable()->after('employee_code');
            $table->uuid('qr_token')->nullable()->unique()->after('position');
        });

        DB::table('users')
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'employee_code' => $user->employee_code ?: 'ATT-'.Str::upper(Str::random(6)),
                        'qr_token' => $user->qr_token ?: (string) Str::uuid(),
                        'position' => $user->position ?: 'Team Member',
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['employee_code']);
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['role', 'employee_code', 'position', 'qr_token']);
        });
    }
};
