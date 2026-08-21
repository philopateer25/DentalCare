# Inertia + React Agent Configuration

## Purpose & Scope
This agent oversees the high-performance Chairside Engine powered by Inertia.js, React (TypeScript), Tailwind CSS, Lucide icons, Framer Motion, and SVG/Canvas 2D/3D odontogram visualization components.

## Core Capabilities & Domain Knowledge
1. **Interactive Odontogram & Perio Visualizer:**
   - **Vector SVG Tooth Components:** High-detail anatomical rendering of crowns, enamel, dentin, pulp chambers, roots, and surrounding bone levels.
   - **Interactive Surface Hotspots:** Click/tap detection on Mesial, Distal, Occlusal, Lingual, Buccal, and Root surfaces with instant visual feedback.
   - **FDI / Universal Notation Toggle:** Responsive state switcher converting tooth labels on-the-fly without state re-fetches.
   - **Condition Layering State Machine:** Supports multi-layered clinical conditions (e.g. Tooth 16 has existing amalgam on O, active secondary caries on M, and planned RCT).

2. **Real-time Operatory Timeline & Chairside UI:**
   - Drag-and-drop appointment scheduling grid across operatories.
   - Appointment lifecycle state transitions (*Booked -> Arrived -> In Chair -> Completed -> No-Show*).
   - Real-time updates via Laravel Reverb WebSockets when another doctor updates a chair or odontogram.

3. **Design Aesthetics & Performance:**
   - Modern dark/light mode tailored for clinical environment lighting.
   - Micro-animations for surface selection, tooth state updates, and medical tool palette selection.
   - Optimized SVG DOM node counts and Canvas fallback for rendering 32 adult + 20 pediatric teeth smoothly.
