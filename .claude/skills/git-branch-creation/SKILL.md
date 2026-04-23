---
name: git-branch-creation
description: >
  Use this skill to create new git branches for features, fixes, and other work.
---

# Git Branch Creation — subscription plugin

Before writing any code, check the current branch:

```bash
git branch --show-current
```

**If on `main` (or `master`), always create a new branch first.** Never commit work directly to main.

### Branch naming

Use the same type prefix as the eventual commit message:

| Work type       | Branch prefix | Example                        |
| --------------- | ------------- | ------------------------------ |
| New feature     | `feat/`       | `feat/subscription-pause`      |
| Bug fix         | `fix/`        | `fix/renewal-date-calculation` |
| Refactor        | `refactor/`   | `refactor/auto-renewal-class`  |
| Chore / tooling | `chore/`      | `chore/update-wp-scripts`      |
| Docs            | `docs/`       | `docs/hook-reference`          |
| Release         | `release/`    | `release/v1.9.3`               |

Use lowercase kebab-case after the prefix. Keep it short but descriptive enough to understand without opening the PR. Create a branch from main branch unless the work is directly related to an existing branch. For example, if you're working on a new feature that builds on an existing feature branch, you can branch off from that feature branch instead of main.

```bash
git checkout -b feat/subscription-pause
```

If already on a feature/fix branch that matches the work, continue on it — no need to create another.
