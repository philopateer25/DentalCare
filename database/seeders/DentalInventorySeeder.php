<?php

namespace Database\Seeders;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Practice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DentalInventorySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Practice exists
        $practice = Practice::firstOrCreate(
            ['name' => 'Main Dental Clinic'],
            [
                'currency' => 'USD',
                'timezone' => 'UTC',
                'is_active' => true,
            ]
        );

        // 2. Create Top Realistic Dental Suppliers
        $suppliersData = [
            [
                'name' => 'Dentsply Sirona Supplies',
                'company_name' => 'Dentsply Sirona Inc.',
                'contact_person' => 'Marcus Vance',
                'email' => 'orders@dentsplysirona.com',
                'phone' => '+1 (800) 877-0020',
                'website' => 'https://www.dentsplysirona.com',
                'tax_number' => 'US-82736192',
                'address' => '13320 Ballantyne Corporate Pl, Charlotte, NC 28277',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 2,
            ],
            [
                'name' => '3M Oral Care Solutions',
                'company_name' => '3M Company - Dental Division',
                'contact_person' => 'Sarah Jenkins',
                'email' => 'dentalorders@3m.com',
                'phone' => '+1 (800) 634-2249',
                'website' => 'https://www.3m.com/oralcare',
                'tax_number' => 'US-41041788',
                'address' => '2510 Conway Ave, St Paul, MN 55144',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 3,
            ],
            [
                'name' => 'Hu-Friedy Dental Instruments',
                'company_name' => 'Hu-Friedy Mfg. Co., LLC',
                'contact_person' => 'Alexander Schmidt',
                'email' => 'orders@hu-friedy.com',
                'phone' => '+1 (800) 483-7433',
                'website' => 'https://www.hu-friedy.com',
                'tax_number' => 'US-36125010',
                'address' => '3232 N Rockwell St, Chicago, IL 60618',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 4,
            ],
            [
                'name' => 'Kerr Dental & Endodontics',
                'company_name' => 'Kerr Corporation / Envista',
                'contact_person' => 'David Morales',
                'email' => 'supply@kerrdental.com',
                'phone' => '+1 (800) 537-7836',
                'website' => 'https://www.kerrdental.com',
                'tax_number' => 'US-95123984',
                'address' => '200 S Kraemer Blvd, Brea, CA 92821',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 3,
            ],
            [
                'name' => 'Ivoclar Vivadent Clinical',
                'company_name' => 'Ivoclar Vivadent Inc.',
                'contact_person' => 'Helena Richter',
                'email' => 'orders.us@ivoclar.com',
                'phone' => '+1 (800) 533-6825',
                'website' => 'https://www.ivoclar.com',
                'tax_number' => 'US-16093847',
                'address' => '175 Pineview Dr, Amherst, NY 14228',
                'payment_terms' => 'Net 15',
                'lead_time_days' => 3,
            ],
            [
                'name' => 'GC America & Restoratives',
                'company_name' => 'GC America Inc.',
                'contact_person' => 'Kenji Takahashi',
                'email' => 'support@gcamerica.com',
                'phone' => '+1 (800) 323-7063',
                'website' => 'https://www.gcamerica.com',
                'tax_number' => 'US-36254719',
                'address' => '3737 W 127th St, Alsip, IL 60803',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 2,
            ],
            [
                'name' => 'Straumann Group Implants',
                'company_name' => 'Institut Straumann AG',
                'contact_person' => 'Dr. Thomas Weber',
                'email' => 'surgical.orders@straumann.com',
                'phone' => '+1 (800) 448-8168',
                'website' => 'https://www.straumann.com',
                'tax_number' => 'US-04302918',
                'address' => '60 Minuteman Rd, Andover, MA 01810',
                'payment_terms' => 'Net 60',
                'lead_time_days' => 2,
            ],
            [
                'name' => 'Septodont Pharmaceuticals',
                'company_name' => 'Septodont Inc.',
                'contact_person' => 'Claire Delacroix',
                'email' => 'pharma@septodont.com',
                'phone' => '+1 (800) 872-8305',
                'website' => 'https://www.septodontusa.com',
                'tax_number' => 'US-22248591',
                'address' => '416 S Taylor Ave, Louisville, CO 80027',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 2,
            ],
            [
                'name' => 'Ultradent Products Inc.',
                'company_name' => 'Ultradent Products Inc.',
                'contact_person' => 'Jessica Fisher',
                'email' => 'sales@ultradent.com',
                'phone' => '+1 (800) 552-5512',
                'website' => 'https://www.ultradent.com',
                'tax_number' => 'US-87034918',
                'address' => '505 W 10200 S, South Jordan, UT 84095',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 2,
            ],
            [
                'name' => 'Henry Schein Direct Distribution',
                'company_name' => 'Henry Schein Inc.',
                'contact_person' => 'Robert Miller',
                'email' => 'dentalsales@henryschein.com',
                'phone' => '+1 (800) 372-4346',
                'website' => 'https://www.henryschein.com',
                'tax_number' => 'US-11313659',
                'address' => '135 Duryea Rd, Melville, NY 11747',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 1,
            ],
            [
                'name' => 'Brasseler USA Rotary & Burs',
                'company_name' => 'Brasseler USA Medical, LLC',
                'contact_person' => 'Timothy Clark',
                'email' => 'orders@brasselerusa.com',
                'phone' => '+1 (800) 841-4522',
                'website' => 'https://www.brasselerusa.com',
                'tax_number' => 'US-58134762',
                'address' => 'One Brasseler Blvd, Savannah, GA 31419',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 2,
            ],
            [
                'name' => 'American Orthodontics',
                'company_name' => 'American Orthodontics Corp.',
                'contact_person' => 'Emily Howard',
                'email' => 'orthosupply@americanortho.com',
                'phone' => '+1 (800) 558-7686',
                'website' => 'https://www.americanortho.com',
                'tax_number' => 'US-39082736',
                'address' => '3524 Washington Ave, Sheboygan, WI 53081',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 3,
            ],
            [
                'name' => 'NSK Dental Instruments & Turbines',
                'company_name' => 'NSK America Corp.',
                'contact_person' => 'Hitoshi Sato',
                'email' => 'service@nsk-dental.com',
                'phone' => '+1 (888) 675-1675',
                'website' => 'https://www.nsk-dental.com',
                'tax_number' => 'US-36412984',
                'address' => '700 Cooper Ct, Schaumburg, IL 60173',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 3,
            ],
            [
                'name' => 'Bisco Dental Adhesives',
                'company_name' => 'Bisco Inc.',
                'contact_person' => 'Daniel Perez',
                'email' => 'orders@bisco.com',
                'phone' => '+1 (800) 247-3368',
                'website' => 'https://www.bisco.com',
                'tax_number' => 'US-36314892',
                'address' => '1100 W Irving Park Rd, Schaumburg, IL 60193',
                'payment_terms' => 'Net 15',
                'lead_time_days' => 2,
            ],
            [
                'name' => 'Crosstex Infection Prevention',
                'company_name' => 'Crosstex International Inc.',
                'contact_person' => 'Monica Rivera',
                'email' => 'infectioncontrol@crosstex.com',
                'phone' => '+1 (888) 276-7783',
                'website' => 'https://www.crosstex.com',
                'tax_number' => 'US-11276384',
                'address' => '10 Ranick Rd, Hauppauge, NY 11788',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 2,
            ],
        ];

        $supplierMap = [];
        foreach ($suppliersData as $s) {
            $created = Supplier::firstOrCreate(
                ['name' => $s['name']],
                array_merge($s, ['practice_id' => $practice->id, 'is_active' => true])
            );
            $supplierMap[$s['name']] = $created->id;
        }

        $allSupplierIds = array_values($supplierMap);

        // 3. Catalog Definition & Generators for 5,000+ Items
        // We will define systematic generators for all dental disciplines:
        $catalogTemplates = [
            // Operative & Restorative Burs (Diamond & Carbide)
            [
                'category' => 'Operative & Restorative',
                'sub_category' => 'Rotary Diamond Burs',
                'supplier_key' => 'Brasseler USA Rotary & Burs',
                'brand' => 'Brasseler USA',
                'unit' => 'pack',
                'has_expiration' => false,
                'min_reorder' => 5,
                'reorder_qty' => 20,
                'base_sku_prefix' => 'BUR-DIA',
                'cost_range' => [8.50, 24.00],
                'locations' => ['Bur Block Cabinet A', 'Operatory 1 Bur Drawer', 'Operatory 2 Bur Drawer', 'Main Sterilization Rack'],
                'items' => self::generateBurVariations('Diamond'),
            ],
            [
                'category' => 'Operative & Restorative',
                'sub_category' => 'Rotary Carbide Burs',
                'supplier_key' => 'Brasseler USA Rotary & Burs',
                'brand' => 'Brasseler USA',
                'unit' => 'pack',
                'has_expiration' => false,
                'min_reorder' => 5,
                'reorder_qty' => 25,
                'base_sku_prefix' => 'BUR-CAR',
                'cost_range' => [5.50, 18.00],
                'locations' => ['Bur Block Cabinet B', 'Sterilization Station 1', 'Operatory 3 Bur Drawer'],
                'items' => self::generateBurVariations('Carbide'),
            ],

            // Restorative Composites & Adhesives
            [
                'category' => 'Operative & Restorative',
                'sub_category' => 'Composite Resins & Flowables',
                'supplier_key' => '3M Oral Care Solutions',
                'brand' => '3M Filtek / Kerr Herculite',
                'unit' => 'syringes',
                'has_expiration' => true,
                'min_reorder' => 6,
                'reorder_qty' => 24,
                'base_sku_prefix' => 'COMP',
                'cost_range' => [38.00, 89.00],
                'locations' => ['Composite Dispenser Drawer A', 'Operatory 1 Cabinet C', 'Operatory 2 Cabinet C'],
                'items' => self::generateCompositeVariations(),
            ],
            [
                'category' => 'Operative & Restorative',
                'sub_category' => 'Bonding, Etchants & Liners',
                'supplier_key' => 'Bisco Dental Adhesives',
                'brand' => 'Bisco / 3M / Kerr',
                'unit' => 'bottles',
                'has_expiration' => true,
                'min_reorder' => 4,
                'reorder_qty' => 12,
                'base_sku_prefix' => 'BOND',
                'cost_range' => [45.00, 145.00],
                'locations' => ['Refrigerated Storage 1', 'Bonding Cabinet B', 'Operatory Supply Hub'],
                'items' => self::generateBondingVariations(),
            ],
            [
                'category' => 'Operative & Restorative',
                'sub_category' => 'Matrix Systems & Polishing',
                'supplier_key' => 'Dentsply Sirona Supplies',
                'brand' => 'Palodent / Garrison / Sof-Lex',
                'unit' => 'box',
                'has_expiration' => false,
                'min_reorder' => 5,
                'reorder_qty' => 15,
                'base_sku_prefix' => 'MAT-POL',
                'cost_range' => [22.00, 120.00],
                'locations' => ['Matrix Cabinet C', 'Polishing Station Drawer', 'Operatory 1 Cabinet B'],
                'items' => self::generateMatrixAndPolishingVariations(),
            ],

            // Endodontics (Root Canal Therapy)
            [
                'category' => 'Endodontics',
                'sub_category' => 'Hand & NiTi Rotary Files',
                'supplier_key' => 'Dentsply Sirona Supplies',
                'brand' => 'Dentsply Maillefer / Kerr Endodontics',
                'unit' => 'pack',
                'has_expiration' => false,
                'min_reorder' => 10,
                'reorder_qty' => 40,
                'base_sku_prefix' => 'ENDO-FILE',
                'cost_range' => [12.00, 68.00],
                'locations' => ['Endo Cart Drawer 1', 'Endodontics Cabinet A', 'Sterilization Endo Rack'],
                'items' => self::generateEndoFileVariations(),
            ],
            [
                'category' => 'Endodontics',
                'sub_category' => 'Obturation, Sealers & Rubber Dam',
                'supplier_key' => 'Kerr Dental & Endodontics',
                'brand' => 'AH Plus / TotalFill / Sanctuary',
                'unit' => 'box',
                'has_expiration' => true,
                'min_reorder' => 5,
                'reorder_qty' => 20,
                'base_sku_prefix' => 'ENDO-OBT',
                'cost_range' => [18.00, 135.00],
                'locations' => ['Endo Cart Drawer 2', 'Rubber Dam Supply Shelf', 'Main Dental Supply Room'],
                'items' => self::generateEndoObturationVariations(),
            ],

            // Periodontics & Hygiene
            [
                'category' => 'Periodontics & Hygiene',
                'sub_category' => 'Graceys & Ultrasonic Tips',
                'supplier_key' => 'Hu-Friedy Dental Instruments',
                'brand' => 'Hu-Friedy EverEdge 2.0',
                'unit' => 'pcs',
                'has_expiration' => false,
                'min_reorder' => 4,
                'reorder_qty' => 12,
                'base_sku_prefix' => 'PERIO-INST',
                'cost_range' => [42.00, 175.00],
                'locations' => ['Hygiene Cassette Rack', 'Periodontal Surgical Bay', 'Sterilization Pack Shelf'],
                'items' => self::generatePerioInstrumentVariations(),
            ],
            [
                'category' => 'Periodontics & Hygiene',
                'sub_category' => 'Bone Grafts & Barrier Membranes',
                'supplier_key' => 'Straumann Group Implants',
                'brand' => 'Geistlich Bio-Oss / Bio-Gide / Straumann',
                'unit' => 'vials',
                'has_expiration' => true,
                'min_reorder' => 3,
                'reorder_qty' => 10,
                'base_sku_prefix' => 'BIO-GRAFT',
                'cost_range' => [110.00, 395.00],
                'locations' => ['Surgical Implant Safe', 'Regenerative Storage Cabinet A'],
                'items' => self::generateBoneGraftVariations(),
            ],

            // Oral Surgery & Extractions
            [
                'category' => 'Oral Surgery & Extractions',
                'sub_category' => 'Forceps, Elevators & Luxators',
                'supplier_key' => 'Hu-Friedy Dental Instruments',
                'brand' => 'Hu-Friedy Surgical',
                'unit' => 'pcs',
                'has_expiration' => false,
                'min_reorder' => 2,
                'reorder_qty' => 6,
                'base_sku_prefix' => 'SURG-FORC',
                'cost_range' => [65.00, 240.00],
                'locations' => ['Surgical Cassette Shelf', 'Operatory 4 (Oral Surgery)', 'Main Instrument Safe'],
                'items' => self::generateOralSurgeryVariations(),
            ],
            [
                'category' => 'Oral Surgery & Extractions',
                'sub_category' => 'Sutures, Blades & Hemostats',
                'supplier_key' => 'Henry Schein Direct Distribution',
                'brand' => 'Ethicon / Swann Morton / Gelfoam',
                'unit' => 'box',
                'has_expiration' => true,
                'min_reorder' => 8,
                'reorder_qty' => 30,
                'base_sku_prefix' => 'SURG-SUTR',
                'cost_range' => [28.00, 140.00],
                'locations' => ['Surgical Consumables Cart', 'Operatory 4 Drawer B'],
                'items' => self::generateSurgicalConsumablesVariations(),
            ],

            // Prosthodontics & Impression
            [
                'category' => 'Prosthodontics & Impression',
                'sub_category' => 'Impression Silicones & Cements',
                'supplier_key' => 'Ivoclar Vivadent Clinical',
                'brand' => 'Ivoclar / 3M Impregum / RelyX',
                'unit' => 'pack',
                'has_expiration' => true,
                'min_reorder' => 6,
                'reorder_qty' => 20,
                'base_sku_prefix' => 'PROS-IMP',
                'cost_range' => [35.00, 195.00],
                'locations' => ['Prostho Impression Shelf', 'Crown & Bridge Station', 'Refrigerated Storage 1'],
                'items' => self::generateProsthodonticVariations(),
            ],

            // Orthodontics
            [
                'category' => 'Orthodontics',
                'sub_category' => 'Brackets, Archwires & Pliers',
                'supplier_key' => 'American Orthodontics',
                'brand' => 'American Orthodontics / Ormco',
                'unit' => 'box',
                'has_expiration' => false,
                'min_reorder' => 10,
                'reorder_qty' => 50,
                'base_sku_prefix' => 'ORTHO',
                'cost_range' => [15.00, 160.00],
                'locations' => ['Orthodontic Bay Drawer 1', 'Ortho Wire Rack', 'Ortho Pliers Stand'],
                'items' => self::generateOrthodonticVariations(),
            ],

            // Dental Implantology
            [
                'category' => 'Dental Implantology',
                'sub_category' => 'Implants, Abutments & Drivers',
                'supplier_key' => 'Straumann Group Implants',
                'brand' => 'Straumann BLX / Nobel Biocare',
                'unit' => 'pcs',
                'has_expiration' => true,
                'min_reorder' => 4,
                'reorder_qty' => 15,
                'base_sku_prefix' => 'IMPL',
                'cost_range' => [85.00, 480.00],
                'locations' => ['Implant Vault Cabinet A', 'Surgical Operatory Sterile Safe'],
                'items' => self::generateImplantVariations(),
            ],

            // Local Anesthesia & Pharmaceuticals
            [
                'category' => 'Local Anesthesia & Pharma',
                'sub_category' => 'Anesthetic Cartridges & Needles',
                'supplier_key' => 'Septodont Pharmaceuticals',
                'brand' => 'Septocaine / Xylocaine / Citanest',
                'unit' => 'box',
                'has_expiration' => true,
                'min_reorder' => 12,
                'reorder_qty' => 50,
                'base_sku_prefix' => 'ANESTH',
                'cost_range' => [42.00, 98.00],
                'locations' => ['Pharmacy Locked Cabinet', 'Operatory 1 Pharma Drawer', 'Operatory 2 Pharma Drawer'],
                'items' => self::generateAnesthesiaVariations(),
            ],

            // Sterilization & Infection Control
            [
                'category' => 'Sterilization & Infection Control',
                'sub_category' => 'Autoclave Pouches, Indicators & Disinfectants',
                'supplier_key' => 'Crosstex Infection Prevention',
                'brand' => 'Crosstex / CaviWipes / Hu-Friedy IMS',
                'unit' => 'box',
                'has_expiration' => true,
                'min_reorder' => 15,
                'reorder_qty' => 60,
                'base_sku_prefix' => 'STERIL',
                'cost_range' => [18.00, 115.00],
                'locations' => ['Sterilization Storage Bay A', 'Central Sterilization Supply Room'],
                'items' => self::generateSterilizationVariations(),
            ],

            // Diagnostic & Examination
            [
                'category' => 'Diagnostic & Examination',
                'sub_category' => 'Mirrors, Probes & Diagnostic Devices',
                'supplier_key' => 'Hu-Friedy Dental Instruments',
                'brand' => 'Hu-Friedy HD Black Line',
                'unit' => 'pcs',
                'has_expiration' => false,
                'min_reorder' => 8,
                'reorder_qty' => 25,
                'base_sku_prefix' => 'DIAG',
                'cost_range' => [14.00, 320.00],
                'locations' => ['Diagnostic Cassette Tray', 'Sterilization Station 2', 'Exam Room 1'],
                'items' => self::generateDiagnosticVariations(),
            ],

            // Pediatric Dentistry
            [
                'category' => 'Pediatric Dentistry',
                'sub_category' => 'Pediatric SS Crowns & Fluorides',
                'supplier_key' => '3M Oral Care Solutions',
                'brand' => '3M Unitek / Premier Dental',
                'unit' => 'box',
                'has_expiration' => true,
                'min_reorder' => 5,
                'reorder_qty' => 20,
                'base_sku_prefix' => 'PEDO',
                'cost_range' => [18.00, 110.00],
                'locations' => ['Pediatric Operatory Drawer', 'Fluoride Supply Cabinet'],
                'items' => self::generatePediatricVariations(),
            ],

            // Dental Equipment & Handpieces
            [
                'category' => 'Dental Equipment & Handpieces',
                'sub_category' => 'High-Speed, Low-Speed & Couplers',
                'supplier_key' => 'NSK Dental Instruments & Turbines',
                'brand' => 'NSK Ti-Max / KaVo Master Series',
                'unit' => 'pcs',
                'has_expiration' => false,
                'min_reorder' => 2,
                'reorder_qty' => 5,
                'base_sku_prefix' => 'EQUIP-HP',
                'cost_range' => [250.00, 1850.00],
                'locations' => ['Equipment Maintenance Room', 'Operatory 1 Handpiece Holder', 'Operatory 2 Handpiece Holder'],
                'items' => self::generateEquipmentVariations(),
            ],

            // Lab & CAD/CAM Supplies
            [
                'category' => 'Lab & CAD/CAM Supplies',
                'sub_category' => 'Zirconia, PMMA & Milling Burs',
                'supplier_key' => 'Ivoclar Vivadent Clinical',
                'brand' => 'IPS e.max / Telio CAD / Cercon',
                'unit' => 'pcs',
                'has_expiration' => false,
                'min_reorder' => 4,
                'reorder_qty' => 15,
                'base_sku_prefix' => 'CAD-CAM',
                'cost_range' => [45.00, 260.00],
                'locations' => ['In-House Dental Lab Shelf A', 'Milling Unit Cabinet'],
                'items' => self::generateLabCADVariations(),
            ],

            // Consumables & PPE
            [
                'category' => 'Consumables & PPE',
                'sub_category' => 'Nitrile Gloves, Masks & Saliva Ejectors',
                'supplier_key' => 'Henry Schein Direct Distribution',
                'brand' => 'Halyard / Cranberry / Crosstex',
                'unit' => 'box',
                'has_expiration' => true,
                'min_reorder' => 20,
                'reorder_qty' => 100,
                'base_sku_prefix' => 'PPE-CONS',
                'cost_range' => [8.00, 45.00],
                'locations' => ['Main PPE Warehouse Rack', 'Operatory 1 Prep Station', 'Sterilization Bay'],
                'items' => self::generatePPEAndConsumablesVariations(),
            ],

            // Rare & Specialty Instruments
            [
                'category' => 'Rare & Specialty Instruments',
                'sub_category' => 'Sinus Lift, Tunneling & Microsurgery',
                'supplier_key' => 'Hu-Friedy Dental Instruments',
                'brand' => 'Hu-Friedy Microsurgical / Salvin',
                'unit' => 'pcs',
                'has_expiration' => false,
                'min_reorder' => 1,
                'reorder_qty' => 3,
                'base_sku_prefix' => 'RARE-SPEC',
                'cost_range' => [95.00, 620.00],
                'locations' => ['Specialty Surgery Vault', 'Microsurgery Case A'],
                'items' => self::generateRareAndSpecialtyVariations(),
            ],
        ];

        // 4. Batch Insertion for Performance
        $batchSize = 250;
        $now = now();
        $totalCreated = 0;

        $itemsToInsert = [];
        $batchesToInsert = [];
        $skuCounter = 1000;

        foreach ($catalogTemplates as $template) {
            $supplierId = $supplierMap[$template['supplier_key']] ?? $allSupplierIds[array_rand($allSupplierIds)];

            foreach ($template['items'] as $itemData) {
                $skuCounter++;
                $sku = $template['base_sku_prefix'] . '-' . str_pad((string)$skuCounter, 6, '0', STR_PAD_LEFT);
                $barcode = '088' . rand(1000000000, 9999999999);
                
                $unitCost = isset($itemData['cost']) 
                    ? $itemData['cost'] 
                    : round(rand((int)($template['cost_range'][0] * 100), (int)($template['cost_range'][1] * 100)) / 100, 2);
                $sellingPrice = round($unitCost * (1.35 + (rand(0, 40) / 100)), 2);

                $location = $template['locations'][array_rand($template['locations'])];

                $itemRecord = [
                    'practice_id' => $practice->id,
                    'supplier_id' => $supplierId,
                    'name' => $itemData['name'],
                    'brand' => $itemData['brand'] ?? $template['brand'],
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'category' => $template['category'],
                    'sub_category' => $itemData['sub_category'] ?? $template['sub_category'],
                    'unit' => $itemData['unit'] ?? $template['unit'],
                    'unit_price' => $unitCost,
                    'selling_price' => $sellingPrice,
                    'min_reorder_level' => $template['min_reorder'],
                    'reorder_quantity' => $template['reorder_qty'],
                    'storage_location' => $location,
                    'has_expiration' => $template['has_expiration'],
                    'description' => $itemData['desc'] ?? "Clinical grade {$itemData['name']} for dental procedures.",
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $itemsToInsert[] = $itemRecord;
            }
        }

        // Chunk insert items
        $chunks = array_chunk($itemsToInsert, $batchSize);
        foreach ($chunks as $chunk) {
            DB::table('inventory_items')->insert($chunk);
        }

        // Retrieve inserted items to generate real Batches
        $insertedItems = DB::table('inventory_items')
            ->select('id', 'supplier_id', 'has_expiration', 'unit_price', 'min_reorder_level')
            ->get();

        $batchLotCounter = 50000;
        foreach ($insertedItems as $item) {
            $batchLotCounter++;
            $lotNumber = 'LOT-' . date('y') . '-' . str_pad((string)$batchLotCounter, 6, '0', STR_PAD_LEFT);
            
            // Generate realistic quantity
            $receivedQty = rand(15, 120);
            // Some items low stock, some healthy, some out of stock for realistic dashboard
            $stockRand = rand(1, 100);
            if ($stockRand <= 5) {
                $remainingQty = 0; // Out of stock
            } elseif ($stockRand <= 15) {
                $remainingQty = rand(1, (int)$item->min_reorder_level); // Low stock
            } else {
                $remainingQty = rand((int)$item->min_reorder_level + 5, $receivedQty);
            }

            // Expiry Date calculation
            $expiryDate = null;
            if ($item->has_expiration) {
                // Distribute: 5% expired, 10% expiring soon (next 60 days), 85% active (1-3 years out)
                $expRand = rand(1, 100);
                if ($expRand <= 5) {
                    $expiryDate = Carbon::now()->subDays(rand(10, 180))->format('Y-m-d');
                } elseif ($expRand <= 15) {
                    $expiryDate = Carbon::now()->addDays(rand(5, 55))->format('Y-m-d');
                } else {
                    $expiryDate = Carbon::now()->addMonths(rand(8, 36))->format('Y-m-d');
                }
            }

            $receivedDate = Carbon::now()->subMonths(rand(1, 12))->format('Y-m-d');

            $batchesToInsert[] = [
                'inventory_item_id' => $item->id,
                'supplier_id' => $item->supplier_id,
                'batch_number' => $lotNumber,
                'expiry_date' => $expiryDate,
                'received_date' => $receivedDate,
                'unit_cost' => $item->unit_price,
                'quantity_received' => $receivedQty,
                'quantity_remaining' => $remainingQty,
                'notes' => 'Inspected and certified compliant with ISO 13485 standards.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Chunk insert batches
        $batchChunks = array_chunk($batchesToInsert, $batchSize);
        foreach ($batchChunks as $bChunk) {
            DB::table('inventory_batches')->insert($bChunk);
        }
    }

    // ==========================================
    // VARIATION GENERATORS FOR 5,000+ DENTAL ITEMS
    // ==========================================

    private static function generateBurVariations(string $type): array
    {
        $items = [];
        $grits = ['Super Coarse (Black)', 'Coarse (Green)', 'Medium (Blue)', 'Fine (Red)', 'Super Fine (Yellow)'];
        $shapes = [
            'Round Ball (#010, #012, #014, #016, #018, #021, #023)',
            'Inverted Cone (#010, #012, #014, #016)',
            'Pear Shape (#330, #245, #331, #332)',
            'Flat End Cylinder (#010, #012, #014, #016)',
            'Round End Cylinder (#012, #014, #016, #018)',
            'Flat End Taper (#012, #014, #016, #018, #021)',
            'Round End Taper (#012, #014, #016, #018, #021)',
            'Flame Shape (#010, #012, #014, #016, #018)',
            'Football / Egg Shape (#014, #016, #018, #021, #023)',
            'Needle Interproximal (#008, #010, #012, #014)',
            'Wheel Shape (#014, #016, #018, #021)',
            'Cross-Cut Fissure (#557, #558, #700, #701, #702, #703)',
            'Surgical Length 25mm (#702SL, #703SL, #Zekrya)',
            'Crown Cutting Metal Buster (#Talon, #Transmetal)',
            '12-Blade Trimming & Finishing Carbide',
            '30-Blade Ultra-Fine Finishing Carbide',
            'Endo Access Non-End Cutting Bur (#Endo-Z, #Diamendo)',
        ];

        $shanks = ['FG (Friction Grip Standard)', 'FG Short Shank', 'RA (Right Angle / Latch)', 'HP (Handpiece 44mm)'];
        $brands = $type === 'Diamond'
            ? ['Brasseler USA Polar Diamond', 'Komet Dental Precision Diamond', 'SS White Great White Diamond', 'Mani Precision Diamond']
            : ['Brasseler USA Carbide', 'SS White Carbide', 'Midwest Once-Carbide', 'Meisinger Surgical Carbide'];

        foreach ($brands as $brand) {
            foreach ($shapes as $shape) {
                foreach ($grits as $grit) {
                    foreach ($shanks as $shank) {
                        $items[] = [
                            'name' => "{$brand} - {$shape} [{$grit}, {$shank}]",
                            'brand' => explode(' ', $brand)[0],
                            'desc' => "High-precision clinical {$type} rotary bur for tooth prep, crown cutting, and margin refinement.",
                        ];
                    }
                }
            }
        }

        return $items; // Generates 4 * 17 * 5 * 4 = 1,360 burs per type (2,720 total)
    }

    private static function generateCompositeVariations(): array
    {
        $items = [];
        $shades = ['A1', 'A2', 'A3', 'A3.5', 'A4', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3', 'D2', 'D3', 'Bleach Extra Light (XBW)', 'Bleach White (BW)', 'Universal Opaque (OA2)', 'Universal Opaque (OA3)', 'Enamel Translucent (CT)', 'Enamel Amber (AT)', 'Dentin (A2D)', 'Dentin (A3D)'];
        $types = [
            'Filtek Supreme Ultra Universal Nanocomposite (4g Syringe)',
            'Filtek Supreme Ultra Flowable Restorative (2x2g Syringes)',
            'Filtek One Bulk Fill Restorative (4g Syringe)',
            'Filtek Bulk Fill Flowable (2x2g Syringes)',
            'Herculite Ultra Microhybrid Composite (4g Syringe)',
            'Harmonize Nanohybrid Universal Composite (4g Syringe)',
            'Tetric Prime Light-Curing Composite (3g Syringe)',
            'Tetric PowerFill 3-Second Bulk-Fill Composite (3g Syringe)',
            'G-aenial Universal Injectable High-Strength Flowable (1.7g)',
            'Gradia Direct Anterior / Posterior Composite (4g Syringe)',
            'SonicFill 3 Single-Step Bulk Fill System Composite (0.25g Unidose)',
            'Core-Flo DC Lite Dual-Cure Core Buildup Composite (8g Dual-Barrel)',
            'GrandioSO Heavy Flow Nanohybrid Flowable (2g Syringe)',
        ];

        foreach ($types as $type) {
            foreach ($shades as $shade) {
                $items[] = [
                    'name' => "{$type} - Shade {$shade}",
                    'desc' => "Light-cured radiopaque dental restorative resin designed for anterior and posterior aesthetic restorations.",
                ];
            }
        }

        return $items; // ~ 13 * 21 = 273 composite items
    }

    private static function generateBondingVariations(): array
    {
        $items = [];
        $products = [
            ['name' => 'Scotchbond Universal Plus Adhesive (5ml Bottle)', 'brand' => '3M Oral Care', 'cost' => 128.00],
            ['name' => 'Scotchbond Universal Plus Unit Dose (100 Applicators/Box)', 'brand' => '3M Oral Care', 'cost' => 245.00],
            ['name' => 'All-Bond Universal Dual-Cured Dental Adhesive (6ml)', 'brand' => 'Bisco Dental', 'cost' => 118.00],
            ['name' => 'Clearfil SE Bond 2 Self-Etch Primer (6ml) & Bond (5ml) Kit', 'brand' => 'Kuraray Noritake', 'cost' => 195.00],
            ['name' => 'OptiBond Universal Single-Component Adhesive (5ml)', 'brand' => 'Kerr Dental', 'cost' => 112.00],
            ['name' => 'OptiBond FL Total-Etch 2-Bottle Bonding System Kit', 'brand' => 'Kerr Dental', 'cost' => 175.00],
            ['name' => 'G-Premio BOND Universal 8th Generation Adhesive (5ml)', 'brand' => 'GC America', 'cost' => 108.00],
            ['name' => 'Prime&Bond elect Universal Dental Adhesive (5ml)', 'brand' => 'Dentsply Sirona', 'cost' => 115.00],
            ['name' => 'Phosphoric Acid Etchant Gel 37% (4x1.2ml Syringes + 20 Tips)', 'brand' => 'Ultradent Ultra-Etch', 'cost' => 28.00],
            ['name' => 'Phosphoric Acid Etchant Jumbo Bulk Refill 37% (30ml Syringe)', 'brand' => 'Bisco Uni-Etch', 'cost' => 45.00],
            ['name' => 'Porcelain Etch Hydrofluoric Acid 9.5% (2x1.2ml Syringes)', 'brand' => 'Bisco / Ultradent', 'cost' => 38.00],
            ['name' => 'Silane Porcelain Coupling Agent (2x1.2ml Syringes)', 'brand' => 'Ultradent', 'cost' => 34.00],
            ['name' => 'Z-Prime Plus Zirconia & Alumina Primer (4ml Bottle)', 'brand' => 'Bisco Dental', 'cost' => 145.00],
            ['name' => 'Monobond Plus Universal Ceramic / Metal Primer (5g)', 'brand' => 'Ivoclar Vivadent', 'cost' => 138.00],
            ['name' => 'TheraCal LC Light-Cured Resin-Modified Calcium Silicate Liner', 'brand' => 'Bisco Dental', 'cost' => 78.00],
            ['name' => 'TheraBase Dual-Cure Calcium & Fluoride-Releasing Base/Liner', 'brand' => 'Bisco Dental', 'cost' => 92.00],
            ['name' => 'Dycal Radiopaque Calcium Hydroxide Liner (Base 13g + Catal 11g)', 'brand' => 'Dentsply Sirona', 'cost' => 48.00],
            ['name' => 'Vitrebond Plus Light Cure Glass Ionomer Liner/Base (10g Clicker)', 'brand' => '3M Oral Care', 'cost' => 165.00],
            ['name' => 'Fuji LINING LC Glass Ionomer Cavity Liner (10g Powder / 6.8ml Liq)', 'brand' => 'GC America', 'cost' => 125.00],
            ['name' => 'Cavit-G Temporary Filling Material in Jar (28g)', 'brand' => '3M Oral Care', 'cost' => 22.00],
            ['name' => 'IRM Intermediate Restorative Material ZOE (Powder 38g / Liquid 14ml)', 'brand' => 'Dentsply Sirona', 'cost' => 64.00],
            ['name' => 'Microbrush Plus Disposable Applicator Tubes (400 Pack Assorted)', 'brand' => 'Microbrush International', 'cost' => 36.00],
            ['name' => 'Dappen Dishes Glass Hexagonal Heavy (6 Pack Assorted Colors)', 'brand' => 'Henry Schein', 'cost' => 18.00],
        ];

        foreach ($products as $p) {
            $items[] = [
                'name' => $p['name'],
                'brand' => $p['brand'],
                'cost' => $p['cost'],
                'desc' => "High-strength adhesive, etchant, or cavity liner for clinical bonding procedures.",
            ];
        }

        return $items;
    }

    private static function generateMatrixAndPolishingVariations(): array
    {
        $items = [];
        $matrices = [
            'Palodent Plus Sectional Matrix Rings (Universal Standard Blue)',
            'Palodent Plus Sectional Matrix Rings (Narrow Premolar Orange)',
            'Palodent Plus Sectional Matrix Bands - 3.5mm Gingival Extension',
            'Palodent Plus Sectional Matrix Bands - 4.5mm Premolar',
            'Palodent Plus Sectional Matrix Bands - 5.5mm Molar Standard',
            'Palodent Plus Sectional Matrix Bands - 6.5mm Molar with Extension',
            'Palodent Plus Sectional Matrix Bands - 7.5mm Deep Subgingival',
            'Garrison Composi-Tight 3D Fusion Sectional Ring - Short Blue',
            'Garrison Composi-Tight 3D Fusion Sectional Ring - Tall Orange',
            'Garrison Composi-Tight 3D Fusion Sectional Ring - Wide Prep Green',
            'Garrison 3D Fusion Ultra-Thin Sectional Bands (FX100, FX175, FX200, FX300)',
            'Tofflemire Matrix Retainer Universal Stainless Steel (Adult)',
            'Tofflemire Matrix Retainer Contra-Angle Stainless Steel (Lingual/Buccal)',
            'Tofflemire Matrix Retainer Junior / Pediatric',
            'Stainless Steel Matrix Bands #1 Universal 0.0015" Regular (100/Box)',
            'Stainless Steel Matrix Bands #1 Universal 0.0010" Ultra-Thin (100/Box)',
            'Stainless Steel Matrix Bands #2 MOD Molar "Wide Wing" (100/Box)',
            'Stainless Steel Matrix Bands #13 Pediatric Extension (100/Box)',
            'Mylar Celluloid Strips Anterior Translucent 0.05mm (100/Box)',
            'Anatomical Wooden Wedges - Extra Small (Yellow, 100/Box)',
            'Anatomical Wooden Wedges - Small (Orange, 100/Box)',
            'Anatomical Wooden Wedges - Medium (Green, 100/Box)',
            'Anatomical Wooden Wedges - Large (Pink, 100/Box)',
            'Garrison 3D Fusion Soft-Wedge Silicone Adaptive Wedges (Assorted 400/Pk)',
            'Palodent Plus WedgeGuards Interproximal Bur Shields (Small, Medium, Large)',
            'Sof-Lex Contouring & Polishing Discs - Coarse 1/2" (8692C, 85/Pk)',
            'Sof-Lex Contouring & Polishing Discs - Medium 1/2" (8692M, 85/Pk)',
            'Sof-Lex Contouring & Polishing Discs - Fine 1/2" (8692F, 85/Pk)',
            'Sof-Lex Contouring & Polishing Discs - Superfine 1/2" (8692SF, 85/Pk)',
            'Sof-Lex XT Extra-Thin Polishing Discs - 3/8" Assorted Pack (240/Box)',
            'Sof-Lex Pop-On Mandrel Stainless Steel RA Latch (3/Pk)',
            'Enhance Finishing Cups, Points & Discs Composite System (40/Box)',
            'Enhance PoGo Diamond Polishing Discs & Points (40/Box)',
            'OptiShine Composite Polishing Brushes with Silicon Carbide (10/Pk)',
            'Prisma Gloss Composite Polishing Paste Extra Fine (4g Syringe)',
            'Diamond Excel Diamond Polishing Paste 0.5 Micron (2g Syringe)',
            'Interproximal Diamond Finishing Strips Single-Sided Medium/Fine (12/Pk)',
            'Epitex Finishing & Polishing Strips Assorted Starter Pack (4x10m Rolls)',
        ];

        foreach ($matrices as $m) {
            $items[] = [
                'name' => $m,
                'desc' => "Essential restorative matrix band, ring, wedge, or composite finishing system.",
            ];
        }

        return $items;
    }

    private static function generateEndoFileVariations(): array
    {
        $items = [];
        $handFileSizes = ['#06 Pink', '#08 Gray', '#10 Purple', '#15 White', '#20 Yellow', '#25 Red', '#30 Blue', '#35 Green', '#40 Black', '#45 White', '#50 Yellow', '#55 Red', '#60 Blue', '#70 Green', '#80 Black', '#15-40 Assorted', '#45-80 Assorted'];
        $lengths = ['21mm', '25mm', '31mm'];
        $brands = ['Dentsply Maillefer', 'VDW Dental', 'Mani Medical', 'Kerr Endodontics', 'FKG Dentaire'];
        $types = ['K-Files Stainless Steel (6/Pk)', 'Hedstrom H-Files (6/Pk)', 'K-Flex Files Flexible (6/Pk)', 'C-Pilot Calcified Canal Files (6/Pk)', 'Barbed Broaches (10/Pk)'];

        foreach ($brands as $brand) {
            foreach ($types as $type) {
                foreach ($lengths as $length) {
                    foreach ($handFileSizes as $size) {
                        $items[] = [
                            'name' => "{$brand} {$type} - Size {$size}, Length {$length}",
                            'brand' => $brand,
                            'desc' => "ISO standardized root canal hand instrument for initial canal scout, apical negotiation, and enlargement.",
                        ];
                    }
                }
            }
        } // 5 * 5 * 3 * 17 = 1,275 hand files

        // Rotary File Systems
        $rotarySystems = [
            'ProTaper Gold Rotary Files (6/Pk)' => ['SX 19mm', 'S1 21mm', 'S1 25mm', 'S1 31mm', 'S2 21mm', 'S2 25mm', 'S2 31mm', 'F1 21mm', 'F1 25mm', 'F1 31mm', 'F2 21mm', 'F2 25mm', 'F2 31mm', 'F3 21mm', 'F3 25mm', 'F3 31mm', 'F4 25mm', 'F5 25mm', 'Assorted SX-F3 25mm'],
            'ProTaper Ultimate Rotary Files (6/Pk)' => ['Slider 25mm', 'Shaper 25mm', 'F1 25mm', 'F2 25mm', 'F3 25mm', 'FX 25mm', 'FXL 25mm', 'Sequence Assorted Kit 25mm'],
            'WaveOne Gold Reciprocating Files (3/Pk)' => ['Small #20.07 21mm', 'Small #20.07 25mm', 'Small #20.07 31mm', 'Primary #25.07 21mm', 'Primary #25.07 25mm', 'Primary #25.07 31mm', 'Medium #35.06 21mm', 'Medium #35.06 25mm', 'Medium #35.06 31mm', 'Large #45.05 21mm', 'Large #45.05 25mm', 'Large #45.05 31mm', 'Assorted Pack 25mm'],
            'ProTaper Next Rotary Files (3/Pk)' => ['X1 #17.04 25mm', 'X2 #25.06 25mm', 'X3 #30.07 25mm', 'X4 #40.06 25mm', 'X5 #50.06 25mm', 'Assorted X1-X3 25mm'],
            'TruNatomy Heat-Treated NiTi Files (3/Pk)' => ['Orifice Modifier 16mm', 'Glider #17.02 25mm', 'Small #20.04 25mm', 'Prime #26.04 25mm', 'Medium #36.03 25mm', 'Assorted Prime Sequence 25mm'],
            'Reciproc Blue Reciprocating NiTi (6/Pk)' => ['R25 #25.08 21mm', 'R25 #25.08 25mm', 'R25 #25.08 31mm', 'R40 #40.06 25mm', 'R50 #50.05 25mm', 'Assorted R25/R40/R50 25mm'],
            'HyFlex CM Controlled Memory NiTi Files (6/Pk)' => ['#25.08 19mm', '#20.04 25mm', '#25.04 25mm', '#30.04 25mm', '#40.04 25mm', '#20.06 25mm', '#25.06 25mm', '#30.06 25mm', 'Sequence Assorted 25mm'],
            'Peeso Reamers Stainless Steel RA (6/Pk)' => ['Size #1 (0.70mm)', 'Size #2 (0.90mm)', 'Size #3 (1.10mm)', 'Size #4 (1.30mm)', 'Size #5 (1.50mm)', 'Size #6 (1.70mm)', 'Assorted #1-6 32mm'],
            'Gates Glidden Drills Rotary RA (6/Pk)' => ['Size #1 (0.50mm)', 'Size #2 (0.70mm)', 'Size #3 (0.90mm)', 'Size #4 (1.10mm)', 'Size #5 (1.30mm)', 'Size #6 (1.50mm)', 'Assorted #1-6 32mm', 'Assorted #1-6 28mm Short'],
        ];

        foreach ($rotarySystems as $system => $files) {
            foreach ($files as $file) {
                $items[] = [
                    'name' => "{$system} - {$file}",
                    'desc' => "Advanced endodontic nickel-titanium rotary/reciprocating shaping file.",
                ];
            }
        }

        return $items; // Total ~ 350 file variations
    }

    private static function generateEndoObturationVariations(): array
    {
        $items = [];
        $guttaSizes = ['#15', '#20', '#25', '#30', '#35', '#40', '#45', '#50', '#55', '#60', '#70', '#80', '#15-40 Assorted'];
        $tapers = ['0.02 Standard ISO', '0.04 Greater Taper', '0.06 Greater Taper'];

        foreach ($tapers as $taper) {
            foreach ($guttaSizes as $sz) {
                $items[] = [
                    'name' => "Gutta-Percha Points ({$taper}) - Size {$sz} (60/Box)",
                    'desc' => "Thermoplastic radiopaque filling cones for root canal obturation.",
                ];
                $items[] = [
                    'name' => "Sterile Absorbent Paper Points ({$taper}) - Size {$sz} (200/Box)",
                    'desc' => "Highly absorbent paper points for drying root canals prior to sealing.",
                ];
            }
        }

        // Matching Gutta-Percha Cones for ProTaper & WaveOne
        $systemCones = [
            'ProTaper Gold Conform Fit Gutta-Percha (F1, F2, F3, F4, F5, Assorted 60/Box)',
            'WaveOne Gold Conform Fit Gutta-Percha (Small, Primary, Medium, Large 60/Box)',
            'TruNatomy Conform Fit Gutta-Percha Points (Small, Prime, Medium 60/Box)',
            'AH Plus Bioceramic Root Canal Sealer (3g Pre-Mixed Syringe + 20 Tips)',
            'AH Plus Jet Root Canal Sealer Paste (2x15g Dual-Syringes + Mixing Tips)',
            'TotalFill BC Sealer Bioceramic Root Canal Sealer (2g Pre-Mixed Syringe)',
            'TotalFill BC RRM Fast Set Putty Bioceramic Root Repair Material (0.3g)',
            'MTA Angelus White Mineral Trioxide Aggregate (1g + Liquid)',
            'ProRoot MTA White Root Canal Repair Material (4x0.5g Pouches)',
            'Grossman Zinc Oxide Eugenol Root Canal Sealer Cement Kit (Powder/Liquid)',
            'Sodium Hypochlorite 5.25% Endo Irrigation Solution (1 Gallon / 3.78L)',
            'Sodium Hypochlorite 3.0% Buffered Dental Irrigant (1 Gallon / 3.78L)',
            'EDTA 17% Solution Demineralizing Canal Rinse (500ml Bottle)',
            'File-Eze 19% EDTA Water-Soluble Lubricant Gel (4x1.2ml Syringes)',
            'Chlorhexidine Gluconate 2.0% Endo Irrigating Solution (500ml Bottle)',
            'Side-Vented Closed-End Endo Irrigation Needles 30G (100/Box)',
            'Side-Vented Closed-End Endo Irrigation Needles 27G (100/Box)',
            'Endo-Ice Cold Pulp Diagnostic Spray -50°C (6 oz Canister)',
            'Sanctuary Dental Dam Powder-Free Latex Medium 6x6" Blue (36/Box)',
            'Sanctuary Non-Latex Dental Dam 6x6" Teal (15/Box)',
            'Dental Dam Frame Young Adult Stainless Steel 6"',
            'Dental Dam Punch Ainsworth Heavy Duty 5-Hole',
            'Dental Dam Clamps Winged (#00, #2, #2A, #7, #8, #8A, #9, #14, #14A, #W8A)',
            'Wedjets Dental Dam Stabilizing Cord Latex-Free (Small Blue / Large Orange)',
            'Gutta-Percha Heat Pluggerson / Buchanan Hand Pluggers (#0, #1, #2, #3)',
            'Gutta-Condensor Thermal Compactor RA Latch (Size #25, #30, #35, #40)',
            'RelyX Fiber Post Glass-Fiber Endodontic Post (Size 0, 1, 2, 3 - 10/Box)',
        ];

        foreach ($systemCones as $sc) {
            $items[] = [
                'name' => $sc,
                'desc' => "Specialized clinical endodontic obturation, irrigation, or isolation material.",
            ];
        }

        return $items;
    }

    private static function generatePerioInstrumentVariations(): array
    {
        $items = [];
        $graceys = [
            'Gracey Curette #1/2 Standard (Anterior Incisors/Canines)',
            'Gracey Curette #3/4 Standard (Anterior Incisors/Canines)',
            'Gracey Curette #5/6 Standard (Anterior & Premolars)',
            'Gracey Curette #7/8 Standard (Premolars & Molar Buccal/Lingual)',
            'Gracey Curette #9/10 Standard (Molar Buccal/Lingual Surfaces)',
            'Gracey Curette #11/12 Standard (Posterior Mesial Surfaces)',
            'Gracey Curette #13/14 Standard (Posterior Distal Surfaces)',
            'Gracey Curette #15/16 Deep Mesial Access (Modified 11/12)',
            'Gracey Curette #17/18 Deep Distal Access with Exaggerated Shank',
            'Gracey Mini-Five #1/2 (50% Shorter Blade for Narrow Pockets)',
            'Gracey Mini-Five #5/6',
            'Gracey Mini-Five #11/12',
            'Gracey Mini-Five #13/14',
            'Gracey After-Five #1/2 (3mm Longer Terminal Shank for Pockets >5mm)',
            'Gracey After-Five #5/6',
            'Gracey After-Five #11/12',
            'Gracey After-Five #13/14',
            'Gracey After-Five #15/16',
            'Gracey After-Five #17/18',
            'Sickle Scaler H6/H7 Anterior (Hygienist Favorite)',
            'Sickle Scaler 204S Posterior Sickle Universal',
            'Sickle Scaler 204SD Posterior Sickle Contra-Angle',
            'Sickle Scaler Jacquette #1 Micro-Sickle Anterior',
            'Sickle Scaler Jacquette #2 / #3 Posterior',
            'Universal Curette Columbia #13/14 (Adult Universal All Teeth)',
            'Universal Curette Columbia #2R/2L (Anterior Teeth)',
            'Universal Curette Columbia #4R/4L (Posterior Teeth)',
            'Universal Curette Barnhart #1/2 (Thin Blade for Tight Contacts)',
            'Universal Curette Barnhart #5/6',
            'Universal Curette McCall #13S/14S Heavy Calculus Remover',
            'Langer Periodontal Curette #1/2 (Mandibular Posterior)',
            'Langer Periodontal Curette #3/4 (Maxillary Posterior)',
            'Langer Periodontal Curette #5/6 (Anterior Teeth)',
            'Periodontal Probe UNC-15 Color-Coded 1-15mm Markings',
            'Periodontal Probe Williams 1-2-3-5-7-8-9-10mm Markings',
            'Periodontal Probe Marquis Color-Coded 3-6-9-12mm',
            'Periodontal Furcation Probe Nabers Q-2N Curved 3-6-9-12mm',
            'Periodontal Probe CPITN / WHO Ball-Tip 0.5mm (3.5-5.5-8.5-11.5mm)',
            'Kirkland Periodontal Gingivectomy Knife #15/16',
            'Orban Periodontal Interdental Knife #1/2',
            'Ochsenbein Bone Chisel #1 / #2 Subgingival Resection',
            'Prichard Periosteal Elevator & Large Curette Combo',
            'Cavitron Ultrasonic Insert 30K Focused Spray PowerLine FSI-1000',
            'Cavitron Ultrasonic Insert 30K Focused Spray PowerLine FSI-100',
            'Cavitron Ultrasonic Insert 30K Focused Spray PowerLine FSI-10',
            'Cavitron Ultrasonic Insert 30K SlimLine Straight FSI-SLI-10S',
            'Cavitron Ultrasonic Insert 30K SlimLine Left Curve FSI-SLI-10L',
            'Cavitron Ultrasonic Insert 30K SlimLine Right Curve FSI-SLI-10R',
            'Cavitron Ultrasonic Insert 30K THINsert Ultra-Thin Debridement',
            'Piezo Ultrasonic Scaling Tip EMS Style - Tip A (Universal Supra)',
            'Piezo Ultrasonic Scaling Tip EMS Style - Tip P (Subgingival Perio)',
            'Piezo Ultrasonic Scaling Tip EMS Style - Tip PS (Deep Subgingival Perio Slim)',
            'Piezo Ultrasonic Implant Debridement Carbon-Fiber Tips (5/Pk)',
        ];

        $handleTypes = ['#6 Satin Steel Handle', '#8 ResinEight Ergonomic Grip', '#9 EverEdge 2.0 Large Grip'];
        foreach ($graceys as $g) {
            foreach ($handleTypes as $ht) {
                $items[] = [
                    'name' => "{$g} - [Handle: {$ht}]",
                    'desc' => "Premium clinical periodontology curette or ultrasonic scaler insert.",
                ];
            }
        }

        return $items; // ~ 55 * 3 = 165 perio items
    }

    private static function generateBoneGraftVariations(): array
    {
        $items = [];
        $grafts = [
            'Geistlich Bio-Oss Bovine Xenograft Small Granules 0.25-1.0mm (0.25g / 0.5cc)',
            'Geistlich Bio-Oss Bovine Xenograft Small Granules 0.25-1.0mm (0.5g / 1.0cc)',
            'Geistlich Bio-Oss Bovine Xenograft Small Granules 0.25-1.0mm (1.0g / 2.0cc)',
            'Geistlich Bio-Oss Bovine Xenograft Large Granules 1.0-2.0mm (0.5g / 1.5cc)',
            'Geistlich Bio-Oss Bovine Xenograft Large Granules 1.0-2.0mm (1.0g / 3.0cc)',
            'Geistlich Bio-Oss Collagen 90% Granules + 10% Collagen Block (100mg)',
            'Geistlich Bio-Oss Collagen 90% Granules + 10% Collagen Block (250mg)',
            'Geistlich Bio-Oss Collagen 90% Granules + 10% Collagen Block (500mg)',
            'Geistlich Bio-Gide Resorbable Bilayer Collagen Membrane 13x25mm',
            'Geistlich Bio-Gide Resorbable Bilayer Collagen Membrane 25x25mm',
            'Geistlich Bio-Gide Resorbable Bilayer Collagen Membrane 30x40mm',
            'MinerOss Allograft Mineralized Cortical/Cancellous Blend 0.5cc',
            'MinerOss Allograft Mineralized Cortical/Cancellous Blend 1.0cc',
            'MinerOss Allograft Mineralized Cortical/Cancellous Blend 2.0cc',
            'Puros Allograft Demineralized Bone Matrix DBM Putty 0.5cc Syringe',
            'Puros Allograft Demineralized Bone Matrix DBM Putty 1.0cc Syringe',
            'Puros Allograft Demineralized Bone Matrix DBM Putty 2.0cc Syringe',
            'Cytoplast TXT-200 Non-Resorbable High-Density dPTFE Membrane 12x24mm',
            'Cytoplast TXT-200 Non-Resorbable High-Density dPTFE Membrane 25x30mm',
            'Cytoplast Ti-250 Titanium-Reinforced dPTFE Membrane Posterior Single 14x24mm',
            'Cytoplast Ti-250 Titanium-Reinforced dPTFE Membrane Anterior Narrow 12x24mm',
            'Cytoplast Ti-250 Titanium-Reinforced dPTFE Membrane Posterior Large 20x25mm',
            'CollaPlug Absorbable Collagen Wound Dressing Plugs (10/Box)',
            'CollaTape Absorbable Collagen Wound Dressing Tape 2.5x7.5cm (10/Box)',
            'CollaCote Absorbable Collagen Sponge Matrix 2.0x4.0cm (10/Box)',
            'Titanium Bone Tack Fixation Kit (Tack Gun + 20 Titanium Tacks 3.5mm/4.5mm)',
            'Titanium Micro-Mesh 0.1mm Thickness 34x25mm with Pore Diameter 0.6mm',
            'Bone Morselizer / Bone Mill Stainless Steel Autogenous Bone Crusher',
            'Bone Collector / Suction Bone Filter Autogenous Harvesting Trap',
        ];

        foreach ($grafts as $gr) {
            $items[] = [
                'name' => $gr,
                'desc' => "Regenerative biomaterial, allograft/xenograft bone matrix, or guided bone regeneration membrane.",
            ];
        }

        return $items;
    }

    private static function generateOralSurgeryVariations(): array
    {
        $items = [];
        $instruments = [
            'Dental Extraction Forceps #150 Universal Upper Incisors, Cuspids & Bicuspids',
            'Dental Extraction Forceps #150S Universal Upper Pediatric/Small Hand',
            'Dental Extraction Forceps #151 Universal Lower Incisors, Cuspids & Bicuspids',
            'Dental Extraction Forceps #151S Universal Lower Pediatric/Small Hand',
            'Dental Extraction Forceps #1 Upper Straight Anteriors',
            'Dental Extraction Forceps #53R Upper Molar Right Tri-Beak',
            'Dental Extraction Forceps #53L Upper Molar Left Tri-Beak',
            'Dental Extraction Forceps #88R Upper 1st/2nd Molar Right Cowhorn',
            'Dental Extraction Forceps #88L Upper 1st/2nd Molar Left Cowhorn',
            'Dental Extraction Forceps #23 Lower Molar Cowhorn (Bifurcation Engagement)',
            'Dental Extraction Forceps #16 Lower Molar Cowhorn Heavy Beak',
            'Dental Extraction Forceps #17 Lower Molar Universal Straight Beak',
            'Dental Extraction Forceps #222 Lower 3rd Molar Wisdom Tooth Forceps',
            'Dental Extraction Forceps #210 Upper 3rd Molar Universal Bayonet',
            'Dental Extraction Forceps #65 Upper Root Tip Extraction Forceps',
            'Dental Extraction Forceps #69 Lower Universal Root Tip Forceps',
            'Dental Extraction Forceps #74 Lower Anterior Root Fragment English Pattern',
            'Dental Extraction Forceps #79 Lower 3rd Molar English Pattern',
            'Dental Extraction Forceps #13 Lower Premolar English Pattern',
            'Dental Extraction Forceps #22 Lower Molar English Pattern Hawk-Bill',
            'Dental Extraction Forceps #33 Lower Root English Pattern',
            'Straight Dental Elevator Coupland #1 (Small 3.0mm Tip)',
            'Straight Dental Elevator Coupland #2 (Medium 3.5mm Tip)',
            'Straight Dental Elevator Coupland #3 (Large 4.0mm Tip)',
            'Straight Dental Elevator Bein #1 (2.0mm Micro-Tip)',
            'Straight Dental Elevator Bein #2 (3.0mm Straight Tip)',
            'Straight Dental Elevator Bein #3 (4.0mm Straight Tip)',
            'Cryer Elevator #30 Triangle Left Angled Elevator',
            'Cryer Elevator #31 Triangle Right Angled Elevator',
            'Warwick James Elevator Straight 2.0mm',
            'Warwick James Elevator Left Curved',
            'Warwick James Elevator Right Curved',
            'Heidbrink Root Tip Pick #1 Straight Delicate',
            'Heidbrink Root Tip Pick #2 Left Angle 90-Degree',
            'Heidbrink Root Tip Pick #3 Right Angle 90-Degree',
            'Crane Pick Elevator #8 Heavy Duty Root Splitting Elevator',
            'Luxator Titanium Coated 2mm Straight (Green Handle)',
            'Luxator Titanium Coated 3mm Straight (Black Handle)',
            'Luxator Titanium Coated 3mm Curved (Blue Handle)',
            'Luxator Titanium Coated 5mm Straight (Brown Handle)',
            'Luxator Titanium Coated 5mm Curved (Yellow Handle)',
            'Luxator Periotome Dual-Edge Titanium 2mm/3mm',
            'Molt Periosteal Elevator #9 (Standard Double Ended Sharp/Blunt)',
            'Seldin Periosteal Elevator #23 Straight / Curved',
            'Freer Periosteal Elevator Double Ended 4.5mm',
            'Lucas Surgical Bone Curette #84 (Small Double Ended 1.8mm)',
            'Lucas Surgical Bone Curette #85 (Medium Double Ended 2.5mm)',
            'Lucas Surgical Bone Curette #86 (Large Double Ended 2.8mm)',
            'Lucas Surgical Bone Curette #87 (Extra Large Double Ended 3.5mm)',
            'Friedman Bone Rongeur 5.5" 30-Degree Angle Delicate',
            'Blumenthal Bone Rongeur 6.0" 45-Degree Angle',
            'Cleveland Bone Rongeur #4 Heavy Duty 6.5"',
            'Miller Bone File #1 Double Ended Straight Cross-Cut',
            'Miller Bone File #2 Double Ended Curved Cross-Cut',
            'Halsted Mosquito Hemostatic Forceps 5.0" Straight',
            'Halsted Mosquito Hemostatic Forceps 5.0" Curved',
            'Kelly Hemostatic Forceps 5.5" Curved Serrated',
            'Adson Tissue Forceps 4.75" 1x2 Teeth (Delicate Flap Handling)',
            'Adson-Brown Tissue Forceps 4.75" 7x7 Fine Teeth',
            'Gerald Micro-Tissue Forceps 7.0" Straight Delicate 1x2 Teeth',
            'Castroviejo Micro Needle Holder 5.5" Straight with Lock (Tungsten Carbide)',
            'Castroviejo Micro Needle Holder 5.5" Curved with Lock (Tungsten Carbide)',
            'Mayo-Hegar Needle Holder 6.0" Tungsten Carbide Inserts',
            'Mathieu Standard Ortho & Surgery Needle Holder 5.5" (Plier Grip)',
            'Goldman-Fox Surgical Gum Scissors 5.0" Curved Serrated',
            'Castroviejo Micro-Surgical Scissors 6.0" Curved Sharp/Sharp',
            'Iris Scissors 4.5" Straight Delicate Sharp/Sharp',
            'Iris Scissors 4.5" Curved Delicate Sharp/Sharp',
            'Minnesota Cheek & Flap Retractor Stainless Steel',
            'Weider Tongue Retractor / Sweetheart Retractor Small',
            'Weider Tongue Retractor / Sweetheart Retractor Large',
            'Bischof Lip & Cheek Retractor Self-Retaining',
            'Molt Mouth Prop / Gag Adult with Silicone Inserts',
            'Molt Mouth Prop / Gag Pediatric with Silicone Inserts',
        ];

        foreach ($instruments as $inst) {
            $items[] = [
                'name' => $inst,
                'desc' => "Surgical-grade stainless steel dental extraction or oral surgery instrument.",
            ];
        }

        return $items; // ~ 75 surgical instruments
    }

    private static function generateSurgicalConsumablesVariations(): array
    {
        $items = [];
        $sutureSizes = ['3-0', '4-0', '5-0', '6-0'];
        $sutureMaterials = [
            'Vicryl (Polyglactin 910) Braided Absorbable Suture, Reverse Cutting PS-2 Needle 19mm',
            'Chromic Gut Absorbable Natural Suture, Reverse Cutting C-3 Needle 13mm',
            'Plain Gut Fast Absorbable Natural Suture, Reverse Cutting FS-2 Needle 19mm',
            'Perma-Hand Black Braided Silk Non-Absorbable Suture, Reverse Cutting FS-2 19mm',
            'Ethilon Monofilament Black Nylon Non-Absorbable Suture, Reverse Cutting PS-3 16mm',
            'PTFE (Polytetrafluoroethylene) Monofilament Non-Absorbable Suture, Precision Reverse Cutting 16mm',
            'Monocryl (Poliglecaprone 25) Monofilament Absorbable Suture, Cutting PC-3 16mm',
        ];

        foreach ($sutureMaterials as $mat) {
            foreach ($sutureSizes as $sz) {
                $items[] = [
                    'name' => "{$mat} - Size {$sz} (12/Box)",
                    'desc' => "Sterile surgical suture with precision swaged reverse cutting needle for oral mucoperiosteal flaps.",
                ];
            }
        } // 7 * 4 = 28 suture items

        $consumables = [
            'Swann-Morton Sterile Surgical Scalpel Blades #15 (100/Box)',
            'Swann-Morton Sterile Surgical Scalpel Blades #15C Extra Slim (100/Box)',
            'Swann-Morton Sterile Surgical Scalpel Blades #11 Pointed Stab Incision (100/Box)',
            'Swann-Morton Sterile Surgical Scalpel Blades #12 Curved Retromolar (100/Box)',
            'Swann-Morton Sterile Surgical Scalpel Blades #12D Double-Edged (100/Box)',
            'Gelfoam Absorbable Gelatin Sponge Dental Pack Size 4 (2x2cm, 24/Box)',
            'Surgicel Absorbable Hemostat Oxidized Regenerated Cellulose 0.5x2" (12/Pk)',
            'Alveogyl Dry Socket Alveolar Osteitis Dressing Paste (10g Jar)',
            'Bone Wax Sterile Hemostatic Beeswax Slabs 2.5g (12/Box)',
            'Disposable Scalpel with Plastic Handle & Protective Guard #15 (10/Box)',
            'Disposable Scalpel with Plastic Handle & Protective Guard #15C (10/Box)',
            'Sterile Surgical Cotton Gauze Sponges 2x2" 8-Ply (200/Box - 2/Pouch)',
            'Sterile Surgical Cotton Gauze Sponges 4x4" 12-Ply (100/Box)',
            'Sterile Surgical Saline 0.9% Sodium Chloride Irrigation Solution (500ml Bottle)',
            'Sterile Water for Irrigation USP (1000ml Bottle)',
            'Surgical Tubing Set with Flow Control for Implant & Piezo Units (10/Box)',
        ];

        foreach ($consumables as $c) {
            $items[] = [
                'name' => $c,
                'desc' => "Sterile oral surgical consumable, hemostatic dressing, or scalpel blade.",
            ];
        }

        return $items;
    }

    private static function generateProsthodonticVariations(): array
    {
        $items = [];
        $materials = [
            'Impregum Penta Soft Polyether Medium Body Impression Material (2x300ml Base + 2x60ml Catal)',
            'Impregum Soft Polyether Light Body Handmix Tube (120ml Base + 15ml Catal)',
            'Express STD Addition Silicone (PVS) Heavy Body Putty (2x305ml Jars)',
            'Aquasil Ultra+ Smart Wetting Impression Material Heavy Body Fast Set (4x50ml Cartridges)',
            'Aquasil Ultra+ Smart Wetting Impression Material Medium Body Regular Set (4x50ml Cartridges)',
            'Aquasil Ultra+ Smart Wetting Impression Material Light Body Wash Fast Set (4x50ml Cartridges)',
            'Aquasil Ultra+ Smart Wetting Impression Material Extra Light Body (4x50ml Cartridges)',
            'Cavex CA37 Superior Dust-Free Alginate Normal Set (500g Bag)',
            'Cavex ColorChange Chromatic Fast Set Alginate (500g Bag)',
            'Jeltrate Plus Dustless Antimicrobial Alginate Fast Set (454g Canister)',
            'Alginmax High-Precision Chromatic Alginate Class A (453g Bag)',
            'Blu-Mousse Vinyl Polysiloxane Fast-Setting Bite Registration (2x50ml Cartridges)',
            'Regisil PB Rigid Addition Silicone Bite Registration (4x50ml Cartridges)',
            'Futar D Fast Extra-Hard Bite Registration Material 30-Second (2x50ml)',
            'RelyX Unicem 2 Automix Self-Adhesive Universal Resin Cement (1x8.5g Syringe Translucent)',
            'RelyX Unicem 2 Automix Self-Adhesive Universal Resin Cement (1x8.5g Syringe Shade A2)',
            'RelyX Unicem 2 Automix Self-Adhesive Universal Resin Cement (1x8.5g Syringe Shade A3 Opaque)',
            'RelyX Luting Plus Resin-Modified Glass Ionomer Cement (2x8.5g Automix Syringes)',
            'Panavia V5 Dual-Curing Resin Cement Standard Kit (Universal A2 / Clear / White)',
            'Panavia SA Cement Universal Self-Adhesive Resin Cement Automix (8.2g Syringe)',
            'Variolink Esthetic DC Dual-Curing Adhesive Cement (9g Syringe Light/Neutral/Warm)',
            'Temp-Bond NE Non-Eugenol Temporary Crown & Bridge Cement Automix (2x11.8g)',
            'Temp-Bond Original Zinc Oxide Eugenol Temporary Cement (50g Tube Base + 15g Accel)',
            'Ketac Cem Maxicap Radiopaque Permanent Glass Ionomer Luting Cement (50 Capsules)',
            'Durelon Carboxylate Permanent Luting Cement Triple Liquid (40ml) & Powder (3x20g)',
            'Protemp 4 Bis-Acryl Temporary Crown & Bridge Material (50ml Cartridge Shade A1)',
            'Protemp 4 Bis-Acryl Temporary Crown & Bridge Material (50ml Cartridge Shade A2)',
            'Protemp 4 Bis-Acryl Temporary Crown & Bridge Material (50ml Cartridge Shade A3)',
            'Protemp 4 Bis-Acryl Temporary Crown & Bridge Material (50ml Cartridge Shade Bleach)',
            'Structur 3 Fast Self-Curing Bis-Acrylic Composite for Temp Crowns (50ml Cartridge A2)',
            'Ultrapak Knitted Non-Impregnated Retraction Cord Size #000 Ultra-Fine (244cm)',
            'Ultrapak Knitted Non-Impregnated Retraction Cord Size #00 Extra-Fine (244cm)',
            'Ultrapak Knitted Non-Impregnated Retraction Cord Size #0 Fine (244cm)',
            'Ultrapak Knitted Non-Impregnated Retraction Cord Size #1 Medium (244cm)',
            'Ultrapak Knitted Non-Impregnated Retraction Cord Size #2 Coarse (244cm)',
            'Traxodent Hemodent Retraction Paste with 15% Aluminum Chloride (7x0.7g Syringes)',
            'ViscoStat Clear 25% Aluminum Chloride Hemostatic Gel (4x1.2ml Syringes)',
            'ViscoStat 20% Ferric Sulfate Hemostatic Solution (30ml IndiSpense Syringe)',
            'Gingival Retraction Cord Packer Circular Smooth (Small / Medium)',
            'Gingival Retraction Cord Packer Serrated Offset (Small / Medium)',
            'Impression Trays Plastic Disposable Perforated Full Arch (Small/Med/Large Upper & Lower - 12/Pk)',
            'Impression Trays Stainless Steel Rim-Lock Autoclavable (Set of 6 Upper & Lower)',
            'Triple Tray Dual-Arch Disposable Impression Trays - Posterior (48/Box)',
            'Triple Tray Dual-Arch Disposable Impression Trays - Anterior (35/Box)',
            'Triple Tray Dual-Arch Disposable Impression Trays - Extended Posterior (48/Box)',
            'Automix Impression Mixing Tips 1:1 / 2:1 Ratio Dynamic Yellow (50/Box)',
            'Automix Impression Mixing Tips 1:1 / 2:1 Ratio Dynamic Green (50/Box)',
            'Automix Intraoral Yellow Syringe Tips (100/Box)',
        ];

        foreach ($materials as $mat) {
            $items[] = [
                'name' => $mat,
                'desc' => "Precision prosthodontic impression material, luting cement, or tissue management item.",
            ];
        }

        return $items;
    }

    private static function generateOrthodonticVariations(): array
    {
        $items = [];
        
        // Brackets
        $prescriptions = ['Roth 0.018"', 'Roth 0.022"', 'MBT 0.018"', 'MBT 0.022"', 'Edgewise 0.022"'];
        $bracketTypes = [
            'Metal Twin Mesh-Base Bracket Set 5-5 (20 Brackets/Kit)',
            'Ceramic Aesthetic Translucent Bracket Set 5-5 (20 Brackets/Kit)',
            'Sapphire Pure Monocrystalline Aesthetic Bracket Set 5-5 (20 Brackets/Kit)',
            'Self-Ligating Passive Interactive Bracket Set 5-5 (20 Brackets/Kit)',
            'Gold-Plated Aesthetic Metal Bracket Set 5-5 (20 Brackets/Kit)',
        ];

        foreach ($bracketTypes as $bt) {
            foreach ($prescriptions as $p) {
                $items[] = [
                    'name' => "Orthodontic {$bt} - Prescription {$p}",
                    'desc' => "Precision engineered orthodontic bonding brackets with anatomical compound contour mesh base.",
                ];
            }
        } // 5 * 5 = 25 bracket kits

        // Archwires
        $wireMaterials = [
            'SuperElastic Nickel-Titanium (NiTi) Preformed Archwires Round (10/Pk)',
            'SuperElastic Nickel-Titanium (NiTi) Preformed Archwires Rectangular (10/Pk)',
            'Thermal Active Heat-Activated Copper-NiTi Archwires (10/Pk)',
            'Stainless Steel High-Tensile Orthodontic Archwires Round (10/Pk)',
            'Stainless Steel High-Tensile Orthodontic Archwires Rectangular (10/Pk)',
            'Beta-Titanium (TMA) Molybdenum Alloy Formable Archwires (10/Pk)',
            'Aesthetic Tooth-Colored Coated White NiTi Archwires (10/Pk)',
        ];

        $wireDimensions = [
            '0.012" Upper', '0.012" Lower', '0.014" Upper', '0.014" Lower', '0.016" Upper', '0.016" Lower', '0.018" Upper', '0.018" Lower', '0.020" Upper', '0.020" Lower',
            '0.016x0.016" Upper', '0.016x0.016" Lower', '0.016x0.022" Upper', '0.016x0.022" Lower', '0.017x0.025" Upper', '0.017x0.025" Lower', '0.018x0.025" Upper', '0.018x0.025" Lower', '0.019x0.025" Upper', '0.019x0.025" Lower', '0.021x0.025" Upper', '0.021x0.025" Lower'
        ];

        foreach ($wireMaterials as $wm) {
            foreach ($wireDimensions as $wd) {
                $items[] = [
                    'name' => "Ortho {$wm} - Size {$wd}",
                    'desc' => "High resiliency shape-memory orthodontic archwire for alignment and torque expression.",
                ];
            }
        } // 7 * 22 = 154 archwire items

        // Pliers & Auxiliaries
        $orthoTools = [
            'Weingart Utility Pliers with Serrated Tips (General Wire Placement)',
            'Bird Beak / Adams Pliers #139 (Loop Forming & Wire Bending)',
            'How Pliers Straight Universal Serrated Tips',
            'How Pliers Curved Offset Tips',
            'Distal End Cutter with Safety Hold Mechanism (Cuts up to 0.021x0.025")',
            'Pin & Ligature Wire Cutter Micro-Miniature Hard Wire Tungsten Carbide',
            'Bracket Debonding Pliers Angled with Wide Pad',
            'Direct Bond Bracket Removing Pliers Straight',
            'Posterior Band Removing Pliers Short 3/16" Pad',
            'Cinch Back / Distal Wire Bending Plier',
            'Three-Jaw Wire Bending Plier #200 (Clasp & Arch Adjustment)',
            'Young Loop Forming Pliers 3-Step',
            'Tweed Arch Bending & Loop Forming Plier',
            'Torque Forming Key & Plier Set with 0.018" & 0.022" Slotted Keys',
            'Band Seater Pusher with Serrated Triangular Tip',
            'Molar Band Contouring / Crimping Pliers',
            'Elastic Ligature Ties Module Sticks Assorted Neon Colors (1000/Pk)',
            'Elastic Ligature Ties Module Sticks Clear Aesthetic (1000/Pk)',
            'Elastic Ligature Ties Module Sticks Silver Gray (1000/Pk)',
            'Power Chain Continuous Short Filament Elastic (15 Ft / 4.5m Spool - Clear)',
            'Power Chain Continuous Short Filament Elastic (15 Ft / 4.5m Spool - Grey)',
            'Power Chain Open Long Filament Elastic (15 Ft / 4.5m Spool - Clear)',
            'Intermaxillary Intraoral Elastics 1/8" 3.5oz Fox (50 Bags/Box)',
            'Intermaxillary Intraoral Elastics 3/16" 4.5oz Rabbit (50 Bags/Box)',
            'Intermaxillary Intraoral Elastics 1/4" 4.5oz Kangaroo (50 Bags/Box)',
            'Intermaxillary Intraoral Elastics 5/16" 6.0oz Bear (50 Bags/Box)',
            'Intermaxillary Intraoral Elastics 3/8" 6.0oz Elephant (50 Bags/Box)',
            'NiTi Open Coil Springs 0.010x0.030" Spool (3 Ft / 91cm)',
            'NiTi Closed Coil Springs 0.010x0.030" with Eyelets (10/Pk, 9mm/12mm/15mm)',
            'Molar Separation Rings / Separators Blue Radiopaque (1000/Pk)',
            'Transbond XT Light Cure Orthodontic Bracket Adhesive (2x4g Syringes)',
            'Transbond XT Primer Moisture Tolerant Bottle (6ml)',
            'Transbond Plus Color Change Adhesive Syringes (2x4g)',
            'Ultra Band-Lok Glass Ionomer Blue Band Cement Automix (2x5g Syringes)',
        ];

        foreach ($orthoTools as $ot) {
            $items[] = [
                'name' => $ot,
                'desc' => "Orthodontic clinical instrument, pliers, elastic, or bonding adhesive.",
            ];
        }

        return $items;
    }

    private static function generateImplantVariations(): array
    {
        $items = [];
        $diameters = ['3.0mm Narrow', '3.5mm Regular', '4.0mm Standard', '4.5mm Wide', '5.0mm Wide', '6.0mm Extra-Wide'];
        $lengths = ['8.0mm', '10.0mm', '11.5mm', '13.0mm', '16.0mm'];
        $lines = ['Straumann BLX TorcFit Conical Active Dental Implant', 'Straumann Bone Level Roxolid SLA Dental Implant', 'Nobel Biocare NobelActive TiUltra Conical Connection Implant', 'Osstem TSIII SA Internal Hex Dental Implant'];

        foreach ($lines as $line) {
            foreach ($diameters as $d) {
                foreach ($lengths as $l) {
                    $items[] = [
                        'name' => "{$line} - Ø {$d} x L {$l}",
                        'desc' => "Sterile medical-grade titanium grade 4 / Roxolid endosseous dental implant with micro-roughened hydrophilic surface.",
                    ];
                }
            }
        } // 4 * 6 * 5 = 120 implant items

        $prosthetics = [
            'Healing Abutment Titanium Conical Connection Ø 3.5mm (H: 2.0mm)',
            'Healing Abutment Titanium Conical Connection Ø 3.5mm (H: 3.5mm)',
            'Healing Abutment Titanium Conical Connection Ø 3.5mm (H: 5.0mm)',
            'Healing Abutment Titanium Conical Connection Ø 4.5mm (H: 2.0mm)',
            'Healing Abutment Titanium Conical Connection Ø 4.5mm (H: 3.5mm)',
            'Healing Abutment Titanium Conical Connection Ø 4.5mm (H: 5.0mm)',
            'Healing Abutment PEEK Anatomical Gingival Former Anterior',
            'Healing Abutment PEEK Anatomical Gingival Former Molar',
            'Open-Tray Pick-Up Impression Coping Ø 3.5mm / 4.5mm with Long Guide Screw',
            'Closed-Tray Transfer Impression Coping Ø 3.5mm / 4.5mm with Snap-On Cap',
            'Implant Replica / Analog Stainless Steel Ø 3.5mm',
            'Implant Replica / Analog Stainless Steel Ø 4.5mm',
            'Multi-Unit Abutment Straight 0-Degree (Gingival Height 1.5mm, 2.5mm, 3.5mm)',
            'Multi-Unit Abutment Angled 17-Degree (Gingival Height 2.5mm, 3.5mm)',
            'Multi-Unit Abutment Angled 30-Degree (Gingival Height 3.5mm, 4.5mm)',
            'Titanium Temporary Abutment Engaging Ø 3.5mm / 4.5mm with Screw',
            'Titanium Temporary Abutment Non-Engaging (Bridge) with Screw',
            'UCLA Gold-Castable Abutment Engaging with Titanium Screw',
            'Ti-Base Titanium CAD/CAM Abutment for Zirconia Hybrid Crowns',
            'Implant Prosthetic Torque Wrench 10-40 Ncm Ratchet Arm Autoclavable',
            'Hex Driver 1.25mm / 0.050" Short (10mm) & Long (15mm) for Ratchet',
            'Torx / Star Driver Unigrip Short & Long for Nobel/Straumann Screws',
            'Implant Pilot Drill Ø 2.0mm External Irrigation with Depth Markings',
            'Twist Drill Twist Form Ø 2.8mm, Ø 3.2mm, Ø 3.65mm, Ø 4.2mm, Ø 4.8mm',
            'Countersink Cortical Bone Profiler Drill Ø 3.5mm / 4.5mm',
            'Trephine Bone Coring Drill Internal Ø 3.0mm, 4.0mm, 5.0mm, 6.0mm',
            'Tissue Punch Mucotome Rotary Latch Ø 3.5mm / 4.5mm / 5.5mm',
            'Implant Paralleling Direction Indicator Pins (4/Kit)',
            'Implant Depth Gauge Probe Double-Ended 8-16mm',
            'Sinus Lift Osteotome Kit Straight with Adjustable Depth Stops (5 Osteotomes)',
            'Sinus Lift Osteotome Kit Convex Offset Angled (5 Osteotomes)',
        ];

        foreach ($prosthetics as $p) {
            $items[] = [
                'name' => $p,
                'desc' => "Dental implant surgical or prosthetic component for single tooth or full-arch rehabilitation.",
            ];
        }

        return $items;
    }

    private static function generateAnesthesiaVariations(): array
    {
        $items = [
            ['name' => 'Septocaine (Articaine HCl 4% with Epinephrine 1:100,000) - 50 Cartridges/Box', 'brand' => 'Septodont', 'cost' => 68.00],
            ['name' => 'Septocaine (Articaine HCl 4% with Epinephrine 1:200,000) - 50 Cartridges/Box', 'brand' => 'Septodont', 'cost' => 68.00],
            ['name' => 'Ubistesin Forte (Articaine HCl 4% with Epinephrine 1:100,000) - 50 Cartridges/Box', 'brand' => '3M Oral Care', 'cost' => 64.00],
            ['name' => 'Ubistesin (Articaine HCl 4% with Epinephrine 1:200,000) - 50 Cartridges/Box', 'brand' => '3M Oral Care', 'cost' => 64.00],
            ['name' => 'Xylocaine (Lidocaine HCl 2% with Epinephrine 1:100,000) - 50 Cartridges/Box', 'brand' => 'Dentsply Sirona', 'cost' => 52.00],
            ['name' => 'Xylocaine (Lidocaine HCl 2% with Epinephrine 1:50,000) - 50 Cartridges/Box', 'brand' => 'Dentsply Sirona', 'cost' => 52.00],
            ['name' => 'Lignospan Standard (Lidocaine HCl 2% with Epi 1:100,000) - 50 Cartridges/Box', 'brand' => 'Septodont', 'cost' => 48.00],
            ['name' => 'Scandonest 3% Plain (Mepivacaine HCl 3% Without Vasoconstrictor) - 50/Box', 'brand' => 'Septodont', 'cost' => 54.00],
            ['name' => 'Polocaine 3% (Mepivacaine HCl 3% Plain) - 50 Cartridges/Box', 'brand' => 'Dentsply Sirona', 'cost' => 55.00],
            ['name' => 'Marcaine (Bupivacaine HCl 0.5% with Epinephrine 1:200,000) - 50/Box', 'brand' => 'Septodont', 'cost' => 84.00],
            ['name' => 'Citanest Forte (Prilocaine HCl 4% with Epinephrine 1:200,000) - 50/Box', 'brand' => 'Dentsply Sirona', 'cost' => 58.00],
            ['name' => 'Citanest Plain (Prilocaine HCl 4% Without Epinephrine) - 50/Box', 'brand' => 'Dentsply Sirona', 'cost' => 58.00],
            ['name' => 'Septoject Dental Needles 30G Short 25mm (100/Box)', 'brand' => 'Septodont', 'cost' => 18.50],
            ['name' => 'Septoject Dental Needles 27G Long 35mm (100/Box)', 'brand' => 'Septodont', 'cost' => 18.50],
            ['name' => 'Septoject Dental Needles 27G Short 25mm (100/Box)', 'brand' => 'Septodont', 'cost' => 18.50],
            ['name' => 'Septoject Dental Needles 30G Extra-Short 12mm for Intraligamentary (100/Box)', 'brand' => 'Septodont', 'cost' => 22.00],
            ['name' => 'Septoject Evolution Scalpel-Designed Needles 30G Short (100/Box)', 'brand' => 'Septodont', 'cost' => 26.00],
            ['name' => 'Septoject Evolution Scalpel-Designed Needles 27G Long (100/Box)', 'brand' => 'Septodont', 'cost' => 26.00],
            ['name' => 'HurriCaine Topical Anesthetic Gel 20% Benzocaine Wild Cherry (1 oz / 28g)', 'brand' => 'Beutlich Pharma', 'cost' => 14.50],
            ['name' => 'HurriCaine Topical Anesthetic Gel 20% Benzocaine Fresh Mint (1 oz / 28g)', 'brand' => 'Beutlich Pharma', 'cost' => 14.50],
            ['name' => 'HurriCaine Topical Anesthetic Gel 20% Benzocaine Piña Colada (1 oz / 28g)', 'brand' => 'Beutlich Pharma', 'cost' => 14.50],
            ['name' => 'Oraqix Non-Injectable Periodontal Anesthetic Gel (2.5% Lido / 2.5% Prilo) - 20/Box', 'brand' => 'Dentsply Sirona', 'cost' => 145.00],
            ['name' => 'Aspirating Dental Cartridge Syringe CW-Style Stainless Steel 1.8ml', 'brand' => 'Hu-Friedy', 'cost' => 45.00],
            ['name' => 'Aspirating Dental Cartridge Syringe Wingless European Style 1.8ml', 'brand' => 'Hu-Friedy', 'cost' => 45.00],
            ['name' => 'Citoject Intraligamentary PDL Syringe Pen-Grip Stainless Steel 1.8ml', 'brand' => 'Kulzer', 'cost' => 265.00],
            ['name' => 'Emergency Medical Kit Dental Basic (EpiPen, Nitro, Albuterol, Glucose, Ammonia)', 'brand' => 'HealthFirst', 'cost' => 580.00],
        ];

        return $items;
    }

    private static function generateSterilizationVariations(): array
    {
        $items = [];
        $pouches = [
            'Self-Seal Sterilization Pouches with Internal/External Indicators 2.25" x 4.0" (200/Box)',
            'Self-Seal Sterilization Pouches with Internal/External Indicators 3.5" x 5.25" (200/Box)',
            'Self-Seal Sterilization Pouches with Internal/External Indicators 3.5" x 9.0" (200/Box)',
            'Self-Seal Sterilization Pouches with Internal/External Indicators 5.25" x 10.0" (200/Box)',
            'Self-Seal Sterilization Pouches with Internal/External Indicators 7.5" x 13.0" (200/Box)',
            'Self-Seal Sterilization Pouches Heavy Duty Extra-Wide 12.0" x 18.0" (100/Box)',
            'Autoclave Sterilization Heat-Sealing Tubing Roll 2.0" x 100 Ft',
            'Autoclave Sterilization Heat-Sealing Tubing Roll 3.0" x 100 Ft',
            'Autoclave Sterilization Heat-Sealing Tubing Roll 4.0" x 100 Ft',
            'Autoclave Sterilization Heat-Sealing Tubing Roll 6.0" x 100 Ft',
            'Autoclave Sterilization Heat-Sealing Tubing Roll 8.0" x 100 Ft',
            'Hu-Friedy IMS Sterilization Cassette Wrap Paper 15x15" (1000/Box)',
            'Hu-Friedy IMS Sterilization Cassette Wrap Paper 20x20" (500/Box)',
            'Hu-Friedy IMS Sterilization Cassette Wrap Paper 24x24" (500/Box)',
            'ConFirm 10 Biological Spore Ampoules Geobacillus Stearothermophilus (25/Box)',
            'SteamPlus Class 5 Integrating Chemical Indicators 4.0" (100/Box)',
            'SteriGage Class 5 Chemical Integrator Strips (100/Box)',
            'Multi-Variable Class 4 Chemical Indicator Strips (250/Box)',
            'AirView Bowie-Dick Class B Vacuum Autoclave Daily Test Packs (30/Box)',
            'Autoclave Indicator Tape Steam Sensitive 3/4" x 60 Yards (1 Roll)',
            'CaviWipes Disinfectant Towelettes Large 6x6.75" (160 Wipes/Canister)',
            'CaviWipes Bleach Disinfecting Towelettes (160 Wipes/Canister)',
            'Optim 33TB One-Step Surface Cleaner & Disinfectant Wipes 6x7" (160/Can)',
            'Optim 33TB Surface Cleaner & Intermediate Disinfectant Liquid (1 Gallon)',
            'Enzymax Dual-Enzyme Ultrasonic Instrument Cleaning Tablets (64/Box)',
            'Enzymax Earth Ultrasonic Liquid Cleaner Concentrate (1 Gallon)',
            'ICX Dental Unit Waterline Purification Tablets 0.7L (50/Box)',
            'A-dec ICX Waterline Treatment Solution for 2.0L Bottles (50/Box)',
            'Purevac Evacuation System Cleaner Concentrated Liquid (2 Liters)',
            'Monarch Lines Dental Unit Waterline Shock Treatment Kit',
        ];

        foreach ($pouches as $p) {
            $items[] = [
                'name' => $p,
                'desc' => "Hospital-grade infection control, biological monitoring, or autoclave sterilization product.",
            ];
        }

        return $items;
    }

    private static function generateDiagnosticVariations(): array
    {
        $items = [];
        $sizes = ['#3 (20mm)', '#4 (22mm)', '#5 (24mm)', '#6 (26mm)'];
        $mirrorTypes = [
            'Front Surface Rhodium Coated Mirror Head Cone Socket (12/Box)',
            'High-Definition Ultra-Bright Front Surface Mirror Head (12/Box)',
            'Double-Sided Rhodium Plated Mirror Head Cone Socket (6/Box)',
            'Titanium Coated Scratch-Resistant Front Surface Mirror (12/Box)',
        ];

        foreach ($mirrorTypes as $mt) {
            foreach ($sizes as $sz) {
                $items[] = [
                    'name' => "Mouth Mirror - {$mt} [Size {$sz}]",
                    'desc' => "High reflectivity front surface distortion-free intraoral dental examination mirror.",
                ];
            }
        }

        $diag = [
            'Dental Explorer #23 "Shepherd\'s Crook" Single Ended Cone Socket',
            'Dental Explorer #17 Single Ended Deep Margin Explorer',
            'Dental Explorer #3CH "Cowhorn" Double Ended Interproximal',
            'Dental Explorer #2A / #9 Double Ended Explorer',
            'Endodontic Explorer DG-16 Double Ended Delicate Canal Finder',
            'Micro-Explorer DE #17/23 Combination Explorer',
            'College Dressing Pliers / Cotton Forceps Serrated Tips 6.0"',
            'College Dressing Pliers Locking Mechanism 6.0"',
            'Perry Cotton Pliers Retro-Angled Double Bend 5.5"',
            'Miller Articulating Paper Forceps Straight 6.0"',
            'Miller Articulating Paper Forceps Curved 6.0"',
            'Articulating Paper Bausch 200 Micron Blue (300 Strips/Box)',
            'Articulating Paper Bausch 200 Micron Red (300 Strips/Box)',
            'Articulating Silk Bausch 80 Micron Two-Tone Blue/Red (1 Roll)',
            'AccuFilm II Double-Sided Ultra-Thin Occlusal Film 21 Micron (280/Box)',
            'Shimstock Occlusal Registration Foil 8 Micron Metallic (1 Roll)',
            'Digitest 3 Digital Electric Pulp Vitality Tester Kit with 4 Probes',
            'Microlux 2 Transilluminator Diagnostic LED Light Unit with 2mm & 3mm Light Guides',
            'Intraoral Photographic Mirrors Rhodium Plated Adult Occlusal Form',
            'Intraoral Photographic Mirrors Rhodium Plated Pediatric Occlusal Form',
            'Intraoral Photographic Mirrors Rhodium Plated Buccal / Lingual Form',
            'Contrasters Matte Black Silicone Intraoral Photography Background Set (4 Pcs)',
        ];

        foreach ($diag as $d) {
            $items[] = [
                'name' => $d,
                'desc' => "Clinical diagnostic examination instrument or intraoral photography aid.",
            ];
        }

        return $items;
    }

    private static function generatePediatricVariations(): array
    {
        $items = [];
        $teeth = ['Upper Right D', 'Upper Left D', 'Lower Right D', 'Lower Left D', 'Upper Right E', 'Upper Left E', 'Lower Right E', 'Lower Left E'];
        $sizes = ['Size 2', 'Size 3', 'Size 4', 'Size 5', 'Size 6', 'Size 7'];

        foreach ($teeth as $t) {
            foreach ($sizes as $s) {
                $items[] = [
                    'name' => "3M Stainless Steel Primary Molar Crown - {$t} [{$s}] (5/Box)",
                    'desc' => "Pre-formed pre-crimped stainless steel primary crown for pediatric molar restoration.",
                ];
            }
        } // 8 * 6 = 48 SS crowns

        $pedo = [
            'Strip Crowns Anterior Pediatric Crystal Form Starter Kit (64 Forms/Box)',
            'Vanish 5% Sodium Fluoride White Varnish with TCP - Cherry Flavor (50/Box)',
            'Vanish 5% Sodium Fluoride White Varnish with TCP - Melon Flavor (50/Box)',
            'Vanish 5% Sodium Fluoride White Varnish with TCP - Mint Flavor (50/Box)',
            'Fluoridex Daily Defense 1.1% Neutral Sodium Fluoride Toothpaste (112g)',
            'Buckley\'s Formocresol Pediatric Pulpotomy Solution (30ml Bottle)',
            'Astringedent 15.5% Ferric Sulfate Pulpotomy Hemostatic Liquid (30ml)',
            'Space Maintainers Denovo Pre-Formed Band & Loop Starter Assortment Kit (32 Pcs)',
            'Pediatric Extraction Forceps #150SK Upper Universal Anteriors/Premolars',
            'Pediatric Extraction Forceps #151SK Lower Universal Anteriors/Premolars',
            'Pedi-Wrap Pediatric Patient Immobilizer & Safety Positioning Blanket (Small / Medium)',
        ];

        foreach ($pedo as $p) {
            $items[] = [
                'name' => $p,
                'desc' => "Pediatric dentistry specialty item, primary crown, or fluoride varnish.",
            ];
        }

        return $items;
    }

    private static function generateEquipmentVariations(): array
    {
        return [
            ['name' => 'NSK Ti-Max Z900L High-Speed Air Turbine Handpiece (Optic, 26W Power)', 'cost' => 840.00],
            ['name' => 'NSK Ti-Max Z800L Miniature Head High-Speed Air Turbine (Optic, 23W)', 'cost' => 840.00],
            ['name' => 'NSK S-Max M900L High-Speed Handpiece Standard Head Optic (20W)', 'cost' => 520.00],
            ['name' => 'KaVo MASTERtorque LUX M8900L High-Speed Turbine with Direct Stop (23W)', 'cost' => 1150.00],
            ['name' => 'KaVo EXPERTtorque LUX E680L High-Speed Handpiece (18W)', 'cost' => 820.00],
            ['name' => 'NSK Ti-Max Z95L 1:5 Increasing Speed Electric Contra-Angle Handpiece (Optic)', 'cost' => 1120.00],
            ['name' => 'NSK Ti-Max Z25L 1:1 Direct Drive Electric Contra-Angle Handpiece (Optic)', 'cost' => 890.00],
            ['name' => 'NSK FX25m 1:1 Direct Drive Latch-Type Low-Speed Contra-Angle', 'cost' => 240.00],
            ['name' => 'NSK FX65m 1:1 Straight Low-Speed Handpiece for Lab & Surgical Burs', 'cost' => 240.00],
            ['name' => 'KaVo COMFORTdrive 200 XNL Electric Handpiece System', 'cost' => 1480.00],
            ['name' => 'W&H Synea Vision WK-93 LT 1:5 Optic High-Speed Electric Contra-Angle', 'cost' => 1250.00],
            ['name' => 'NSK Phatelus Quick-Disconnect Coupler with LED (6-Pin)', 'cost' => 260.00],
            ['name' => 'KaVo MULTIflex LUX 465 LRN 6-Pin Coupler with Water Regulation', 'cost' => 295.00],
            ['name' => 'VALO Cordless LED Dental Curing Light Kit (Ultradent Broad-Band)', 'cost' => 1450.00],
            ['name' => 'Elipar DeepCure-S LED Curing Light Stainless Steel (3M Oral Care)', 'cost' => 1350.00],
            ['name' => 'Bluephase PowerCure Polywave 3-Second High-Intensity LED Light (Ivoclar)', 'cost' => 1620.00],
            ['name' => 'Woodpecker Ai-Ray Portable Handheld Dental X-Ray Unit (70kV / 2mA)', 'cost' => 1750.00],
            ['name' => 'Root ZX II Electronic Apex Locator High-Precision Multi-Frequency (J. Morita)', 'cost' => 980.00],
            ['name' => 'X-Smart Plus Cordless Endodontic Motor with Reciprocating Motion (Dentsply)', 'cost' => 1650.00],
            ['name' => 'Calamus Dual Warm Vertical Obturation Pack & Flow System (Dentsply)', 'cost' => 2100.00],
        ];
    }

    private static function generateLabCADVariations(): array
    {
        $items = [];
        $blocks = [
            'IPS e.max CAD Lithium Disilicate Glass-Ceramic Blocks C14 (Shade A1, A2, A3, A3.5, B1, HT/LT - 5/Box)',
            'IPS e.max ZirCAD Prime Multi-Layered All-Ceramic Zirconia Disc Ø 98.5mm (Thickness: 14mm, 16mm, 18mm, 20mm)',
            'Katana Zirconia HTML Plus Multi-Layered Dental Zirconia Blank Ø 98.5x18mm (Shade A2)',
            'Katana Zirconia HTML Plus Multi-Layered Dental Zirconia Blank Ø 98.5x14mm (Shade A1)',
            'Telio CAD for CEREC / inLab PMMA Monolithic Aesthetic Temp Blocks B40L (3/Box)',
            'Celtra Duo Zirconia-Reinforced Lithium Silicate Blocks for CEREC (4/Box - Shade A2 HT)',
            'Vita Enamic Hybrid Dental Ceramic CAD/CAM Blocks EM-14 (5/Box - Shade 2M2 HT)',
            'Lava Ultimate CAD/CAM Restorative Blocks for CEREC (5/Box - Shade A2 LT)',
            'Diamond Milling Burs for Sirona CEREC Primemill / MC XL (Step Bur 12S, Shaper 25 RZ - 6/Pk)',
            'Carbide Milling Burs for Roland DWX-52D Dental Mill (0.6mm, 1.0mm, 2.0mm)',
            'IPS e.max CAD Crystall./Glaze Paste Spray (270ml Canister)',
            'IPS e.max CAD Crystall./Glaze Paste (3g Jar + Liquid)',
            'SpeedPaste Refractory Firing Paste for All-Ceramic Firing Pegs (12g Syringe)',
            'Ivostrip CAD/CAM Zirconia Diamond Finishing Strips (6/Pk)',
        ];

        foreach ($blocks as $b) {
            $items[] = [
                'name' => $b,
                'desc' => "Digital laboratory dental CAD/CAM milling block, disc, or crystallization glaze.",
            ];
        }

        return $items;
    }

    private static function generatePPEAndConsumablesVariations(): array
    {
        $items = [];
        $sizes = ['Extra Small (XS)', 'Small (S)', 'Medium (M)', 'Large (L)', 'Extra Large (XL)'];
        $gloves = [
            'Cranberry Evolve 300 Nitrile Examination Gloves Medical-Grade Cobalt Blue (300/Box)',
            'Halyard Sterling Nitrile Exam Gloves Textured Fingertips Silver (200/Box)',
            'Microflex Ultraform Powder-Free Nitrile Examination Gloves Soft Blue (300/Box)',
            'Aurelia Sonic 100 Powder-Free Indigo Nitrile Exam Gloves (100/Box)',
            'Ansell Micro-Touch Denta-Glove Powder-Free Natural Rubber Latex (100/Box)',
        ];

        foreach ($gloves as $g) {
            foreach ($sizes as $s) {
                $items[] = [
                    'name' => "{$g} - Size {$s}",
                    'desc' => "Medical examination gloves with tactile sensitivity and chemical resistance.",
                ];
            }
        } // 5 * 5 = 25 glove items

        $ppe = [
            'ASTM Level 3 Fluid Resistant Surgical Face Masks with Ear Loops - Blue (50/Box)',
            'ASTM Level 3 Fluid Resistant Surgical Face Masks with Ear Loops - Lavender (50/Box)',
            'ASTM Level 3 Anti-Fog Face Masks with Foam Strip & Shield Splash Visor (25/Box)',
            'N95 NIOSH-Approved Particulate Surgical Respirator Cone Masks (20/Box)',
            'Disposable Dental Patient Bibs 3-Ply 2-Paper 1-Polymer Blue 13x18" (500/Case)',
            'Disposable Dental Patient Bibs 3-Ply 2-Paper 1-Polymer Lavender 13x18" (500/Case)',
            'Disposable Dental Patient Bibs 3-Ply 2-Paper 1-Polymer Green 13x18" (500/Case)',
            'Disposable Dental Patient Bibs 3-Ply 2-Paper 1-Polymer Charcoal Grey (500/Case)',
            'Premium Dental Saliva Ejectors with Soft Non-Removable Wire Reinforced Tip - Clear (1000/Case)',
            'Premium Dental Saliva Ejectors with Soft Non-Removable Tip - Assorted Colors (1000/Case)',
            'High-Volume Evacuation (HVE) Suction Tips Vented / Non-Vented Combo (100/Pack)',
            'Surgical Aspirator Suction Tips 2.5mm Autoclavable Fine Bore Green (25/Pk)',
            'Surgical Aspirator Suction Tips 4.0mm Autoclavable Medium Bore White (25/Pk)',
            'Non-Sterile Cotton Rolls #2 Medium 1.5" Braided 100% Pure Cotton (2000/Box)',
            'Dry-Angles Saliva Control Parotid Gland Moisture Absorption Pads Small (50/Box)',
            'Dry-Angles Saliva Control Parotid Gland Moisture Absorption Pads Large (50/Box)',
            'Plastic Barrier Film Roll with Perforated Sheets 4"x6" (1200 Sheets/Roll - Blue)',
            'Plastic Barrier Film Roll with Perforated Sheets 4"x6" (1200 Sheets/Roll - Clear)',
            'Syringe Sleeve Barriers for 3-Way Air/Water Syringe with Opening (500/Box)',
            'Digital X-Ray Sensor Barrier Sleeves Custom Fit Size #1 / #2 (500/Box)',
            'T-Style Light Handle Barrier Sleeves Disposable (500/Box)',
            'Full Dental Chair Barrier Poly Cover Sleeves 29"x80" (125/Roll)',
            'Prophy Paste Medium Grit with Fluoride - Mint Flavor (200 Unit Cups/Box)',
            'Prophy Paste Coarse Grit with Fluoride - Cherry Flavor (200 Unit Cups/Box)',
            'Disposable Prophy Angles 90-Degree Soft Cup Latex-Free (100/Box)',
            'Disposable Prophy Angles Contra-Angle Soft Cup Latex-Free (100/Box)',
        ];

        foreach ($ppe as $p) {
            $items[] = [
                'name' => $p,
                'desc' => "Essential daily dental hygiene, PPE, saliva evacuation, or chair barrier consumable.",
            ];
        }

        return $items;
    }

    private static function generateRareAndSpecialtyVariations(): array
    {
        return [
            ['name' => 'Sinus Lift Lateral Window DASK Kit with Diamond Coated Sinus Drills', 'brand' => 'Dentium Surgical', 'cost' => 780.00],
            ['name' => 'Sinus Membrane Elevation Curettes Set of 5 Double-Ended Titanium', 'brand' => 'Hu-Friedy', 'cost' => 340.00],
            ['name' => 'Chao Pinhole Surgical Technique PST Tunneling Instruments Set (6 Pcs)', 'brand' => 'Chao Pinhole Academy', 'cost' => 1250.00],
            ['name' => 'Allen Microsurgical Tunneling Elevators & Papilla Preservers (4 Pcs)', 'brand' => 'Hu-Friedy', 'cost' => 495.00],
            ['name' => 'SafeScraper Twist Curve Autologous Bone Harvester Disposable (3/Box)', 'brand' => 'Meta Dental', 'cost' => 185.00],
            ['name' => 'Micross Minimally Invasive Bone Collector Harvester (3/Box)', 'brand' => 'Meta Dental', 'cost' => 210.00],
            ['name' => 'Piezosurgery Ultrasonic Bone Surgery Saw Inserts Kit (OT7, OT7S, OT8L, OT8R)', 'brand' => 'Mectron Medical', 'cost' => 640.00],
            ['name' => 'Piezosurgery Ultrasonic Sinus Elevation Inserts Kit (SL1, SL2, SL3, SL4)', 'brand' => 'Mectron Medical', 'cost' => 590.00],
            ['name' => 'Automatic Pneumatic Crown & Bridge Remover Kit with 3 Wire Loops & 5 Tips', 'brand' => 'Medesy', 'cost' => 380.00],
            ['name' => 'WAMkey Crown Remover Set of 3 Keys (Size 1, 2, 3) for Damage-Free Removal', 'brand' => 'WAM Dental', 'cost' => 395.00],
            ['name' => 'Castroviejo Ophthalmic-Style Micro-Corneal Scissors Curved Ultra-Sharp', 'brand' => 'Storz Ophthalmic', 'cost' => 280.00],
            ['name' => 'Benex Root Extraction System Vertical Atraumatic Extraction Kit', 'brand' => 'Helmut Zepf', 'cost' => 1890.00],
            ['name' => 'Salvin Osseous Coagulum Trap Suction Inline Filter System (12/Box)', 'brand' => 'Salvin Dental', 'cost' => 165.00],
            ['name' => 'Bone Graft Syringe Applicator 2.5mm / 3.5mm / 4.5mm Stainless Steel', 'brand' => 'Hu-Friedy', 'cost' => 85.00],
            ['name' => 'Buser Periosteal Elevator 3mm Sharp Chisel / Round End Delicate', 'brand' => 'Hu-Friedy', 'cost' => 92.00],
            ['name' => 'Tunneling Instrument TKN1 / TKN2 for Subepithelial Connective Tissue Grafts', 'brand' => 'Hu-Friedy', 'cost' => 145.00],
            ['name' => 'Khoury Bone Grafting Bone Collector Plate & Micro-Screws Fixation Set', 'brand' => 'Stoma Instruments', 'cost' => 1420.00],
        ];
    }
}
