<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full {{ request('dark') == '1' ? 'dark' : '' }} bg-slate-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="{{ request('dark') == '1' ? 'dark' : 'light' }}">
        <title inertia>{{ config('app.name', 'DentalCare') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased text-slate-900 bg-slate-50 selection:bg-teal-500 selection:text-white dark:bg-gray-950 dark:text-gray-100 transition-colors duration-200">
        @inertia
    </body>
</html>
