<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a new password · DM System</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full">
<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></h1>
        <p class="mb-6 text-center text-sm text-gray-500">Choose a new password</p>

        <form method="POST" action="{{ route('password.update') }}" class="card space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" required
                       value="{{ old('email', $email) }}" class="input">
            </div>
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" name="password" type="password" required class="input" autocomplete="new-password">
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="input" autocomplete="new-password">
            </div>
            @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="btn-primary w-full">Update password</button>
        </form>
    </div>
</div>
</body>
</html>
