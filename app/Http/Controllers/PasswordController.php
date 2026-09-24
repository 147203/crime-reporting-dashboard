<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionTerminator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('auth.change-password', ['forced' => $user->must_change_password]);
    }

    public function update(UpdatePasswordRequest $request, AuditLogger $audit, SessionTerminator $sessions): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $user->forceFill(['password' => $request->string('password')->toString(), 'must_change_password' => false])->save();
        $request->session()->regenerate();
        $sessions->endOtherSessions($user, $request->session()->getId());
        $audit->record('auth.password_changed', $user, [], $user);

        return redirect()->route('home')->with('status', 'Your password was changed.');
    }
}
