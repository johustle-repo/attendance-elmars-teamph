<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admin dashboard hides super admin records from stats and recent attendance', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'email_verified_at' => now(),
        'name' => 'Visible Admin',
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

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($admin, $member): void {
            $page
                ->where('stats.totalUsers', 2)
                ->where('stats.totalAdmins', 1)
                ->where('stats.attendanceToday', 1)
                ->where('stats.presentToday', 1)
                ->has('recentAttendances', 1)
                ->where('recentAttendances.0.user_name', $member->name)
                ->missing('recentAttendances.1')
                ->where('myQrValue', $admin->qr_value);
        });
});
