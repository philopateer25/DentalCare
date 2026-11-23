# DentalCare documentation

This documentation was produced from a full read-through of the application source, database migrations, seeders, tests, Docker/deployment files, and a test run. It is intended to take a new developer from "what is this?" to "I can safely change it".

## Start here

| If you are... | Read in this order |
| --- | --- |
| New to the project | [ARCHITECTURE](ARCHITECTURE.md) → [DATA_MODEL](DATA_MODEL.md) → [DEVELOPMENT](DEVELOPMENT.md) |
| Picking up a feature | [MODULES](MODULES.md) → relevant section of [ARCHITECTURE](ARCHITECTURE.md) → [API](API.md) |
| Deploying or operating it | [DEPLOYMENT](DEPLOYMENT.md) → [SECURITY](SECURITY.md) → [RECOMMENDATIONS](RECOMMENDATIONS.md) |
| Auditing / planning work | [SECURITY](SECURITY.md) → [RECOMMENDATIONS](RECOMMENDATIONS.md) → [DATA_MODEL](DATA_MODEL.md) |
| Looking for a specific endpoint | [API](API.md) |

## Document map

### [ARCHITECTURE.md](ARCHITECTURE.md)

The big picture:

- runtime topology and request lifecycle
- the two user interfaces (Filament + Inertia)
- multi-tenancy with `Practice` and `Branch`
- roles and authorization
- feature flags
- services, observers, jobs, scheduler
- notifications, PWA, localization, file storage
- key design decisions and trade-offs

### [DATA_MODEL.md](DATA_MODEL.md)

The persistence layer:

- tenancy hierarchy and ownership rules
- table catalog grouped by domain
- Mermaid ER diagrams
- clinical/odontogram model duplication
- billing and inventory costing rules
- known schema gaps (`patients.branch_id`, etc.)

### [MODULES.md](MODULES.md)

What each part of the product does, screen by screen:

- patients and medical history
- appointments and calendar
- examinations and 3D odontogram
- treatment planning
- billing, installments, commissions, expenses, payroll
- insurance, labs, inventory
- WhatsApp bot and voice notes
- analytics, dashboard widgets, support
- onboarding, licensing, system panel

### [API.md](API.md)

Endpoint reference:

- route groups and middleware
- request/response examples for the clinical APIs
- tenant and authorization expectations
- current API design problems

### [DEVELOPMENT.md](DEVELOPMENT.md)

Day-to-day engineering:

- prerequisites and setup
- seeded accounts and demo data
- common workflows (add a resource, add a feature flag, add a migration)
- testing strategy and known test failures
- coding conventions
- troubleshooting

### [DEPLOYMENT.md](DEPLOYMENT.md)

Running it outside a laptop:

- local Docker compose
- production compose
- Windows on-prem installer
- license activation and heartbeat
- scheduler/queue requirements
- TLS, backups, and go-live checklist

### [SECURITY.md](SECURITY.md)

Security posture and findings:

- authentication / authorization model
- critical authentication bypasses
- unauthenticated clinical APIs
- webhook and secret handling
- license enforcement gaps
- data isolation and operational risks
- remediation guidance

### [RECOMMENDATIONS.md](RECOMMENDATIONS.md)

Prioritized engineering roadmap:

- P0: stop-the-line security and correctness fixes
- P1: architecture and testing fixes
- P2: product/UX and maintainability work
- suggested sequencing and acceptance criteria

## Conventions used in these docs

- "Practice" means a tenant/clinic (`practices` table). "Branch" means a physical location inside a practice.
- Paths are relative to the repository root unless stated otherwise.
- `→` describes a relationship or navigation step.
- Severity in [SECURITY.md](SECURITY.md): **Critical**, **High**, **Medium**, **Low**.
- The documentation describes the code as it exists on the audit date. When code and docs disagree, the code is authoritative; update the docs with the change.
