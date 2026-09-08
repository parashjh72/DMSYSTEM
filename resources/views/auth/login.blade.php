<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · DM System</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full">
<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></h1>
        <p class="mb-6 text-center text-sm text-gray-500">Sales &amp; Activation Data Management</p>

        <form method="POST" action="{{ route('login') }}" class="card space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" required autofocus
                       value="{{ old('email') }}" class="input">
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" required class="input">
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300"> Remember me
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600">Forgot password?</a>
            </div>
            @error('email')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @if (session('status'))
                <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
            @endif
            <button class="btn-primary w-full">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
