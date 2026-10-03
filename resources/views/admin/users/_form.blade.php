@php($mode = old('password_mode', $user->exists ? 'keep' : 'generate'))
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <x-card title="Account">
            <x-form.input name="name" label="Full name" :value="$user->name" required maxlength="120" />
            <x-form.input name="email" type="email" label="Email" :value="$user->email" required maxlength="190" autocomplete="off" />
            @if ($self ?? false)
                <div class="alert alert-info small mb-0" role="note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    This is your own account. Your role and status cannot be changed here, and your password is changed on the
                    <a href="{{ route('password.edit') }}">Change password</a> page, which asks for your current password.
                </div>
            @else
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :value="$user->role?->value" required />
                    </div>
                    <div class="col-md-6">
                        <x-form.select name="station_id" label="Station" :options="$stations" :value="$user->station_id" placeholder="None" help="Required for Reporting Officers only." />
                    </div>
                </div>
                <x-form.checkbox name="is_active" label="Account active" :checked="$user->exists ? $user->is_active : true" help="Inactive users cannot sign in. Accounts are deactivated, never deleted, so records keep who created them." />
            @endif
        </x-card>
    </div>
    @unless ($self ?? false)
        <div class="col-12 col-lg-5">
            <x-card :title="$user->exists ? 'Password' : 'Initial password'" subtitle="The user must choose their own password at first sign-in.">
                <fieldset>
                    <legend class="visually-hidden">Password option</legend>
                    @if ($user->exists)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="password_mode" id="mode-keep" value="keep" @checked($mode === 'keep')>
                            <label class="form-check-label" for="mode-keep">Keep the current password</label>
                        </div>
                    @endif
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="password_mode" id="mode-generate" value="generate" @checked($mode === 'generate')>
                        <label class="form-check-label" for="mode-generate">Generate a secure temporary password <span class="text-body-secondary small">(shown to you once)</span></label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="password_mode" id="mode-manual" value="manual" @checked($mode === 'manual')>
                        <label class="form-check-label" for="mode-manual">Type a password</label>
                    </div>
                </fieldset>
                @error('password_mode')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <div id="manual-password-fields" @class(['d-none' => $mode !== 'manual'])>
                    <x-form.input name="password" type="password" label="Password" autocomplete="new-password" help="At least {{ config('security.password_min_length') }} characters, with letters and numbers." />
                    <x-form.input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" />
                </div>
            </x-card>
        </div>
    @endunless
</div>
