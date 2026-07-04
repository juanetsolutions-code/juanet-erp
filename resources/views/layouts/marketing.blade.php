<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ 
    darkMode: localStorage.getItem('darkMode') === 'true',
    mobileMenuOpen: false,
    commandPaletteOpen: false,
    searchQuery: '',
    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode);
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'JUANET Marketing Suite' }}</title>

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
                    },
                    boxShadow: {
                        'premium': '0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 2px 8px -1px rgba(0, 0, 0, 0.03)',
                        'premium-dark': '0 4px 20px -2px rgba(0, 0, 0, 0.3), 0 2px 8px -1px rgba(0, 0, 0, 0.2)',
                    },
                    borderRadius: {
                        'xl': '0.75rem',
                        '2xl': '1rem',
                        '3xl': '1.5rem',
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Inter', sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50 flex flex-col transition-colors duration-300">

    <!-- Sticky Navigation Bar -->
    <x-navigation />

    <!-- Announcement Banner -->
    <div class="bg-indigo-600 text-white text-center py-2 px-4 text-xs font-semibold tracking-wide dark:bg-indigo-700">
        🚀 Frontend Design System Phase F1 is now live! Explore reusable layouts, premium components, and AA accessibility.
    </div>

    <!-- Hero / Header Section -->
    @yield('hero')

    <!-- Marketing content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Footer -->
    <x-footer />

    <!-- Command Palette (Ctrl + K) -->
    <x-command-palette />

</body>
</html>
