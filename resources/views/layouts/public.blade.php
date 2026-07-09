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

    <title>@yield('title', 'JUANET — The Enterprise Platform for Growing Businesses')</title>
    <meta name="description" content="@yield('meta_description', 'JUANET combines CRM, Marketplace, Projects, Finance, CMS, Support, AI, and Automation into one unified cloud platform built for modern enterprise agencies.')">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'JUANET — The Enterprise Platform for Growing Businesses')">
    <meta property="og:description" content="@yield('meta_description', 'JUANET combines CRM, Marketplace, Projects, Finance, CMS, Support, AI, and Automation into one unified cloud platform built for modern enterprise agencies.')">
    <meta property="og:image" content="https://juanet.cloud/og-image.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="@yield('title', 'JUANET — The Enterprise Platform for Growing Businesses')">
    <meta property="twitter:description" content="@yield('meta_description', 'JUANET combines CRM, Marketplace, Projects, Finance, CMS, Support, AI, and Automation into one unified cloud platform built for modern enterprise agencies.')">
    <meta property="twitter:image" content="https://juanet.cloud/og-image.png">

    <!-- Canonical Tag -->
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    [
      {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "JUANET SaaS Platform",
        "operatingSystem": "All",
        "applicationCategory": "BusinessApplication",
        "description": "Unified Enterprise Cloud Platform for CRM, Multi-Tenant Workspaces, and M-PESA Daraja integration.",
        "offers": {
          "@type": "Offer",
          "price": "0",
          "priceCurrency": "KES"
        }
      },
      {
        "@context": "https://schema.org",
        "@type": "ProfessionalService",
        "name": "JUANET Solutions & Digital Agency",
        "image": "https://juanet.cloud/og-image.png",
        "@id": "https://juanet.cloud/#agency",
        "url": "https://juanet.cloud",
        "telephone": "+254700000000",
        "priceRange": "$$",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "Kilimani Road",
          "addressLocality": "Nairobi",
          "addressCountry": "KE"
        },
        "description": "Premium technology agency and software developer in Kenya, specializing in custom website development, enterprise web portals, SaaS platforms, and brand systems."
      }
    ]
    </script>

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
        .font-display {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50 flex flex-col transition-colors duration-300">

    <!-- Sticky Navigation Bar -->
    <x-navigation />

    <!-- Hero / Header Section -->
    @yield('hero')

    <!-- Main Page Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Enterprise Footer -->
    <x-footer />

    <!-- Command Palette (Ctrl + K) -->
    <x-command-palette />

</body>
</html>
