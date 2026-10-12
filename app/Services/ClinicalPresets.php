<?php

namespace App\Services;

class ClinicalPresets
{
    /**
     * Local Anesthetic agents commonly used in dentistry.
     */
    public static function anestheticAgents(): array
    {
        return [
            'Articaine 4% (1:100,000 Epinephrine)' => 'Articaine 4% (1:100,000 Epinephrine) - Septanest / Ubistesin',
            'Lidocaine 2% (1:100,000 Epinephrine)' => 'Lidocaine 2% (1:100,000 Epinephrine) - Xylocaine',
            'Mepivacaine 3% Plain (No Epinephrine)' => 'Mepivacaine 3% Plain (No Epi - Cardiac Safe)',
            'Bupivacaine 0.5% (1:200,000 Epinephrine)' => 'Bupivacaine 0.5% (1:200,000 Epinephrine) - Long Duration',
            'Prilocaine 4% (1:200,000 Epinephrine)' => 'Prilocaine 4% (1:200,000 Epinephrine) - Citanest',
        ];
    }

    /**
     * Anesthetic carpule dosage amounts.
     */
    public static function anestheticDosages(): array
    {
        return [
            '0.5 carpule (0.9 ml)' => '0.5 carpule (0.9 ml)',
            '1.0 carpule (1.8 ml)' => '1.0 carpule (1.8 ml)',
            '1.5 carpules (2.7 ml)' => '1.5 carpules (2.7 ml)',
            '2.0 carpules (3.6 ml)' => '2.0 carpules (3.6 ml)',
            '2.5 carpules (4.5 ml)' => '2.5 carpules (4.5 ml)',
            '3.0 carpules (5.4 ml)' => '3.0 carpules (5.4 ml)',
        ];
    }

    /**
     * Local anesthetic injection techniques.
     */
    public static function anestheticTechniques(): array
    {
        return [
            'Infiltration (Buccal/Labial)' => 'Infiltration (Buccal/Labial)',
            'Inferior Alveolar Nerve Block (IANB)' => 'Inferior Alveolar Nerve Block (IANB)',
            'Mental / Incisive Nerve Block' => 'Mental / Incisive Nerve Block',
            'Greater Palatine Nerve Block' => 'Greater Palatine Nerve Block',
            'Nasopalatine Nerve Block' => 'Nasopalatine Nerve Block',
            'Intraligamentary / PDL Injection' => 'Intraligamentary / PDL Injection',
            'Intrapulpal Injection' => 'Intrapulpal Injection',
            'Topical Gel Anesthetic Only' => 'Topical Gel Anesthetic Only',
        ];
    }

    /**
     * Common dental tools and chairside equipment suggestions.
     */
    public static function toolsAndEquipment(): array
    {
        return [
            'Rubber Dam (Medium/Heavy)',
            'Cotton Rolls & High Suction',
            'Apex Locator',
            'Endo Rotary Motor',
            'Ultrasonic Scaler (Cavitron)',
            'Electric Surgical Handpiece',
            'High-Speed Turbine',
            'Diode Laser (1.5W)',
            'Curing Light (High Intensity)',
            'Implant Torque Wrench (35 Ncm)',
            'Surgical Elevator / Periotome',
            'Matrix Band & Wedge System',
        ];
    }

    /**
     * Common clinical materials and consumables.
     */
    public static function clinicalMaterials(): array
    {
        return [
            // Restorative
            'Composite Shade A1',
            'Composite Shade A2',
            'Composite Shade A3',
            'Composite Shade A3.5',
            'Composite Shade B1',
            'Composite Bleach Shade',
            'Universal Bonding Agent (8th Gen)',
            'Flowable Resin',
            'Glass Ionomer Cement (Fuji IX)',
            'Phosphoric Acid Etchant 37%',
            // Endodontic
            'ProTaper Gold F1/F2 Files',
            'WaveOne Gold Primary Files',
            'Bioceramic Root Canal Sealer',
            'AH Plus Resin Sealer',
            'Sodium Hypochlorite 2.5% Irrigation',
            'EDTA 17% Irrigation',
            'Gutta-Percha Cones 25/.04',
            // Surgical / Periodontal
            'Suture 3-0 Silk',
            'Suture 4-0 Vicryl (Resorbable)',
            'Suture 5-0 Monofilament',
            'Surgical Blade #15',
            'Surgical Blade #15C',
            'Bio-Oss Bone Graft (0.5g)',
            'Bio-Gide Collagen Membrane',
            'Hemostatic Sponge (Surgicel)',
        ];
    }

    /**
     * Diabetes Mellitus classification.
     */
    public static function diabetesTypes(): array
    {
        return [
            'type_1' => 'Type 1 (Insulin-dependent)',
            'type_2' => 'Type 2 (Adult-onset)',
            'gestational' => 'Gestational Diabetes',
            'pre_diabetic' => 'Pre-diabetic / Borderline',
        ];
    }

    /**
     * Glycemic control / HbA1c levels with clinical risk guidance.
     */
    public static function hba1cLevels(): array
    {
        return [
            '<6.5%' => '< 6.5% (Well-controlled — Safe for surgery/implants)',
            '6.5-7.5%' => '6.5% – 7.5% (Controlled — Safe for standard dental care)',
            '7.6-8.5%' => '7.6% – 8.5% (Moderate risk — Delayed healing risk, caution)',
            '>8.5%' => '> 8.5% (Uncontrolled / High risk — Defer elective surgery)',
        ];
    }

    /**
     * Blood pressure stages (AHA classification).
     */
    public static function bloodPressureStages(): array
    {
        return [
            'normal' => 'Normal (< 120/80 mmHg)',
            'elevated' => 'Elevated (120-129 / <80 mmHg)',
            'stage_1' => 'Stage 1 Hypertension (130-139 / 80-89 mmHg)',
            'stage_2' => 'Stage 2 Hypertension (≥ 140 / ≥ 90 mmHg)',
            'crisis' => 'Hypertensive Crisis (> 180 / > 120 mmHg — Urgent)',
        ];
    }

    /**
     * Anticoagulants and antiplatelet drugs.
     */
    public static function anticoagulants(): array
    {
        return [
            'none' => 'None',
            'aspirin' => 'Aspirin (Baby Aspirin 75/81/100mg)',
            'plavix' => 'Plavix (Clopidogrel 75mg)',
            'warfarin' => 'Warfarin / Marevan (Coumadin - Monitor INR)',
            'eliquis' => 'Eliquis (Apixaban)',
            'xarelto' => 'Xarelto (Rivaroxaban)',
            'clexane' => 'Clexane / Heparin',
            'other' => 'Other Blood Thinner',
        ];
    }

    /**
     * Common cardiac conditions relevant to dental care.
     */
    public static function cardiacConditions(): array
    {
        return [
            'Pacemaker / AICD (Caution with ultrasonic scaler)',
            'Artificial / Prosthetic Heart Valve (Prophylaxis required)',
            'History of Infective Endocarditis',
            'Coronary Stent / Bypass (CABG)',
            'Angina Pectoris / Ischemic Heart Disease',
            'Congestive Heart Failure',
            'Heart Murmur',
        ];
    }

    /**
     * Common diabetic medications.
     */
    public static function diabetesMedications(): array
    {
        return [
            'Metformin (Glucophage)',
            'Insulin (Lantus / Novorapid / Humalog)',
            'Glimepiride (Amaryl)',
            'Jardiance (Empagliflozin)',
            'Januvia (Sitagliptin)',
            'Diet & Exercise Only',
        ];
    }

    /**
     * Dental anxiety levels.
     */
    public static function anxietyLevels(): array
    {
        return [
            'low' => 'Low / Relaxed',
            'moderate' => 'Moderate (Slight apprehension)',
            'severe_phobia' => 'Severe (Dental Phobia / High stress)',
        ];
    }

    /**
     * Common external dental treatment types.
     */
    public static function externalTreatmentTypes(): array
    {
        return [
            'Root Canal Treatment' => 'Root Canal Treatment',
            'Dental Implant Placement' => 'Dental Implant Placement',
            'Porcelain / Zirconia Crown' => 'Porcelain / Zirconia Crown',
            'Fixed Dental Bridge' => 'Fixed Dental Bridge',
            'Surgical Tooth Extraction' => 'Surgical Tooth Extraction',
            'Orthodontic Treatment (Braces/Aligners)' => 'Orthodontic Treatment (Braces/Aligners)',
            'Periodontal Flap / Gum Surgery' => 'Periodontal Flap / Gum Surgery',
            'Bone Grafting / Sinus Lift' => 'Bone Grafting / Sinus Lift',
            'Composite Restorations' => 'Composite Restorations',
            'Other Medical / Dental Surgery' => 'Other Medical / Dental Surgery',
        ];
    }
}
