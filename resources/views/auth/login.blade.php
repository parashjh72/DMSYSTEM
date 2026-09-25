<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 font-sans antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · DM System</title>
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
    </style>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden">
    {{-- Background glowing orb accents --}}
    <div class="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        {{-- Brand Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-500 via-indigo-600 to-violet-500 font-extrabold text-white text-xl shadow-lg shadow-indigo-500/25 mb-3 ring-4 ring-white/10">
                DM
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">DM<span class="text-indigo-400">System</span></h1>
            <p class="mt-1 text-xs text-slate-400 font-medium">Distribution Management &amp; Activation Platform</p>
            <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/5 border border-white/10 px-3 py-1 text-[11px] font-medium text-slate-300">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>dms.parashojha.com</span>
            </div>
        </div>

        {{-- Login Card --}}
        <div class="rounded-3xl bg-white/95 p-8 shadow-2xl backdrop-blur-xl border border-white/20 text-slate-900">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900">Sign in to your account</h2>
                <p class="text-xs text-slate-500 mt-0.5">Enter your enterprise credentials to continue</p>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-xs font-medium text-emerald-800 border border-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="label text-slate-700" for="email">Work Email</label>
                    <div class="relative">
                        <input id="email" name="email" type="email" required autofocus
                               value="{{ old('email') }}"
                               placeholder="name@company.com"
                               class="input pl-10 bg-slate-50/50 focus:bg-white text-slate-900 border-slate-200">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        </div>
                    </div>
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="label text-slate-700 !mb-0" for="password">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                            Forgot password?
                        </a>
                    </div>
                    <div class="relative">
                        <input id="password" name="password" type="password" required
                               placeholder="••••••••"
                               class="input pl-10 bg-slate-50/50 focus:bg-white text-slate-900 border-slate-200">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="flex items-center pt-1">
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Remember my session</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-primary w-full py-2.5 text-sm font-semibold tracking-wide shadow-md shadow-indigo-600/20">
                        Sign in
                    </button>
                </div>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} DM System. All rights reserved.
        </p>
    </div>
</body>
</html>
