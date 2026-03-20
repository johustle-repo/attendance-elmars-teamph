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

test('admin can open the backup page', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/backups')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('backups/index'));
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

test('backup export includes users and attendance data for selected month', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $member = User::factory()->create([
        'role' => UserRole::Member,
        'email_verified_at' => now(),
        'name' => 'Archive Member',
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
    expect($memberBackup['attendance_days'][0]['time_in'])->toBe('08:15 AM');
    expect($memberBackup['attendance_days'][0]['time_out'])->toBe('05:30 PM');
});

test('backup export can be downloaded as excel', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/backups/export?year='.now()->year.'&month='.now()->month.'&type=excel');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    expect($response->streamedContent())->toContain("Elmar's Team PH Backup");
    expect($response->streamedContent())->toContain('Approved and verified by:');
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
    expect($pdf)->not->toContain('admin-backup@example.com');
    expect($pdf)->toContain('Approved and verified by:');
    expect($pdf)->toContain('Elmar B. Noche');
});
