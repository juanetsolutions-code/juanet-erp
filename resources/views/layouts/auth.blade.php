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
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'JUANET Enterprise Platform' }}</title>

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
                    },
                    boxShadow: {
                        'premium': '0 4px 30px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.02)',
                        'premium-dark': '0 4px 30px rgba(0, 0, 0, 0.4), 0 1px 3px rgba(0, 0, 0, 0.3)',
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

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

    <!-- Top floating navigation context -->
    <header class="w-full px-6 py-4 flex justify-between items-center z-10">
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

    <!-- Main visual container -->
    <div class="flex-grow flex items-center justify-center px-4 py-12 relative overflow-hidden">
        <!-- Ambient mesh light background -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-indigo-500/10 dark:bg-indigo-500/5 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-10 left-1/3 w-[300px] h-[300px] bg-emerald-500/5 dark:bg-emerald-500/5 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="w-full max-w-md z-10 transition-all duration-300">
            @yield('content')
        </div>
    </div>

    <!-- Small footer -->
    <footer class="w-full text-center py-6 text-[10px] text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-900/50">
        &copy; 2026 JUANET Enterprise. Protected by security systems.
    </footer>

</body>
</html>
