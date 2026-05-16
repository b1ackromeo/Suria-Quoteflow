@extends('layouts.app')

@section('content')
<div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold tracking-tight">Create the first administrator</h1>
    <p class="mt-2 text-sm text-zinc-600">This screen closes after the first user is created.</p>
    <form method="post" action="{{ route('setup.store') }}" class="mt-6 space-y-4">
        @csrf
        <label class="form-label">Name
            <input class="form-input" name="name" value="{{ old('name') }}" required autofocus>
        </label>
        <label class="form-label">Email
            <input class="form-input" type="email" name="email" value="{{ old('email') }}" required>
        </label>
        <label class="form-label">Password
            <input class="form-input" type="password" name="password" required>
        </label>
        <label class="form-label">Confirm password
            <input class="form-input" type="password" name="password_confirmation" required>
        </label>
        <button class="btn btn-primary w-full" type="submit">Create admin account</button>
    </form>
</div>
@endsection
