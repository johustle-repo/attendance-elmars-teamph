<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Services\FirebaseRealtimeDatabase;

class AttendanceObserver
{
    public function __construct(
        private readonly FirebaseRealtimeDatabase $firebase,
    ) {
    }

    public function saved(Attendance $attendance): void
    {
        $this->firebase->syncAttendance($attendance);
    }

    public function deleted(Attendance $attendance): void
    {
        $this->firebase->deleteAttendance($attendance);
    }
}
