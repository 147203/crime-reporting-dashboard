<x-layouts.guest title="Log in">
    <h1 class="h5 mb-1">Sign in</h1>
    <p class="text-body-secondary small mb-4">Use the account your administrator created for you.</p>
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <x-form.input name="email" type="email" label="Email" required autofocus autocomplete="username" />
        <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
        <button type="submit" class="btn btn-primary w-100 mt-1"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Log in</button>
    </form>
    <p class="text-body-secondary small mt-3 mb-0">Accounts are created by an administrator. There is no self-registration.</p>
</x-layouts.guest>
