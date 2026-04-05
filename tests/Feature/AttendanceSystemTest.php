<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('public scanner page can be rendered', function () {
    $this->get('/scan')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/scan'));
});

test('admin can open the user management page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/users')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('users/index'));
});

test('super admin accounts are hidden from the user management page', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
        'name' => 'Hidden Super Admin',
        'email' => 'hidden-super-admin@example.com',
    ]);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
        'name' => 'Visible Admin',
        'email' => 'visible-admin@example.com',
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Visible Member',
        'sub_name' => 'Visible Alias',
        'email' => 'visible-member@example.com',
    ]);

    $this->actingAs($admin)
        ->get('/users')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($admin, $member, $superAdmin): void {
            $page
                ->where('users', function ($users) use ($admin, $member, $superAdmin): bool {
                    $emails = collect($users)->pluck('email');

                    return $emails->contains($admin->email)
                        && $emails->contains($member->email)
                        && ! $emails->contains($superAdmin->email);
                })
                ->where('users.0.status', fn ($status): bool => in_array($status, ['active', 'inactive'], true))
                ->where(
                    'users',
                    fn ($users): bool => collect($users)->contains(
                        fn (array $user): bool => ($user['email'] ?? null) === $member->email
                            && ($user['sub_name'] ?? null) === $member->sub_name,
                    ),
                )
                ->where('allowedRoles', function ($roles): bool {
                    return ! collect($roles)->pluck('value')->contains('super_admin');
                })
                ->where('statusOptions', function ($statuses): bool {
                    return collect($statuses)->pluck('value')->sort()->values()->all() === ['active', 'inactive'];
                });
        });
});

test('admin can open the backup page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
        'name' => 'Zulu Admin',
    ]);

    User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Elmar B. Noche',
        'sub_name' => 'Alexander Bennett',
        'email' => 'elmar-priority@example.com',
    ]);

    $this->actingAs($admin)
        ->get('/backups')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('backups/index')
            ->where('summary.totalWorkHours', '0h 00m')
            ->where('backupUsers.0.name', 'Elmar B. Noche')
            ->where('backupUsers.0.sub_name', 'Alexander Bennett'));
});

test('backup page includes inactive users only when they have attendance in the selected month', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $inactiveWithAttendance = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Inactive With Attendance',
        'status' => User::STATUS_INACTIVE,
    ]);

    $inactiveWithoutAttendance = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Inactive Without Attendance',
        'status' => User::STATUS_INACTIVE,
    ]);

    Attendance::query()->create([
        'user_id' => $inactiveWithAttendance->id,
        'recorded_at' => now()->startOfMonth()->addDay()->setTime(8, 0),
        'entry_type' => 'time_in',
        'scanned_code' => $inactiveWithAttendance->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->actingAs($admin)
        ->get('/backups?year='.now()->year.'&month='.now()->month)
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($inactiveWithAttendance, $inactiveWithoutAttendance): void {
            $page->where('backupUsers', function ($users) use ($inactiveWithAttendance, $inactiveWithoutAttendance): bool {
                $names = collect($users)->pluck('name');

                return $names->contains($inactiveWithAttendance->name)
                    && ! $names->contains($inactiveWithoutAttendance->name);
            });
        });
});

test('members cannot open management pages', function () {
    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($member)->get('/users')->assertForbidden();
    $this->actingAs($member)->get('/attendances')->assertForbidden();
    $this->actingAs($member)->get('/backups')->assertForbidden();
});

test('valid qr code creates an attendance record', function () {
    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $this->post('/scan', [
        'qr_code' => $member->qr_value,
        'entry_type' => 'time_in',
    ])->assertRedirect('/scan');

    $this->assertDatabaseHas('attendances', [
        'user_id' => $member->id,
        'entry_type' => 'time_in',
        'source' => 'qr_scan',
    ]);
});

test('scanner stores the real current scan timestamp', function () {
    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $scanMoment = now()->setTime(8, 15, 0);
    $this->travelTo($scanMoment);

    $this->post('/scan', [
        'qr_code' => $member->qr_value,
        'entry_type' => 'time_in',
    ])->assertRedirect('/scan');

    $attendance = Attendance::query()
        ->where('user_id', $member->id)
        ->latest('recorded_at')
        ->first();

    expect($attendance)->not->toBeNull();
    expect($attendance?->recorded_at?->toDateTimeString())->toBe($scanMoment->toDateTimeString());
});

test('scanner hides and rejects super admin attendance data', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
        'name' => 'Hidden Super Admin',
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Visible Member',
    ]);

    Attendance::query()->create([
        'user_id' => $superAdmin->id,
        'recorded_at' => now()->subMinute(),
        'entry_type' => 'time_in',
        'scanned_code' => $superAdmin->qr_value,
        'source' => 'qr_scan',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now(),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->get('/scan')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($member): void {
            $page
                ->where('teamCount', 1)
                ->has('latestAttendances', 1)
                ->where('latestAttendances.0.user_name', $member->name);
        });

    $this->post('/scan', [
        'qr_code' => $superAdmin->qr_value,
        'entry_type' => 'time_in',
    ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('time out requires a prior time in', function () {
    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $this->post('/scan', [
        'qr_code' => $member->qr_value,
        'entry_type' => 'time_out',
    ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('attendances', [
        'user_id' => $member->id,
        'entry_type' => 'time_out',
    ]);
});

test('time out can be recorded after time in', function () {
    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $this->post('/scan', [
        'qr_code' => $member->qr_value,
        'entry_type' => 'time_in',
    ])->assertRedirect('/scan');

    $this->travel(3)->minutes();

    $this->post('/scan', [
        'qr_code' => $member->qr_value,
        'entry_type' => 'time_out',
    ])->assertRedirect('/scan');

    $this->assertDatabaseHas('attendances', [
        'user_id' => $member->id,
        'entry_type' => 'time_in',
    ]);

    $this->assertDatabaseHas('attendances', [
        'user_id' => $member->id,
        'entry_type' => 'time_out',
    ]);
});

test('admin cannot create another admin account', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Another Admin',
            'email' => 'another-admin@example.com',
            'role' => UserRole::Admin->value,
            'employee_code' => 'ATT-ADMIN1',
            'position' => 'Admin',
            'password' => 'attendance123',
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', [
        'email' => 'another-admin@example.com',
    ]);
});

test('admin can add an inactive member account', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Inactive Member',
            'email' => 'inactive-member@example.com',
            'role' => UserRole::Member->value,
            'position' => 'Support Agent',
            'status' => User::STATUS_INACTIVE,
            'password' => 'attendance123',
        ])
        ->assertRedirect('/users');

    $this->assertDatabaseHas('users', [
        'email' => 'inactive-member@example.com',
        'status' => User::STATUS_INACTIVE,
    ]);

    expect(
        User::query()->where('email', 'inactive-member@example.com')->value('employee_code'),
    )->toStartWith('DUS-');
});

test('admin can update a member status to inactive', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)
        ->patch('/users/'.$member->id.'/status', [
            'status' => User::STATUS_INACTIVE,
        ])
        ->assertRedirect('/users');

    expect($member->fresh()->status)->toBe(User::STATUS_INACTIVE);
});

test('inactive users cannot record attendance', function () {
    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_INACTIVE,
    ]);

    $this->post('/scan', [
        'qr_code' => $member->qr_value,
        'entry_type' => 'time_in',
    ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('attendances', [
        'user_id' => $member->id,
    ]);
});

test('super admin can update attendance timestamps', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $attendance = Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->subHour(),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->actingAs($superAdmin)
        ->patch('/attendances/'.$attendance->id, [
            'recorded_date' => now()->toDateString(),
            'recorded_time' => '08:30',
        ])
        ->assertRedirect('/attendances');

    expect($attendance->fresh()->recorded_at?->format('H:i'))->toBe('08:30');
});

test('super admin can delete attendance records', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    $attendance = Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->subHour(),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->actingAs($superAdmin)
        ->delete('/attendances/'.$attendance->id, [
            'date' => now()->toDateString(),
        ])
        ->assertRedirect('/attendances?date='.now()->toDateString());

    $this->assertDatabaseMissing('attendances', [
        'id' => $attendance->id,
    ]);
});

test('super admin can add a missing time out from attendance management', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->setTime(8, 0),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->actingAs($superAdmin)
        ->post('/attendances/manual-time-out', [
            'user_id' => $member->id,
            'recorded_date' => now()->toDateString(),
            'recorded_time' => '17:05',
            'date' => now()->toDateString(),
        ])
        ->assertRedirect('/attendances?date='.now()->toDateString());

    $this->assertDatabaseHas('attendances', [
        'user_id' => $member->id,
        'entry_type' => 'time_out',
        'source' => 'manual_adjustment',
    ]);
});

test('super admin attendance page includes active agents for manual recording', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
    ]);

    $activeMember = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
        'name' => 'Active Agent',
    ]);

    $inactiveMember = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_INACTIVE,
        'name' => 'Inactive Agent',
    ]);

    $this->actingAs($superAdmin)
        ->get('/attendances?date='.now()->toDateString())
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($activeMember, $inactiveMember): void {
            $page
                ->where('canEditAttendanceTime', true)
                ->where('recordableUsers', function ($users) use ($activeMember, $inactiveMember): bool {
                    $ids = collect($users)->pluck('id');

                    return $ids->contains($activeMember->id)
                        && ! $ids->contains($inactiveMember->id);
                });
        });
});

test('super admin can record a manual time in from attendance management', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    $this->actingAs($superAdmin)
        ->post('/attendances/manual-record', [
            'user_id' => $member->id,
            'entry_type' => 'time_in',
            'recorded_date' => now()->toDateString(),
            'recorded_time' => '08:15',
            'date' => now()->toDateString(),
        ])
        ->assertRedirect('/attendances?date='.now()->toDateString());

    $manualAttendance = Attendance::query()
        ->where('user_id', $member->id)
        ->where('entry_type', 'time_in')
        ->latest('id')
        ->first();

    expect($manualAttendance)->not->toBeNull();
    expect($manualAttendance?->source)->toBe('manual_adjustment');
    expect($manualAttendance?->recorded_at?->format('H:i'))->toBe('08:15');
});

test('attendance page hides super admin attendance data', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email_verified_at' => now(),
        'name' => 'Hidden Super Admin',
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Visible Member',
        'employee_code' => 'ATT-VISIBLE',
    ]);

    Attendance::query()->create([
        'user_id' => $superAdmin->id,
        'recorded_at' => now(),
        'entry_type' => 'time_in',
        'scanned_code' => $superAdmin->qr_value,
        'source' => 'qr_scan',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now(),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->actingAs($admin)
        ->get('/attendances?date='.now()->toDateString())
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($member, $admin): void {
            $page
                ->where('summary.recordCount', 1)
                ->where('summary.uniqueUsers', 1)
                ->where('summary.teamSize', 2)
                ->has('attendances', 1)
                ->where('attendances.0.user_name', $member->name)
                ->where('attendances.0.employee_code', $member->employee_code)
                ->where('canEditAttendanceTime', false);
        });
});

test('attendance page team size counts active users only', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_ACTIVE,
    ]);

    User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'status' => User::STATUS_INACTIVE,
    ]);

    $this->actingAs($admin)
        ->get('/attendances?date='.now()->toDateString())
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('summary.teamSize', 2));
});

test('attendance page marks late time in records based on 8am office start', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Late Member',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->setTime(8, 15),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $this->actingAs($admin)
        ->get('/attendances?date='.now()->toDateString())
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page): void {
            $page
                ->where('officeHours', '8:00 AM - 5:00 PM')
                ->where('attendances.0.status_label', 'Late')
                ->where('attendances.0.attendance_status', 'late')
                ->where('attendances.0.late_minutes', 15);
        });
});

test('backup export includes users and attendance data for selected month', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Archive Member',
        'sub_name' => 'Archive Alias',
        'employee_code' => 'ATT-BACKUP',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->startOfMonth()->addDay()->setTime(8, 15),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->startOfMonth()->addDay()->setTime(17, 30),
        'entry_type' => 'time_out',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $response = $this->actingAs($admin)->get('/backups/export?year='.now()->year.'&month='.now()->month.'&type=json');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json; charset=UTF-8');

    $json = json_decode($response->streamedContent(), true);

    expect($json['period']['year'])->toBe(now()->year);
    expect($json['period']['month'])->toBe(now()->month);
    expect(collect($json['users'])->contains(fn (array $user): bool => $user['email'] === $member->email))->toBeTrue();

    $memberBackup = collect($json['users'])->firstWhere('email', $member->email);

    expect($memberBackup['attendance_day_count'])->toBe(1);
    expect($memberBackup['sub_name'])->toBe('Archive Alias');
    expect($memberBackup['total_work_minutes'])->toBe(555);
    expect($memberBackup['total_work_hours'])->toBe('9h 15m');
    expect($memberBackup['attendance_days'][0]['time_in'])->toBe('08:15 AM');
    expect($memberBackup['attendance_days'][0]['time_out'])->toBe('05:30 PM');
    expect($memberBackup['attendance_days'][0]['total_work_minutes'])->toBe(555);
    expect($memberBackup['attendance_days'][0]['total_work_hours'])->toBe('9h 15m');
});

test('backup export can be downloaded as excel', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
        'name' => 'Backup Admin',
    ]);

    $elmar = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Elmar B. Noche',
        'sub_name' => 'Alexander Bennett',
        'email' => 'elmar-sheet@example.com',
    ]);

    Attendance::query()->create([
        'user_id' => $elmar->id,
        'recorded_at' => now()->startOfMonth()->addDay()->setTime(8, 0),
        'entry_type' => 'time_in',
        'scanned_code' => $elmar->qr_value,
        'source' => 'qr_scan',
    ]);

    Attendance::query()->create([
        'user_id' => $elmar->id,
        'recorded_at' => now()->startOfMonth()->addDay()->setTime(17, 0),
        'entry_type' => 'time_out',
        'scanned_code' => $elmar->qr_value,
        'source' => 'qr_scan',
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Second Member',
        'email' => 'second-member@example.com',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->startOfMonth()->addDays(2)->setTime(8, 30),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->startOfMonth()->addDays(2)->setTime(17, 30),
        'entry_type' => 'time_out',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $response = $this->actingAs($admin)->get('/backups/export?year='.now()->year.'&month='.now()->month.'&type=excel');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    $excel = $response->streamedContent();

    expect($excel)->toContain("Elmar's Team PH Backup");
    expect($excel)->not->toContain('Worksheet ss:Name="Backup Admin"');
    expect($excel)->not->toContain('backup-admin@example.com');
    expect($excel)->toContain('Worksheet ss:Name="Elmar B. Noche"');
    expect($excel)->toContain('Sub Name');
    expect($excel)->toContain('Alexander Bennett');
    expect($excel)->toContain('Worksheet ss:Name="Second Member"');
    expect($excel)->toContain('Daily Total Hours');
    expect($excel)->toContain('Member Total Hours');
    expect($excel)->toContain('9h 00m');
    expect($excel)->toContain('Approved and verified by:');
    expect(strpos($excel, 'Worksheet ss:Name="Elmar B. Noche"'))->toBeLessThan(
        strpos($excel, 'Worksheet ss:Name="Second Member"'),
    );
});

test('backup export can be downloaded as pdf with signatory', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
        'email' => 'admin-backup@example.com',
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Monthly Member',
        'sub_name' => 'Monthly Alias',
        'email' => 'monthly-member@example.com',
        'employee_code' => 'ATT-MONTH',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->startOfMonth()->addDays(2)->setTime(8, 0),
        'entry_type' => 'time_in',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    Attendance::query()->create([
        'user_id' => $member->id,
        'recorded_at' => now()->startOfMonth()->addDays(2)->setTime(17, 0),
        'entry_type' => 'time_out',
        'scanned_code' => $member->qr_value,
        'source' => 'qr_scan',
    ]);

    $response = $this->actingAs($admin)->get('/backups/export?year='.now()->year.'&month='.now()->month.'&type=pdf');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $pdf = $response->streamedContent();

    expect($pdf)->toContain('%PDF-1.4');
    expect($pdf)->toContain('Date');
    expect($pdf)->toContain('Time In');
    expect($pdf)->toContain('monthly-member@example.com');
    expect($pdf)->toContain('Sub Name: Monthly Alias');
    expect($pdf)->toContain('Total Hours');
    expect($pdf)->toContain('9h 00m');
    expect($pdf)->not->toContain('admin-backup@example.com');
    expect($pdf)->toContain('Approved and verified by:');
    expect($pdf)->toContain('Elmar B. Noche');
});
