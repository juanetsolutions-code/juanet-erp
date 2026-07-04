<nav x-data="{ mobileMenuOpen: false }" class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/80 transition-colors duration-300">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            
            <!-- Left: Brand Logo -->
            <div class="flex items-center">
                <a href="/" class="flex items-center gap-x-2">
                    <span class="h-9 w-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-extrabold text-base shadow-md">J</span>
                    <span class="text-base font-black tracking-wider text-slate-900 dark:text-white uppercase font-display">JUANET</span>
                </a>
                
                <!-- Desktop Navigation Links -->
                <div class="hidden md:ml-6 lg:ml-10 md:flex md:space-x-1 lg:space-x-2">
                    <a href="/" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Home</a>
                    <a href="/services" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Services</a>
                    <a href="/marketplace" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Marketplace</a>
                    <a href="/portfolio" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Portfolio</a>
                    <a href="/blog" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Blog</a>
                    <a href="/#pricing" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Pricing</a>
                    <a href="/#built-for-africa" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">About</a>
                    <a href="/contact" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Contact</a>
                    @auth
                        <a href="/dashboard" class="px-2.5 py-2 text-[11px] lg:text-xs font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">Dashboard</a>
                    @endauth
                </div>
            </div>

            <!-- Right: Actions & Theme toggle -->
            <div class="hidden md:flex items-center gap-x-4">
                
                <!-- Dark Mode Toggle Button -->
                <button type="button" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition" @click="toggleDarkMode()">
                    <!-- Sun (for dark mode active) -->
                    <svg x-show="darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9.75-9h-2.25m-13.5 0H3m14.03-7.03l-1.59 1.59M8.28 17.03l-1.59 1.59m10.12 0l1.59-1.59M8.28 6.97l1.59-1.59M12 18.75a6.75 6.75 0 100-13.5 6.75 6.75 0 000 13.5z" />
                    </svg>
                    <!-- Moon (for light mode active) -->
                    <svg x-show="!darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>

                @auth
                    <a href="/dashboard" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-500 transition">
                        Console Dashboard
                    </a>
                @else
                    <a href="/login" class="text-xs font-bold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 transition">
                        Sign In
                    </a>
                    <a href="/register" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-500 transition">
                        Get Started
                    </a>
                @endauth
            </div>

            <!-- Mobile Menu Toggle Button -->
            <div class="flex items-center md:hidden gap-x-2">
                <!-- Theme toggle for mobile -->
                <button type="button" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition" @click="toggleDarkMode()">
                    <svg x-show="darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9.75-9h-2.25m-13.5 0H3m14.03-7.03l-1.59 1.59M8.28 17.03l-1.59 1.59m10.12 0l1.59-1.59M8.28 6.97l1.59-1.59M12 18.75a6.75 6.75 0 100-13.5 6.75 6.75 0 000 13.5z" />
                    </svg>
                    <svg x-show="!darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>

                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" class="p-2 text-slate-500 dark:text-slate-400 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition focus:outline-none">
                    <svg x-show="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Animated Mobile Menu panel -->
    <div x-show="mobileMenuOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-4" class="md:hidden border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950 px-4 py-4 space-y-1.5 transition-all" x-cloak>
        <a href="/" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Home</a>
        <a href="/services" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Services</a>
        <a href="/marketplace" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Marketplace</a>
        <a href="/portfolio" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Portfolio</a>
        <a href="/blog" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Blog</a>
        <a href="/#pricing" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Pricing</a>
        <a href="/#built-for-africa" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">About</a>
        <a href="/contact" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Contact</a>
        @auth
            <a href="/dashboard" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900">Dashboard</a>
        @endauth
        
        <div class="border-t border-slate-100 dark:border-slate-800 my-2 pt-2"></div>
        
        @auth
            <a href="/dashboard" class="block w-full text-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm">
                Dashboard Console
            </a>
        @else
            <a href="/login" class="block text-center px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50">
                Sign In
            </a>
            <a href="/register" class="block text-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm">
                Get Started
            </a>
        @endauth
    </div>
</nav>
