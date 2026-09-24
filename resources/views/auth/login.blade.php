@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-8 col-md-6 col-lg-4">
        <h1 class="h4 mb-3">Log in</h1>
        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="form-control @error('email') is-invalid @enderror" aria-describedby="email-error">
                @error('email')
                    <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror" aria-describedby="password-error">
                @error('password')
                    <div id="password-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Log in</button>
        </form>
        <p class="text-body-secondary small mt-3">Accounts are created by an administrator.</p>
    </div>
</div>
@endsection
