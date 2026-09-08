<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password · DM System</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full">
<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></h1>
        <p class="mb-6 text-center text-sm text-gray-500">Forgot your password?</p>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="card space-y-4">
            @csrf
            <p class="text-sm text-gray-500">Enter your email and we'll send you a link to choose a new password.</p>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" required autofocus
                       value="{{ old('email') }}" class="input">
            </div>
            @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="btn-primary w-full">Email reset link</button>
            <a href="{{ route('login') }}" class="block text-center text-sm text-indigo-600">Back to sign in</a>
        </form>
    </div>
</div>
</body>
</html>
