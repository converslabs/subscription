# Bug & Feature Request Workflow

Rules for how to handle every bug report and feature request in this project.

---

## Branching

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

Use lowercase kebab-case after the prefix. Keep it short but descriptive enough to understand without opening the PR.

```bash
git checkout -b feat/subscription-pause
```

If already on a feature/fix branch that matches the work, continue on it — no need to create another.

---

## Atomic commits — one change, one commit

Break work into the smallest logical units and commit each one separately. Do not accumulate multiple unrelated changes into a single commit.

**Why this matters:** Small commits make it easy to bisect bugs, revert a specific change, and review history. A commit that does three things is three times harder to understand and revert.

### What counts as one unit

A "unit" is a single coherent change that can be described in one subject line. Examples:

- Rename one hook across the codebase → commit
- Rename a second hook across the codebase → separate commit
- Add a new method to a class → commit
- Add the test for that method → separate commit
- Update the changelog entry → separate commit

If you find yourself writing "and" in the commit subject, split it into two commits.

### Practical sequence

When a task involves multiple logical steps, follow this loop for each step:

1. Make only the changes for that one unit
2. Run the side effect checklist if the change touches hooks, templates, or public APIs
3. Stage only the files for that unit
4. Commit with a focused message
5. Then move to the next unit

**Example — renaming two hooks:**

```
# Step 1
- Rename subscrpt_old_hook_one → subscrpt_new_hook_one in all files
- git add <affected files>
- git commit -m "refactor: 🛠️ rename subscrpt_old_hook_one to subscrpt_new_hook_one"

# Step 2
- Rename subscrpt_old_hook_two → subscrpt_new_hook_two in all files
- git add <affected files>
- git commit -m "refactor: 🛠️ rename subscrpt_old_hook_two to subscrpt_new_hook_two"
```

Never batch both renames into one commit.

---

## Bug reports

### Step 1 — Gather details before touching any code

Always ask the reporter (or clarify from the issue) before starting:

1. **Expected vs actual behaviour** — what should happen, and what is happening instead?
2. **Is it a regression?** — did this work in a previous version? If yes, which version broke it?

Do not start investigating or writing code until both of these are clear.

### Step 2 — Investigate

Read all relevant files and trace the execution path. Confirm the root cause before proposing or writing a fix.

### Step 3 — Fix and verify

After applying the fix, check for side effects (see [Side effect checklist](#side-effect-checklist) below).

### Step 4 — Wrap up

- Summarise what was changed and why (root cause → fix applied)
- Commit with the correct format (`fix: 🐛 short description`) and push

---

## Feature requests

### Step 1 — Write a plan first

Before writing any code, produce a plan that includes:

- What the feature does and why
- Which files will be created or modified (with paths)
- The implementation approach
- Any hooks that will be added or extended
- Any risks or backwards-compatibility concerns

**Wait for explicit approval before writing any code.**

### Step 2 — Build

Implement against the approved plan. If the scope changes mid-build, pause and flag it before continuing.

### Step 3 — Verify side effects

After building, run through the [Side effect checklist](#side-effect-checklist).

### Step 4 — Wrap up

- Summarise what was built and any decisions made during implementation
- Commit with the correct format (`feat: ✨ short description`) and push

---

## Side effect checklist

Run this after every bug fix and every feature build before committing:

**1. subscrpt\_\* hook contract**
Check whether any existing hook was renamed, removed, had its parameters changed, or had its firing order changed. The Pro plugin depends on these hooks — any change silently breaks Pro. If a hook must change, keep the old version firing (with a deprecation notice) for at least one major version.

**2. Backwards compatibility**
Check whether any public-facing API changed: constants (`SUBSCRPT_*`), global functions (`subscrpt_*`), or class method signatures in the public namespace. If yes, add legacy aliases in `includes/LegacyCompat.php` and note it in the changelog.

**3. Template / frontend impact**
Check whether any change affects customer-facing templates (`templates/myaccount/`, `templates/emails/`) or the My Account subscription pages. If templates were changed, note that site owners who have overridden templates in their theme will need to update them.

---

## Commit & push

After all checks pass:

1. Stage the changed files
2. Commit using the project commit format (see `commit-message` skill):
   - Bug fix → `fix: 🐛 short description`
   - Feature → `feat: ✨ short description`
   - Other types as appropriate
3. Push to the current remote branch immediately after committing

The pre-commit hook runs lint-staged automatically (phpcs + phpcbf on PHP, prettier on JS/CSS/JSON) — no manual formatting step needed before committing.
