<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ManagedUserController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->canManageUsers(), 403);

        $managedUsers = User::query()
            ->withCount('attendances')
            ->orderByRaw("case when role = 'super_admin' then 0 when role = 'admin' then 1 else 2 end")
            ->orderBy('name')
            ->get();

        return Inertia::render('users/index', [
            'users' => $managedUsers->map(fn (User $managedUser) => [
                'id' => $managedUser->id,
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'role' => $managedUser->role?->value,
                'role_label' => $managedUser->role?->label(),
                'employee_code' => $managedUser->employee_code,
                'position' => $managedUser->position,
                'qr_value' => $managedUser->qr_value,
                'attendance_count' => $managedUser->attendances_count,
                'created_at' => optional($managedUser->created_at)->format('M d, Y'),
            ]),
            'allowedRoles' => collect($this->allowedRolesFor($user))
                ->map(fn (UserRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canManageUsers(), 403);

        $allowedRoles = array_map(
            fn (UserRole $role): string => $role->value,
            $this->allowedRolesFor($user),
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($allowedRoles)],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:users,employee_code'],
            'position' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'employee_code' => $validated['employee_code'] ?: 'ATT-'.Str::upper(Str::random(6)),
            'position' => $validated['position'] ?: 'Team Member',
            'qr_token' => (string) Str::uuid(),
            'email_verified_at' => now(),
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User added successfully.');
    }

    /**
     * @return array<int, UserRole>
     */
    private function allowedRolesFor(User $user): array
    {
        return $user->isSuperAdmin()
            ? [UserRole::SuperAdmin, UserRole::Admin, UserRole::Member]
            : [UserRole::Member];
    }
}
