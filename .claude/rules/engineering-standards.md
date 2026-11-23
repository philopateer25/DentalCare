---
---

# DentalCare Engineering Guidelines & Codebase Standards

## TDD & Seam Testing
- Prioritize writing tests against external interfaces (`tests/Feature` or API endpoints) before or alongside implementation.
- Run tests via `php artisan test` or `php artisan test --filter=<Name>`.
- In-memory SQLite is configured in `phpunit.xml` (`:memory:` database, `sync` queue, `array` cache/session).

## Strict Multi-Tenant Data Isolation
- Never execute naked queries on tenant models without scoping to `practice_id`.
- On mutation endpoints or detail views, always verify ownership:
  `abort_unless($model->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant access.');`
- Sanitize and isolate all patient file uploads within `storage/app/` protected disks.

## Application Code Preservation
- Do NOT delete, break, or arbitrarily refactor working domain logic.
- Avoid modifying working third-party services like `GeminiService.php` (appointment NLP parser), `WhatsAppService.php`, or `LicenseManager.php` unless explicitly tasked.

## Issue Tracking Conventions
- Issues and feature specifications are tracked on GitHub: `philopateer25/DentalCare`.
- Use the `gh` CLI for issue management (`gh issue list`, `gh issue view <num>`, `gh issue create`).
