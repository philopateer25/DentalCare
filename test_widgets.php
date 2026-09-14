<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // try to load the widgets directly
    $widgets = [
        \App\Filament\Widgets\AdminExecutiveSummaryWidget::class,
        \App\Filament\Widgets\SecretaryStatsWidget::class,
        \App\Filament\Widgets\DoctorEarningsWidget::class,
        \App\Filament\Widgets\AdminLabDebtsWidget::class,
        \App\Filament\Widgets\AdminRevenueExpenseChartWidget::class,
        \App\Filament\Widgets\AdminTopTreatmentsWidget::class,
        \App\Filament\Widgets\DoctorCommissionsOverviewWidget::class,
        \App\Filament\Widgets\DoctorPendingLabsWidget::class,
        \App\Filament\Widgets\DoctorScheduleWidget::class,
        \App\Filament\Widgets\FinanceOverviewWidget::class,
        \App\Filament\Widgets\InventoryOverviewWidget::class,
        \App\Filament\Widgets\LiveReceptionQueueWidget::class,
        \App\Filament\Widgets\PendingTasksWidget::class,
        \App\Filament\Widgets\ActionRequiredWidget::class,
    ];

    foreach ($widgets as $widget) {
        $w = app($widget);
        echo "Successfully instantiated {$widget}\n";
    }

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString();
} catch (\Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
