<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ 
    darkMode: localStorage.getItem('darkMode') === 'true',
    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode);
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'An Error Occurred') — {{ config('app.name', 'JUANET') }}</title>

    <!-- Tailwind CSS CDN & Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                        display: ['Outfit', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 transition-colors duration-300 dark:bg-slate-950 dark:text-slate-50 flex flex-col justify-between">

    <!-- Simple Top Logo -->
    <header class="w-full px-6 py-6 flex justify-between items-center z-10">
        <a href="/" class="flex items-center gap-x-2">
            <span class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-extrabold text-sm shadow-sm">J</span>
            <span class="text-sm font-black tracking-tight text-slate-900 dark:text-white uppercase">JUANET</span>
        </a>
        <button type="button" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition" @click="toggleDarkMode()">
            <svg x-show="darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9.75-9h-2.25m-13.5 0H3m14.03-7.03l-1.59 1.59M8.28 17.03l-1.59 1.59m10.12 0l1.59-1.59M8.28 6.97l1.59-1.59M12 18.75a6.75 6.75 0 100-13.5 6.75 6.75 0 000 13.5z" />
            </svg>
            <svg x-show="!darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
        </button>
    </header>

    <!-- Error Centered Card -->
    <div class="flex-grow flex items-center justify-center px-4 py-12 relative overflow-hidden">
        <!-- background lighting -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] bg-rose-500/5 dark:bg-rose-500/5 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="max-w-md w-full text-center z-10">
            <span class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 font-display text-2xl font-black mb-6">
                @yield('code', '!')
            </span>
            <h1 class="text-3xl font-bold font-display tracking-tight text-slate-900 dark:text-white sm:text-4xl">@yield('headline', 'An unexpected error occurred')</h1>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">@yield('message', 'We apologize for the inconvenience. Our technical monitoring team has been notified.')</p>
            
            <div class="mt-8 flex justify-center gap-x-4">
                <a href="/" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow hover:bg-indigo-500 transition-all">
                    Return to safety
                </a>
                <button onclick="window.history.back()" class="inline-flex items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-slate-800 border border-transparent dark:border-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-all">
                    Go Back
                </button>
            </div>
        </div>
    </div>

    <!-- Small Footer -->
    <footer class="w-full text-center py-6 text-[10px] text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-900/50">
        JUANET Platform Security &middot; Report ID: <span class="font-mono">ERR-{{ strtoupper(substr(md5(now()), 0, 8)) }}</span>
    </footer>

</body>
</html>
