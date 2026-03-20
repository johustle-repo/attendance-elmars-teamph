<?php

namespace App\Observers;

use App\Models\User;
use App\Services\FirebaseRealtimeDatabase;

class UserObserver
{
    public function __construct(
        private readonly FirebaseRealtimeDatabase $firebase,
    ) {
    }

    public function saved(User $user): void
    {
        $this->firebase->syncUser($user);
    }

    public function deleted(User $user): void
    {
        $this->firebase->deleteUser($user);
    }
}
