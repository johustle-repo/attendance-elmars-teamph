<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagedUserController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
    'scanUrl' => '/scan',
])->name('home');

Route::get('/scan', [AttendanceScanController::class, 'create'])->name('scan.create');
Route::post('/scan', [AttendanceScanController::class, 'store'])->name('scan.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('users', [ManagedUserController::class, 'index'])->name('users.index');
        Route::post('users', [ManagedUserController::class, 'store'])->name('users.store');

        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('attendances/export', [AttendanceController::class, 'export'])->name('attendances.export');
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::get('backups/export', [BackupController::class, 'export'])->name('backups.export');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::patch('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    });
});

require __DIR__.'/settings.php';
