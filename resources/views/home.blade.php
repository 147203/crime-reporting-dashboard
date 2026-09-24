@extends('layouts.app')

@section('title', 'Home')

@section('content')
<h1 class="h4">Signed in</h1>
<p>Signed in as {{ auth()->user()?->name }}.</p>
@endsection
