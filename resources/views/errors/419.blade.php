<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 - Page Expired | {{ config('app.name', 'DentalCare') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full font-sans antialiased text-slate-100 bg-slate-950 flex flex-col items-center justify-center p-6 relative overflow-hidden">
    <div class="max-w-md w-full bg-slate-900/90 border border-slate-800 backdrop-blur-2xl rounded-3xl p-8 sm:p-10 shadow-2xl relative z-10 text-center space-y-6">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-400 font-extrabold text-2xl">
            419
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">Session Expired</h1>
            <p class="text-sm text-slate-400 leading-relaxed">Your session or security token has expired. Please refresh the page and try again.</p>
        </div>

        <div class="pt-2">
            <button onclick="window.location.reload()" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-sm shadow-lg transition-all">
                Refresh Page
            </button>
        </div>
    </div>
</body>
</html>
