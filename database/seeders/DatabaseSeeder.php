<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'System Super Admin',
                'email' => 'superadmin@duscaff.local',
                'role' => UserRole::SuperAdmin,
                'position' => 'Super Administrator',
                'employee_code' => 'DUS-SUPER',
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Attendance Admin',
                'email' => 'admin@duscaff.local',
                'role' => UserRole::Admin,
                'position' => 'Attendance Administrator',
                'employee_code' => 'DUS-ADMIN',
                'status' => User::STATUS_ACTIVE,
            ],
            ...collect([
                'Elmar B. Noche',
                'Jonathan F. Quiles',
                'Dexter L. Javelosa',
                'Rosielyn F. Laron',
                'Maria Lorena Sheen P. Velasco',
                'Fiona Ley Maramba',
                'Sheenah Anne Ablen',
            ])->map(function (string $name, int $index): array {
                $email = Str::of($name)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '.')
                    ->trim('.')
                    ->append('@teamph.local')
                    ->value();

                return [
                    'name' => $name,
                    'email' => $email,
                    'role' => UserRole::Member,
                    'position' => 'Team Member',
                    'employee_code' => 'DUS-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'status' => User::STATUS_ACTIVE,
                ];
            })->all(),
        ];

        foreach ($accounts as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    ...$account,
                    'email_verified_at' => now(),
                    'password' => Hash::make('attendance123'),
                    'qr_token' => User::query()
                        ->where('email', $account['email'])
                        ->value('qr_token') ?: (string) Str::uuid(),
                ],
            );
        }
    }
}
