<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionTerminator;
use App\Support\ReferenceOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly ReferenceOptions $options, private readonly SessionTerminator $sessions) {}

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('station:id,name')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User, 'roles' => Role::cases(), 'stations' => $this->options->stations(activeOnly: true)]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = new User($request->safe()->only(['name', 'email', 'password']));
            $this->applyAdminFields($user, $request);
            $user->must_change_password = true;
            $user->save();
            $this->audit->record('user.created', $user, ['role' => $user->role->value, 'station_id' => $user->station_id]);

            return $user;
        });

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} was created. They must choose a new password at first login.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'roles' => Role::cases(), 'stations' => $this->options->stations(activeOnly: true)]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user()) && (! $request->boolean('is_active', true) || $request->input('role') !== Role::Admin->value)) {
            throw ValidationException::withMessages(['role' => 'You cannot deactivate your own account or remove your own administrator role.']);
        }

        DB::transaction(function () use ($request, $user): void {
            $user->fill($request->safe()->only(['name', 'email']));
            $this->applyAdminFields($user, $request);

            $reset = $request->filled('password');
            if ($reset) {
                $user->password = $request->string('password')->toString();
                $user->must_change_password = true;
                $user->failed_attempts = 0;
                $user->locked_until = null;
            }

            $changed = array_values(array_diff(array_keys($user->getDirty()), ['password', 'remember_token']));
            $user->save();

            if ($reset || ! $user->is_active) {
                $this->sessions->endOtherSessions($user);
            }
            $this->audit->record($reset ? 'user.password_reset' : 'user.updated', $user, ['fields' => $changed]);
        });

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} was updated.");
    }

    private function applyAdminFields(User $user, UserRequest $request): void
    {
        $user->role = Role::from($request->string('role')->toString());
        $user->station_id = $request->filled('station_id') ? $request->integer('station_id') : null;
        $user->is_active = $request->boolean('is_active', true);
    }
}
