---
paths:
  - "resources/js/**"
---

# Inertia + React Chairside Engine Guidelines

## Architecture & Tech Stack
- Frontend engine: Inertia.js with React 19 and TypeScript (`.tsx` / `.jsx`).
- Styling: Tailwind CSS v4.
- Icons: `lucide-react`.
- 3D Engine: `@react-three/fiber` and `@react-three/drei` with `three.js`.

## 3D & 2D Odontogram Rendering
- Dentition mapping defined in `resources/js/Components/Dental3D/dentitionManifest.ts`.
- Components in `resources/js/Components/Dental3D/`:
  - `Dental3DViewer.tsx`: Canvas container with lighting, orbit controls, tooth meshes.
  - `InteractiveTooth.tsx`: Raycasting & click/hover surface interaction.
  - `SingleToothViewer.tsx`: Deep diagnostic inspection of individual tooth models.
- Support instant toggle between FDI (11–48 / 51–85) and Universal (1–32 / A–T) notations without re-fetching network state.

## Mobile & Chairside Ergonomics
- Clinic tablets (iPads, Surface devices) are primary chairside devices.
- Adhere to touch standards:
  - Minimum touch target 44×44px for tooth surfaces and tool buttons.
  - Eliminate tap highlight delay and unwanted text selection on interactive elements.
  - Support high-contrast dark/light mode for bright dental operatory lighting.
- Follow spring animations and micro-interaction polish for tool selection and surface updates.
