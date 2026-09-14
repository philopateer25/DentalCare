<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ClinicExpense;
use App\Models\LabOrder;

echo "Clearing all financial data...\n";

// Disable foreign key checks for SQLite
\Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');

Payment::truncate();
InvoiceItem::truncate();
Invoice::truncate();
ClinicExpense::truncate();
LabOrder::truncate();

\Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');

echo "All financial data has been reset to 0!\n";
