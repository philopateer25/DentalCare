use App\Models\Practice;
use App\Models\ProcedureCode;

$p = Practice::create(['name' => 'Visionary Eyes', 'type' => 'ophthalmology', 'currency' => 'USD', 'timezone' => 'UTC', 'is_active' => true]);
echo "Inventory: " . $p->inventoryItems()->count() . "\n";
echo "Labs: " . $p->dentalLabs()->count() . "\n";
echo "Procedures: " . ProcedureCode::count() . "\n";
