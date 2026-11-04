# Issue tracker: GitHub

Issues and specs for this repo live as GitHub issues on [philopateer25/DentalCare](https://github.com/philopateer25/DentalCare). Use the `gh` CLI for all operations.

## Conventions

- **Create an issue**: `gh issue create --title "..." --body "..."`
- **Read an issue**: `gh issue view <number> --json number,title,body,labels,comments`
- **List issues**: `gh issue list --state open`
- **Comment on an issue**: `gh issue comment <number> --body "..."`
- **Close an issue**: `gh issue close <number> --comment "..."`

## Pull requests as a triage surface

**PRs as a request surface: no.**

## When a skill says "publish to the issue tracker"

Create a GitHub issue in `philopateer25/DentalCare`.

## When a skill says "fetch the relevant ticket"

Read it via `gh issue view <number>`.
