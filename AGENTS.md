# DentalCare - Agent Guidelines & Project Architecture

DentalCare is a specialized Dental Clinic Management System built with Laravel 11, Filament v3 (admin back-office), and Inertia.js + React (interactive chairside clinic interface).

## Core Domain Specifications

When implementing or reviewing features, consult the corresponding domain guide in `agents/`:

1. **Clinical & Odontogram**: [`agents/dental-clinical-agent.md`](agents/dental-clinical-agent.md)
   - FDI (11–48 / 51–85) and Universal numbering conversions.
   - Tooth anatomy & 5-surface coordinates (Mesial, Distal, Occlusal, Buccal, Lingual).
   - Periodontal charting depth matrix (6 sites per tooth: PD, CAL, BOP, SUP, PI, Furcation, Mobility).
   - CDT procedure code hierarchy & multi-layered condition states.

2. **Billing & Financial Informatics**: [`agents/billing-finance-agent.md`](agents/billing-finance-agent.md)
   - Chairside invoice generation and procedure synchronization.
   - Installment plans and payment methods (InstaPay with receipt reference logging, Cash, POS, TPA).
   - Specialist chair-production commission splits (Gross vs Net, tiered volume splits).

3. **Filament v3 Back-Office Architecture**: [`agents/filament-resource-agent.md`](agents/filament-resource-agent.md)
   - Multi-tenant clinic management (Practices, Branches, Operatories, Staff roles).
   - Custom form schemas, relation managers, custom actions, and real-time dashboard widgets.
   - Spatie Permission role guardrails.

4. **Inertia + React Chairside Engine**: [`agents/inertia-react-agent.md`](agents/inertia-react-agent.md)
   - Interactive SVG/Canvas vector odontogram and perio visualizer.
   - Real-time operatory timeline and chair status lifecycle via Laravel Reverb WebSockets.
   - UI polish according to `emil-design-eng` and touch responsiveness per `mobile-native`.

---

## Agent skills

### Issue tracker

Issues and feature specs are tracked in GitHub Issues ([philopateer25/DentalCare](https://github.com/philopateer25/DentalCare)). See `docs/agents/issue-tracker.md`.

### Domain docs

Domain specs and operational conventions live in `agents/` and `docs/agents/domain.md`.

---

## Engineering Guidelines & Standards

- **TDD & Seams**: Prioritize writing tests against external interfaces (`tests/Feature` or API endpoints) before implementation. Run tests via `php artisan test`.
- **UI Craftsmanship**: Adhere to `emil-design-eng` for transitions, active button feedback, and spring-based animations. Ensure `mobile-native` touch ergonomics for clinic tablets.
- **Security & Data Isolation**: Strict Spatie tenant/role enforcement, parameterized queries, and sanitized patient file uploads according to `security-and-hardening`.
