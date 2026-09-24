@extends('layouts.app')

@section('title', 'Confirm password')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-8 col-md-6 col-lg-4">
        <h1 class="h4 mb-3">Confirm your password</h1>
        <p>This action needs your password again.</p>
        <form method="POST" action="{{ route('password.confirm.store') }}">
            @csrf
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror" aria-describedby="password-error">
                @error('password')
                    <div id="password-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Confirm</button>
        </form>
    </div>
</div>
@endsection
