# Domain Docs

How skills consume DentalCare domain documentation when exploring the codebase.

## Domain Agent Specs

Detailed domain knowledge and operational guidelines live in `agents/`:
- **Dental Clinical Informatics**: [`agents/dental-clinical-agent.md`](../../agents/dental-clinical-agent.md) — FDI/Universal notation, tooth surface coordinates (M/D/O/B/L), periodontal probing matrices, condition stacking.
- **Billing & Financial Informatics**: [`agents/billing-finance-agent.md`](../../agents/billing-finance-agent.md) — Invoices, treatment syncing, installment plans (InstaPay/cash), doctor commission splits.
- **Filament v3 Resources**: [`agents/filament-resource-agent.md`](../../agents/filament-resource-agent.md) — Back-office admin, multi-tenancy, relation managers, custom actions, Spatie RBAC.
- **Inertia + React Chairside UI**: [`agents/inertia-react-agent.md`](../../agents/inertia-react-agent.md) — Interactive SVG/Canvas odontogram, real-time chair timeline, Tailwind + Framer Motion.

## Before exploring, read these

- Domain agent specifications in `agents/*.md` matching the feature you are touching.
- `docs/adr/` if architectural decision records exist.
