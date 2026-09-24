<x-layouts.guest title="Confirm password">
    <h1 class="h5 mb-1">Confirm your password</h1>
    <p class="text-body-secondary small mb-4">This action needs your password again.</p>
    <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
        <button type="submit" class="btn btn-primary w-100">Confirm</button>
    </form>
</x-layouts.guest>
