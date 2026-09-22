<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 font-sans antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set new password · DM System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
    </style>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-500 via-indigo-600 to-violet-500 font-extrabold text-white text-xl shadow-lg shadow-indigo-500/25 mb-3 ring-4 ring-white/10">
                DM
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">DM<span class="text-indigo-400">System</span></h1>
            <p class="mt-1 text-xs text-slate-400 font-medium">Reset Your Account Password</p>
        </div>

        <div class="rounded-3xl bg-white/95 p-8 shadow-2xl backdrop-blur-xl border border-white/20 text-slate-900">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900">Choose a new password</h2>
                <p class="text-xs text-slate-500 mt-1">Make sure it has at least 8 characters</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="label text-slate-700" for="email">Work Email</label>
                    <input id="email" name="email" type="email" required
                           value="{{ old('email', $email) }}"
                           class="input bg-slate-50/50 text-slate-900 border-slate-200">
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label text-slate-700" for="password">New Password</label>
                    <input id="password" name="password" type="password" required
                           class="input bg-slate-50/50 text-slate-900 border-slate-200" autocomplete="new-password">
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label text-slate-700" for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="input bg-slate-50/50 text-slate-900 border-slate-200" autocomplete="new-password">
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-primary w-full py-2.5 text-sm font-semibold tracking-wide shadow-md shadow-indigo-600/20">
                        Update Password &amp; Sign in
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
