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
    private const DEFAULT_PASSWORD = 'attendance123';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $memberAccounts = [
            [
                'name' => 'Elmar B. Noche',
                'sub_name' => 'Alexander Bennett',
                'email' => 'a.bennett@duscaff.com',
                'employee_code' => 'DUS-001',
                'hourly_rate' => 3.00,
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Jonathan F. Quiles',
                'sub_name' => 'James Whitaker',
                'email' => 'j.whitaker@duscaff.com',
                'employee_code' => 'DUS-002',
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Dexter L. Javelosa',
                'sub_name' => 'Daniel Hoffman',
                'email' => 'd.hoffman@duscaff.com',
                'employee_code' => 'DUS-003',
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Rosielyn F. Laron',
                'sub_name' => 'Isabella Rossi',
                'email' => 'i.rossi@duscaff.com',
                'employee_code' => 'DUS-004',
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Maria Lorena Sheen P. Velasco',
                'sub_name' => 'Charlotte Fischer',
                'email' => 'c.fischer@duscaff.com',
                'employee_code' => 'DUS-005',
                'night_shift_eligible' => true,
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Fiona Ley Maramba',
                'sub_name' => 'Amelia Dubois',
                'email' => 'a.dubois@duscaff.com',
                'employee_code' => 'DUS-006',
                'night_shift_eligible' => true,
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Haryll L. Caido',
                'sub_name' => 'Emily Carter',
                'email' => 'e.carter@duscaff.com',
                'employee_code' => 'DUS-008',
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Sheenah Anne Ablen',
                'sub_name' => null,
                'email' => 'sheenah.anne.ablen@teamph.local',
                'employee_code' => 'DUS-007',
                'status' => User::STATUS_INACTIVE,
            ],
        ];

        $systemAccounts = [
            [
                'name' => 'System Super Admin',
                'sub_name' => null,
                'email' => 'superadmin@duscaff.local',
                'role' => UserRole::SuperAdmin,
                'position' => 'Super Administrator',
                'employee_code' => 'DUS-SUPER',
                'status' => User::STATUS_ACTIVE,
            ],
            [
                'name' => 'Attendance Admin',
                'sub_name' => null,
                'email' => 'admin@duscaff.local',
                'role' => UserRole::Admin,
                'position' => 'Attendance Administrator',
                'employee_code' => 'DUS-ADMIN',
                'status' => User::STATUS_ACTIVE,
            ],
        ];

        $accounts = [
            ...$systemAccounts,
            ...collect($memberAccounts)->map(function (array $member): array {
                return [
                    'name' => $member['name'],
                    'sub_name' => $member['sub_name'],
                    'email' => $member['email'],
                    'role' => UserRole::Member,
                    'position' => 'Team Member',
                    'employee_code' => $member['employee_code'],
                    'hourly_rate' => $member['hourly_rate'] ?? 2.00,
                    'night_shift_eligible' => $member['night_shift_eligible'] ?? false,
                    'status' => $member['status'],
                ];
            })->all(),
        ];

        foreach ($accounts as $account) {
            $existingUser = User::query()
                ->where('employee_code', $account['employee_code'])
                ->first();

            if (! $existingUser) {
                $existingUser = User::query()
                    ->where('email', $account['email'])
                    ->first();
            }

            $conflictingEmailUser = User::query()
                ->where('email', $account['email'])
                ->first();

            $user = $existingUser ?? new User();

            if ($conflictingEmailUser && ! $conflictingEmailUser->is($user)) {
                $conflictingEmailUser->forceFill([
                    'email' => 'seed-temp-'.$conflictingEmailUser->id.'-'.Str::lower(Str::random(8)).'@duscaff.local',
                ])->save();
            }

            $user->fill([
                ...$account,
                'email_verified_at' => now(),
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'qr_token' => $existingUser?->qr_token ?: (string) Str::uuid(),
            ]);

            $user->save();
        }
    }
}
