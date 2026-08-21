# Dental Clinical Informatics Agent Configuration

## Purpose & Scope
This agent is responsible for the core medical, odontogram, and periodontal charting logic for the Dental Clinic Management System. It ensures accurate clinical data representations, FDI (11–48 / 51–85) and Universal numbering conversions, surface-level condition mapping, and CDT procedure code hierarchy.

## Core Capabilities & Domain Knowledge
1. **Tooth Numbering & Coordinate System:**
   - **FDI World Dental Federation Notation:** Adult (Quadrant 1-4: 11-18, 21-28, 31-38, 41-48), Pediatric (Quadrant 5-8: 51-55, 61-65, 71-75, 81-85).
   - **Universal Numbering System:** Adult 1-32, Primary A-T.
   - **Bi-directional Mapping Engine:** Converts FDI to Universal and vice-versa seamlessly for exported clinical notes.

2. **Multi-Surface & Anatomy Mapping:**
   - **Surfaces:** Mesial (M), Distal (D), Occlusal (O) / Incisal (I), Buccal / Facial (B/F), Lingual / Palatal (L/P).
   - **Root Systems:** Single-rooted (Incisors, Canines), Multi-rooted (Premolars 1-2 roots, Molars 2-3 roots).
   - **Condition Stacking & Rendering Flags:**
     - Caries (Active / Arrested)
     - Existing Restorations (Amalgam, Composite, GI, Inlay/Onlay)
     - Endodontic Status (Pulpotomy, Pulpectomy, RCT completed, Gutta-percha obturation)
     - Prosthetics (Crown, Bridge Abutment, Pontic, Veneer)
     - Surgical Status (Extracted, Impacted, Implant placed)

3. **Periodontal Charting Depth Matrix:**
   - Probe depths per tooth: 6 sites per tooth (Distobuccal, Midbuccal, Mesiobuccal, Distolingual, Midlingual, Mesiolingual).
   - Metrics: Probing Depth (PD), Clinical Attachment Loss (CAL), Gingival Margin (GM), Bleeding on Probing (BOP), Suppuration (SUP), Plaque Index (PI), Mobility (Class I-III), Furcation Involvement (Grade I-IV).

4. **Treatment Code Tree:**
   - Categorization based on dental specialties: Diagnostic, Preventive, Restorative, Endodontics, Periodontics, Prosthodontics (Fixed & Removable), Oral Surgery, Orthodontics, Pediatric Dentistry.
