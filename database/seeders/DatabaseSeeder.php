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
                'email' => 'superadmin@teamph.local',
                'role' => UserRole::SuperAdmin,
                'position' => 'Super Administrator',
                'employee_code' => 'DUS-SUPER',
            ],
            [
                'name' => 'Attendance Admin',
                'email' => 'admin@teamph.local',
                'role' => UserRole::Admin,
                'position' => 'Attendance Administrator',
                'employee_code' => 'DUS-ADMIN',
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
                    'position' => $name === 'Elmar B. Noche'
                        ? 'Team Manager'
                        : 'Team Member',
                    'employee_code' => 'DUS-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                ];
            })->all(),
        ];

        foreach ($accounts as $account) {
            $existingUser = User::query()
                ->where('employee_code', $account['employee_code'])
                ->first();

            User::query()->updateOrCreate(
                ['employee_code' => $account['employee_code']],
                [
                    ...$account,
                    'email_verified_at' => now(),
                    'password' => Hash::make('attendance123'),
                    'qr_token' => $existingUser?->qr_token ?: (string) Str::uuid(),
                ],
            );
        }
    }
}
