<x-layouts.app title="Change password">
    <x-page-header title="Change password" :subtitle="$forced ? 'Choose your own password before you continue.' : 'Update the password you use to sign in.'" />
    <div class="row">
        <div class="col-12 col-md-8 col-lg-6">
            <x-card>
                <form method="POST" action="{{ route('password.update') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <x-form.input name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                    <x-form.input name="password" type="password" label="New password" required autocomplete="new-password"
                        help="At least {{ config('security.password_min_length') }} characters, with letters and numbers." />
                    <x-form.input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
                    <button type="submit" class="btn btn-primary"><i class="bi bi-key" aria-hidden="true"></i> Save password</button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.app>
