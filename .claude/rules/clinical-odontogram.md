---
paths:
  - "app/Models/PatientTooth.php"
  - "app/Models/ToothFinding.php"
  - "app/Models/DentalExamination.php"
  - "app/Models/Treatment*.php"
  - "app/Models/Procedure*.php"
  - "app/Http/Controllers/*Odontogram*.php"
  - "app/Http/Controllers/*Examination*.php"
  - "app/Services/*Clinical*.php"
  - "resources/js/Components/Dental3D/**"
  - "resources/js/Pages/Dental/**"
---

# Clinical & Odontogram Domain Guidelines

## Tooth Numbering & Coordinates
- **FDI World Dental Federation Notation:**
  - Adult Dentition (Quadrants 1–4): 11–18 (UR), 21–28 (UL), 31–38 (LL), 41–48 (LR).
  - Pediatric / Primary Dentition (Quadrants 5–8): 51–55 (UR), 61–65 (UL), 71–75 (LL), 81–85 (LR).
- **Universal Numbering System:**
  - Adult: 1–32.
  - Primary: A–T.
- Bi-directional conversion must be preserved across clinical chart exports and UI views.

## 5-Surface Coordinates
- Mesial (M), Distal (D), Occlusal (O) / Incisal (I), Buccal / Facial (B/F), Lingual / Palatal (L/P).
- Root systems: Single-rooted (Incisors, Canines), Multi-rooted (Premolars 1–2 roots, Molars 2–3 roots).

## Condition State Machine & Layering
Teeth support layered conditions simultaneously:
- **Caries:** Active / Arrested.
- **Restorations:** Composite, Amalgam, Glass Ionomer (GI), Inlay/Onlay.
- **Endodontics:** Pulpotomy, Pulpectomy, RCT completed, Gutta-percha obturation.
- **Prosthetics:** Crown, Bridge Abutment, Pontic, Veneer.
- **Surgical:** Extracted / Missing, Impacted, Implant placed.

## Periodontal Charting Depth Matrix
- 6 probing sites per tooth: Distobuccal (DB), Midbuccal (B), Mesiobuccal (MB), Distolingual (DL), Midlingual (L), Mesiolingual (ML).
- Metrics tracked: Probing Depth (PD), Clinical Attachment Loss (CAL), Gingival Margin (GM), Bleeding on Probing (BOP), Suppuration (SUP), Plaque Index (PI), Mobility (Class I–III), Furcation (Grade I–IV).

## Treatment & Procedure Synchronization
- Procedure completion automatically syncs to `patient_teeth` and creates/updates `tooth_findings` in the latest `dental_examinations`.
- CDT code hierarchy follows dental specialties: Diagnostic, Preventive, Restorative, Endodontics, Periodontics, Prosthodontics, Oral Surgery, Orthodontics, Pediatric.
