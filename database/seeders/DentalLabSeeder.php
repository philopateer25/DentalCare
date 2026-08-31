<?php

namespace Database\Seeders;

use App\Models\DentalLab;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DentalLabSeeder extends Seeder
{
    public function run(): void
    {
        $practice = Practice::firstOrCreate(
            ['name' => 'Main Dental Clinic'],
            ['currency' => 'USD', 'timezone' => 'UTC', 'is_active' => true]
        );

        $doctor = User::firstOrCreate(
            ['email' => 'doctor@dentalcare.com'],
            ['name' => 'Dr. Sarah Jenkins DDS', 'password' => bcrypt('password')]
        );

        // Ensure Patients exist
        $patients = [
            ['first_name' => 'James', 'last_name' => 'Wilson', 'file_number' => 'DC-2026-001', 'phone' => '+1 (555) 234-5678', 'email' => 'james.wilson@example.com'],
            ['first_name' => 'Emily', 'last_name' => 'Rodriguez', 'file_number' => 'DC-2026-002', 'phone' => '+1 (555) 345-6789', 'email' => 'emily.r@example.com'],
            ['first_name' => 'Michael', 'last_name' => 'Chang', 'file_number' => 'DC-2026-003', 'phone' => '+1 (555) 456-7890', 'email' => 'm.chang@example.com'],
            ['first_name' => 'Sophia', 'last_name' => 'Martinez', 'file_number' => 'DC-2026-004', 'phone' => '+1 (555) 567-8901', 'email' => 'sophia.m@example.com'],
            ['first_name' => 'David', 'last_name' => 'Thompson', 'file_number' => 'DC-2026-005', 'phone' => '+1 (555) 678-9012', 'email' => 'dthompson@example.com'],
            ['first_name' => 'Olivia', 'last_name' => 'Anderson', 'file_number' => 'DC-2026-006', 'phone' => '+1 (555) 789-0123', 'email' => 'olivia.a@example.com'],
            ['first_name' => 'Robert', 'last_name' => 'Taylor', 'file_number' => 'DC-2026-007', 'phone' => '+1 (555) 890-1234', 'email' => 'robert.t@example.com'],
            ['first_name' => 'Emma', 'last_name' => 'White', 'file_number' => 'DC-2026-008', 'phone' => '+1 (555) 901-2345', 'email' => 'emma.w@example.com'],
        ];

        $patientModels = [];
        foreach ($patients as $p) {
            $patientModels[] = Patient::firstOrCreate(
                ['file_number' => $p['file_number']],
                array_merge($p, ['practice_id' => $practice->id, 'gender' => 'female', 'dob' => '1990-05-15'])
            );
        }

        // Labs
        $labs = [
            [
                'name' => 'Glidewell Dental Laboratories',
                'lab_type' => 'Commercial Lab',
                'account_number' => 'GLIDE-89402',
                'contact_person' => 'Mark Sullivan (Master CDT)',
                'phone' => '+1 (800) 854-7256',
                'email' => 'cases@glidewelldental.com',
                'portal_url' => 'https://glidewell.io/cases/portal',
                'address' => '4141 MacArthur Blvd, Newport Beach, CA 92660',
                'standard_turnaround_days' => 5,
                'rating' => 4.9,
                'pricing_tier' => 'Standard',
                'courier_service' => 'Lab Courier (Daily 3PM Pickup)',
            ],
            [
                'name' => 'MicroDental Master Aesthetic Lab',
                'lab_type' => 'Crown & Bridge Specialist',
                'account_number' => 'MICRO-33109',
                'contact_person' => 'Jean-Luc Dubois (Aesthetic Ceramist)',
                'phone' => '+1 (800) 229-0936',
                'email' => 'cosmetics@microdental.com',
                'portal_url' => 'https://portal.microdental.com',
                'address' => '6800 Santa Teresa Blvd, San Jose, CA 95119',
                'standard_turnaround_days' => 8,
                'rating' => 5.0,
                'pricing_tier' => 'Premium',
                'courier_service' => 'FedEx Priority Overnight',
            ],
            [
                'name' => 'In-House CAD/CAM Milling Center',
                'lab_type' => 'In-House CAD/CAM',
                'account_number' => 'IN-HOUSE-01',
                'contact_person' => 'Clinic CAD/CAM Specialist',
                'phone' => '+1 (555) 100-2000',
                'email' => 'inhouse.lab@dentalcare.com',
                'portal_url' => 'https://cerec.local/inbox',
                'address' => 'In-Clinic Digital Lab Suite, 2nd Floor',
                'standard_turnaround_days' => 1,
                'rating' => 5.0,
                'pricing_tier' => 'Economy',
                'courier_service' => 'Direct Hand Delivery',
            ],
            [
                'name' => 'Modern Dental Prosthetics & Implants',
                'lab_type' => 'Implant & Surgical Center',
                'account_number' => 'MOD-77412',
                'contact_person' => 'Dmitri Volkov',
                'phone' => '+1 (877) 711-8778',
                'email' => 'implants@moderndentalusa.com',
                'portal_url' => 'https://portal.moderndentalusa.com',
                'address' => '1065 Austin Pkwy, Troy, MI 48083',
                'standard_turnaround_days' => 7,
                'rating' => 4.8,
                'pricing_tier' => 'Standard',
                'courier_service' => 'UPS 2nd Day Air',
            ],
            [
                'name' => 'ClearOrtho 3D & Digital Aligners',
                'lab_type' => 'Clear Aligners & Ortho',
                'account_number' => 'ALIGN-55201',
                'contact_person' => 'Rachel Zhang',
                'phone' => '+1 (800) 441-2099',
                'email' => 'orthorx@clearortholab.com',
                'portal_url' => 'https://alignerhub.clearortholab.com',
                'address' => '500 Technology Dr, Austin, TX 78701',
                'standard_turnaround_days' => 10,
                'rating' => 4.9,
                'pricing_tier' => 'Standard',
                'courier_service' => 'FedEx Express',
            ],
        ];

        $labModels = [];
        foreach ($labs as $lab) {
            $labModels[] = DentalLab::firstOrCreate(
                ['name' => $lab['name']],
                array_merge($lab, ['practice_id' => $practice->id, 'is_active' => true])
            );
        }

        // Lab Cases Data
        $orders = [
            [
                'tracking_number' => 'LAB-2026-00101',
                'patient_id' => $patientModels[0]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[0]->id,
                'order_type' => 'Single Crown',
                'teeth_fdi' => '16',
                'material' => 'High-Translucency Multilayer Zirconia (Katana/3D Pro)',
                'shade' => 'A2',
                'stump_shade' => 'ND2',
                'translucency' => 'Medium Translucency (Natural)',
                'surface_texture' => 'Natural Satin Enamel Texture',
                'occlusal_staining' => 'Light Brown',
                'margin_design' => 'Deep Chamfer',
                'impression_type' => 'digital_scan',
                'digital_scan_url' => 'https://dropbox.com/s/dentelcare_scans/patient_001_tooth_16.stl',
                'instructions' => 'Anatomical occlusal anatomy with light contact points. High polish on gingival margin.',
                'cost' => 125.00,
                'patient_charge' => 950.00,
                'lab_invoice_number' => 'INV-GL-44910',
                'payment_status' => 'paid',
                'status' => 'received_at_clinic',
                'qc_passed' => true,
                'lab_box_number' => 'Lab Box #04',
                'sent_at' => Carbon::now()->subDays(6),
                'expected_delivery_at' => Carbon::now()->subDays(1),
                'delivered_at' => Carbon::now()->subDay(),
                'fitting_date' => Carbon::now()->addDays(2),
            ],
            [
                'tracking_number' => 'LAB-2026-00102',
                'patient_id' => $patientModels[1]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[1]->id,
                'order_type' => 'Veneer',
                'teeth_fdi' => '13, 12, 11, 21, 22, 23',
                'material' => 'IPS e.max CAD Lithium Disilicate Glass-Ceramic',
                'shade' => 'OM2',
                'stump_shade' => 'ND1',
                'translucency' => 'High Translucency (Aesthetic Anterior)',
                'surface_texture' => 'Natural Satin Enamel Texture',
                'occlusal_staining' => 'None',
                'margin_design' => 'Shoulder 360 Porcelain Butt',
                'impression_type' => 'digital_scan',
                'digital_scan_url' => 'https://dropbox.com/s/dentelcare_scans/patient_002_smile_design_veneers.ply',
                'instructions' => 'Master aesthetic 6-unit anterior smile makeover. Emphasize developmental lobes, mamelons, and incisal halo.',
                'cost' => 1140.00,
                'patient_charge' => 7200.00,
                'lab_invoice_number' => 'INV-MD-88192',
                'payment_status' => 'invoiced',
                'status' => 'in_production',
                'qc_passed' => false,
                'lab_box_number' => null,
                'sent_at' => Carbon::now()->subDays(3),
                'expected_delivery_at' => Carbon::now()->addDays(5),
                'delivered_at' => null,
                'fitting_date' => Carbon::now()->addDays(7),
            ],
            [
                'tracking_number' => 'LAB-2026-00103',
                'patient_id' => $patientModels[2]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[3]->id,
                'order_type' => 'Implant Custom Abutment & Crown',
                'teeth_fdi' => '21',
                'material' => 'High-Translucency Multilayer Zirconia (Katana/3D Pro)',
                'shade' => 'A1',
                'stump_shade' => 'ND9',
                'translucency' => 'High Translucency (Aesthetic Anterior)',
                'surface_texture' => 'Natural Satin Enamel Texture',
                'occlusal_staining' => 'None',
                'margin_design' => 'Subgingival 0.5mm',
                'impression_type' => 'digital_scan',
                'digital_scan_url' => 'https://meditlink.com/cases/straumann_blx_21_implant.stl',
                'instructions' => 'Custom titanium milled abutment with concave emergence profile. Screw-retained monolithic zirconia crown with lingual access hole.',
                'cost' => 380.00,
                'patient_charge' => 2400.00,
                'lab_invoice_number' => 'INV-MOD-10394',
                'payment_status' => 'invoiced',
                'status' => 'received_at_clinic',
                'qc_passed' => true,
                'lab_box_number' => 'Lab Box #12 (Implant Safe)',
                'sent_at' => Carbon::now()->subDays(7),
                'expected_delivery_at' => Carbon::now()->subDays(2),
                'delivered_at' => Carbon::now()->subDays(2),
                'fitting_date' => Carbon::now()->addDay(),
            ],
            [
                'tracking_number' => 'LAB-2026-00104',
                'patient_id' => $patientModels[3]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[0]->id,
                'order_type' => 'Bridge',
                'teeth_fdi' => '45, 46, 47',
                'material' => 'Monolithic BruxZir Zirconia (1200 MPa)',
                'shade' => 'A3',
                'stump_shade' => 'ND3',
                'translucency' => 'Medium Translucency (Natural)',
                'surface_texture' => 'High Gloss Glaze',
                'occlusal_staining' => 'Light Brown',
                'margin_design' => 'Deep Chamfer',
                'impression_type' => 'physical_pvs',
                'digital_scan_url' => null,
                'instructions' => '3-unit posterior bridge (pontic #46). Broad connector areas (9mm2) for heavy bruxist.',
                'cost' => 345.00,
                'patient_charge' => 2800.00,
                'lab_invoice_number' => 'INV-GL-45120',
                'payment_status' => 'pending',
                'status' => 'shipped_by_lab',
                'qc_passed' => false,
                'lab_box_number' => null,
                'sent_at' => Carbon::now()->subDays(8),
                'expected_delivery_at' => Carbon::now()->subDays(1), // Overdue alert!
                'delivered_at' => null,
                'fitting_date' => Carbon::now()->addDays(3),
            ],
            [
                'tracking_number' => 'LAB-2026-00105',
                'patient_id' => $patientModels[4]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[4]->id,
                'order_type' => 'Clear Aligners',
                'teeth_fdi' => 'Full Arch (Upper & Lower)',
                'material' => 'Bio-Compatible 3D Print Resin Class IIa',
                'shade' => 'Clear Aesthetic',
                'stump_shade' => null,
                'translucency' => 'High Translucency (Aesthetic Anterior)',
                'surface_texture' => 'High Gloss Glaze',
                'occlusal_staining' => 'None',
                'margin_design' => 'Scalloped Gingival Margin',
                'impression_type' => 'digital_scan',
                'digital_scan_url' => 'https://alignerhub.clearortholab.com/cases/thompson_ortho.stl',
                'instructions' => '20-stage upper and lower clear aligner treatment for moderate crowding and Class I canine correction.',
                'cost' => 750.00,
                'patient_charge' => 4500.00,
                'lab_invoice_number' => 'INV-ALIGN-2938',
                'payment_status' => 'paid',
                'status' => 'in_production',
                'qc_passed' => false,
                'lab_box_number' => null,
                'sent_at' => Carbon::now()->subDays(4),
                'expected_delivery_at' => Carbon::now()->addDays(6),
                'delivered_at' => null,
                'fitting_date' => Carbon::now()->addDays(8),
            ],
            [
                'tracking_number' => 'LAB-2026-00106',
                'patient_id' => $patientModels[5]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[2]->id,
                'order_type' => 'Nightguard / Splint',
                'teeth_fdi' => 'Upper Arch',
                'material' => 'Hard/Soft Dual-Laminate Polyurethane',
                'shade' => 'Clear',
                'stump_shade' => null,
                'translucency' => 'High Translucency (Aesthetic Anterior)',
                'surface_texture' => 'High Gloss Glaze',
                'occlusal_staining' => 'None',
                'margin_design' => 'Full Coverage Flat Plane',
                'impression_type' => 'digital_scan',
                'digital_scan_url' => 'https://cerec.local/inbox/scans/anderson_nightguard.stl',
                'instructions' => 'In-house milled 3mm dual-layer nightguard with anterior ramp disclusion for nocturnal bruxism.',
                'cost' => 45.00,
                'patient_charge' => 550.00,
                'lab_invoice_number' => 'IN-HOUSE-004',
                'payment_status' => 'paid',
                'status' => 'received_at_clinic',
                'qc_passed' => true,
                'lab_box_number' => 'Lab Box #02',
                'sent_at' => Carbon::now()->subDays(1),
                'expected_delivery_at' => Carbon::now(),
                'delivered_at' => Carbon::now(),
                'fitting_date' => Carbon::now()->addDays(2),
            ],
            [
                'tracking_number' => 'LAB-2026-00107',
                'patient_id' => $patientModels[6]->id,
                'doctor_id' => $doctor->id,
                'dental_lab_id' => $labModels[0]->id,
                'order_type' => 'Single Crown',
                'teeth_fdi' => '24',
                'material' => 'IPS e.max CAD Lithium Disilicate Glass-Ceramic',
                'shade' => 'B2',
                'stump_shade' => 'ND3',
                'translucency' => 'Medium Translucency (Natural)',
                'surface_texture' => 'Natural Satin Enamel Texture',
                'occlusal_staining' => 'Light Brown',
                'margin_design' => 'Deep Chamfer',
                'impression_type' => 'digital_scan',
                'digital_scan_url' => 'https://dropbox.com/s/dentelcare_scans/taylor_crown_24.stl',
                'instructions' => 'Redo: previous crown had distal margin gap of 150 microns. Remake with new scan provided.',
                'cost' => 0.00,
                'patient_charge' => 950.00,
                'lab_invoice_number' => 'INV-REDO-019',
                'payment_status' => 'warranty_covered',
                'status' => 'returned_for_redo',
                'redo_reason' => 'Distal margin gap on try-in (Lab Warranty Remake)',
                'redo_count' => 1,
                'warranty_years' => 5,
                'qc_passed' => false,
                'lab_box_number' => null,
                'sent_at' => Carbon::now()->subDays(2),
                'expected_delivery_at' => Carbon::now()->addDays(4),
                'delivered_at' => null,
                'fitting_date' => Carbon::now()->addDays(5),
            ],
        ];

        foreach ($orders as $order) {
            LabOrder::firstOrCreate(
                ['tracking_number' => $order['tracking_number']],
                array_merge($order, ['practice_id' => $practice->id])
            );
        }
    }
}
