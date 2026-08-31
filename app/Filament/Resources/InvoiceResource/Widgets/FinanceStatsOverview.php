<?php

namespace App\Filament\Resources\InvoiceResource\Widgets;

use App\Models\ClinicExpense;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalProduction = (float) Invoice::where('status', '!=', 'cancelled')->sum('total_amount');
        $totalCollections = (float) Payment::sum('amount');
        $totalOutstandingAR = (float) Invoice::where('status', '!=', 'cancelled')->sum('balance_due');
        $totalExpenses = (float) ClinicExpense::sum('amount');
        
        $netProfit = $totalCollections - $totalExpenses;
        $collectionRate = $totalProduction > 0 ? round(($totalCollections / $totalProduction) * 100, 1) : 0;

        return [
            Stat::make('Gross Invoiced (Production)', '$' . number_format($totalProduction, 2))
                ->description('Total clinical procedure billing')
                ->icon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Net Cash Collections', '$' . number_format($totalCollections, 2))
                ->description("Collection Efficiency: {$collectionRate}%")
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Outstanding Accounts Receivable (A/R)', '$' . number_format($totalOutstandingAR, 2))
                ->description($totalOutstandingAR > 0 ? 'Pending patient & insurance balances' : 'Zero outstanding balance')
                ->icon('heroicon-o-clock')
                ->color($totalOutstandingAR > 0 ? 'warning' : 'success'),

            Stat::make('Net Operating Cash Profit', '$' . number_format($netProfit, 2))
                ->description('Collections minus operating expenses')
                ->icon('heroicon-o-chart-bar')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
        ];
    }
}
