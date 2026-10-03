<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionTerminator;
use App\Support\PasswordGenerator;
use App\Support\ReferenceOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ReferenceOptions $options,
        private readonly SessionTerminator $sessions,
        private readonly PasswordGenerator $passwords,
    ) {}

    public function index(Request $request): Response
    {
        $response = response()->view('admin.users.index', [
            'users' => User::query()->with('station:id,name')->orderBy('name')->paginate(25),
        ]);

        // The one-time password must not come back from the browser cache through the Back button.
        return $request->session()->has('generated_password') ? $response->header('Cache-Control', 'no-store, private') : $response;
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User, 'roles' => Role::cases(), 'stations' => $this->options->stations(activeOnly: true)]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        [$password, $generated] = $this->chosenPassword($request);

        $user = DB::transaction(function () use ($request, $password): User {
            $user = new User([...$request->safe()->only(['name', 'email']), 'password' => $password]);
            $this->applyAdminFields($user, $request);
            $user->must_change_password = true;
            $user->save();
            $this->audit->record('user.created', $user, ['role' => $user->role->value, 'station_id' => $user->station_id]);

            return $user;
        });

        return $this->withPassword(redirect()->route('admin.users.index'), $user, $generated ? $password : null)
            ->with('status', "User {$user->name} was created. They must choose a new password at first login.");
    }

    public function edit(Request $request, User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::cases(),
            'stations' => $this->options->stations(activeOnly: true),
            'self' => $user->is($request->user()),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        [$password, $generated] = $request->string('password_mode')->toString() === UserRequest::KEEP ? [null, false] : $this->chosenPassword($request);

        DB::transaction(function () use ($request, $user, $password): void {
            $this->guardLastAdministrator($request, $user);
            $user->fill($request->safe()->only(['name', 'email']));
            if (! $request->editingSelf()) {
                $this->applyAdminFields($user, $request);
            }

            if ($password !== null) {
                $user->password = $password;
                $user->must_change_password = true;
                $user->failed_attempts = 0;
                $user->locked_until = null;
            }

            $changed = array_values(array_diff(array_keys($user->getDirty()), ['password', 'remember_token']));
            $user->save();

            if ($password !== null || ! $user->is_active) {
                $this->sessions->endOtherSessions($user);
            }
            $this->audit->record($password !== null ? 'user.password_reset' : 'user.updated', $user, ['fields' => $changed]);
        });

        return $this->withPassword(redirect()->route('admin.users.index'), $user, $generated ? $password : null)->with('status', "User {$user->name} was updated.");
    }

    public function unlock(User $user): RedirectResponse
    {
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        $this->audit->record('user.unlocked', $user);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} can sign in again.");
    }

    /**
     * @return array{0: string, 1: bool} the password and whether the system generated it
     */
    private function chosenPassword(UserRequest $request): array
    {
        return $request->string('password_mode')->toString() === UserRequest::GENERATE
            ? [$this->passwords->generate(), true]
            : [$request->string('password')->toString(), false];
    }

    /**
     * A generated password is shown once on the next page (flashed in the encrypted session) and never logged.
     */
    private function withPassword(RedirectResponse $response, User $user, ?string $generated): RedirectResponse
    {
        return $generated === null ? $response : $response->with('generated_password', ['name' => $user->name, 'email' => $user->email, 'password' => $generated]);
    }

    /**
     * Someone must always be able to administer the system. The active administrators are read under a row lock, so
     * two administrators demoting each other at the same moment cannot both succeed.
     */
    private function guardLastAdministrator(UserRequest $request, User $user): void
    {
        if ($request->input('role') === Role::Admin->value && $request->boolean('is_active', true)) {
            return;
        }

        $activeAdmins = User::query()->where('role', Role::Admin->value)->where('is_active', true)->lockForUpdate()->pluck('id');
        if ($activeAdmins->contains($user->id) && $activeAdmins->count() === 1) {
            throw ValidationException::withMessages(['role' => 'This is the last active administrator, so it cannot be deactivated or given another role.']);
        }
    }

    private function applyAdminFields(User $user, UserRequest $request): void
    {
        $user->role = Role::from($request->string('role')->toString());
        $user->station_id = $request->filled('station_id') ? $request->integer('station_id') : null;
        $user->is_active = $request->boolean('is_active', true);
    }
}
