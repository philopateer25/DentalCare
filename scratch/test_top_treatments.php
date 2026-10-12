<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$practiceId = 1;
$query = App\Models\InvoiceItem::query()
    ->select(
        'description',
        Illuminate\Support\Facades\DB::raw('MAX(id) as id'),
        Illuminate\Support\Facades\DB::raw('COUNT(id) as times_performed'),
        Illuminate\Support\Facades\DB::raw('SUM(total_price) as generated_revenue')
    )
    ->whereHas('invoice', fn($q) => $q->where('practice_id', $practiceId))
    ->groupBy('description')
    ->orderBy('generated_revenue', 'desc')
    ->limit(5);

$results = $query->get();
foreach ($results as $r) {
    echo "Procedure: '{$r->description}' | Times: {$r->times_performed} | Revenue: {$r->generated_revenue}\n";
}
