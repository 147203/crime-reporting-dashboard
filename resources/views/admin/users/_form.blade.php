<div class="row g-3">
    <div class="col-12 col-lg-7">
        <x-card title="Account">
            <x-form.input name="name" label="Full name" :value="$user->name" required maxlength="120" />
            <x-form.input name="email" type="email" label="Email" :value="$user->email" required maxlength="190" autocomplete="off" />
            <div class="row">
                <div class="col-md-6">
                    <x-form.select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :value="$user->role?->value" required />
                </div>
                <div class="col-md-6">
                    <x-form.select name="station_id" label="Station" :options="$stations" :value="$user->station_id" placeholder="None" help="Required for Reporting Officers only." />
                </div>
            </div>
            <x-form.checkbox name="is_active" label="Account active" :checked="$user->exists ? $user->is_active : true" help="Inactive users cannot sign in." />
        </x-card>
    </div>
    <div class="col-12 col-lg-5">
        <x-card :title="$user->exists ? 'Reset password' : 'Initial password'" :subtitle="$user->exists ? 'Leave blank to keep the current password.' : 'The user must change it at first login.'">
            <x-form.input name="password" type="password" label="Password" :required="! $user->exists" autocomplete="new-password" help="At least {{ config('security.password_min_length') }} characters, with letters and numbers." />
            <x-form.input name="password_confirmation" type="password" label="Confirm password" :required="! $user->exists" autocomplete="new-password" />
        </x-card>
    </div>
</div>
