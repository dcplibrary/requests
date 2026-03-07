# SFP Rule Book

This folder is the **single source of truth** for AI-facing and developer-facing “rules” for this repository.

## Index

| Page | What it’s for |
|---|---|
| [CLAUDE.md](CLAUDE.md) | Core package rules and “gotchas” (CSS build/shipping pattern, publish tags, backup/restore constraints, Polaris PAPI integration rules). |
| [blaze-integration.md](blaze-integration.md) | Standard pattern for enabling `livewire/blaze` in packages as an **optional host-app optimization** (safe defaults + constraints). |
| [phpdoc.md](phpdoc.md) | PHPDoc/DocBlock standards for IDE autocompletion (PhpStorm/VS Code) and Doxygen-compatible docs generation. |

## Maintenance rule
When adding, renaming, or removing any `docs/rules/*.md` page, **update the Index table** above in the same change.

