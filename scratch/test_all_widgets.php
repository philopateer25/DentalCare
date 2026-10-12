<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = [
    'admin' => App\Models\User::where('email', 'admin@clinic.com')->first(),
    'dr' => App\Models\User::where('email', 'dr@clinic.com')->first(),
    'dev' => App\Models\User::where('email', 'dev@clinic.com')->first(),
];

$widgets = [
    'AdminExecutiveSummaryWidget' => App\Filament\Widgets\AdminExecutiveSummaryWidget::class,
    'AdminRevenueExpenseChartWidget' => App\Filament\Widgets\AdminRevenueExpenseChartWidget::class,
    'AdminTopTreatmentsWidget' => App\Filament\Widgets\AdminTopTreatmentsWidget::class,
    'AdminLabDebtsWidget' => App\Filament\Widgets\AdminLabDebtsWidget::class,
    'DoctorScheduleWidget' => App\Filament\Widgets\DoctorScheduleWidget::class,
    'DoctorEarningsWidget' => App\Filament\Widgets\DoctorEarningsWidget::class,
    'DoctorPendingLabsWidget' => App\Filament\Widgets\DoctorPendingLabsWidget::class,
    'DoctorCommissionsOverviewWidget' => App\Filament\Widgets\DoctorCommissionsOverviewWidget::class,
    'SecretaryStatsWidget' => App\Filament\Widgets\SecretaryStatsWidget::class,
    'LiveReceptionQueueWidget' => App\Filament\Widgets\LiveReceptionQueueWidget::class,
    'ActionRequiredWidget' => App\Filament\Widgets\ActionRequiredWidget::class,
    'InventoryOverviewWidget' => App\Filament\Widgets\InventoryOverviewWidget::class,
    'PendingTasksWidget' => App\Filament\Widgets\PendingTasksWidget::class,
    'TestPushNotificationWidget' => App\Filament\Widgets\TestPushNotificationWidget::class,
];

foreach ($users as $roleName => $user) {
    echo "==============================================\n";
    echo "TESTING WIDGETS AS USER: {$user->email} ({$roleName})\n";
    echo "==============================================\n";
    auth()->login($user);
    $practice = $user->practice ?? App\Models\Practice::first();
    \Filament\Facades\Filament::setTenant($practice);

    foreach ($widgets as $name => $widgetClass) {
        $canView = false;
        try {
            $canView = $widgetClass::canView();
        } catch (\Throwable $e) {
            echo "❌ {$name}::canView() threw error: " . $e->getMessage() . "\n";
            continue;
        }

        echo "  - {$name} canView: " . ($canView ? 'YES' : 'NO') . "\n";

        if ($canView) {
            try {
                $instance = new $widgetClass();
                // If it's a StatsOverviewWidget
                if (method_exists($instance, 'getStats')) {
                    $reflection = new \ReflectionMethod($instance, 'getStats');
                    $reflection->setAccessible(true);
                    $stats = $reflection->invoke($instance);
                    echo "    ✔ getStats() executed successfully (" . count($stats) . " stats)\n";
                }
                // If it's a ChartWidget
                if (method_exists($instance, 'getData')) {
                    $reflection = new \ReflectionMethod($instance, 'getData');
                    $reflection->setAccessible(true);
                    $data = $reflection->invoke($instance);
                    echo "    ✔ getData() executed successfully (" . count($data['datasets'] ?? []) . " datasets)\n";
                }
                // If it's a TableWidget
                if (is_subclass_of($widgetClass, \Filament\Widgets\TableWidget::class)) {
                    $table = $instance->table(new \Filament\Tables\Table($instance));
                    $query = $table->getQuery();
                    $count = $query->count();
                    echo "    ✔ table query executed successfully ({$count} records)\n";
                }
            } catch (\Throwable $e) {
                echo "    ❌ ERROR running {$name}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
            }
        }
    }
}
