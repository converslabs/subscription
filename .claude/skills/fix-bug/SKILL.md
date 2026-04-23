---
name: fix-bug
description: >
  OpenSpec-integrated bug fixing pipeline for this WordPress subscription plugin.
  Use this skill whenever the user reports a bug, says something is broken, pastes
  an error or stack trace, describes unexpected behavior, or asks to debug/fix anything.
  Trigger even when the user phrases it casually ("this isn't working", "users are
  complaining about X", "the cancel button doesn't do anything"). The skill drives
  the full lifecycle: clarify → investigate → spec → implement incrementally → verify.
---

# OpenSpec Bug Fix Pipeline

This skill combines structured clarification, root cause investigation, and OpenSpec artifact generation with incremental implementation — so bugs get fixed precisely, not just patched.

---

## Preamble — Activate Caveman Mode

Before anything else, invoke caveman lite for the clarification phase:

```
/caveman lite
```

Switch levels as the pipeline progresses — see per-phase instructions below. Code blocks, error messages, and OpenSpec artifacts always write normal.

---

## Phase 1 — Intake: Clarify Before Anything Else

**Caveman level: `lite`** — keep questions readable and easy to answer.

Before touching any code or creating any OpenSpec artifacts, gather enough information to understand the bug precisely. Ask only for what isn't already provided — don't repeat questions the user already answered.

**Ask for these if missing:**

1. **Observed behavior** — the exact symptom, error message, or incorrect output
2. **Expected behavior** — what should happen instead
3. **Reproduction steps** — a numbered list of exact actions that trigger the bug
4. **When did it start?** — always broken, or started after a specific change/version?
5. **Affected scope** — all users? specific product type? specific payment gateway? specific browser?
6. **Logs or traces** — any PHP error logs, browser console errors, or WooCommerce log entries?

If the user gives a GitHub issue URL, read it first and skip any questions already answered there.

Do not proceed to Phase 2 until you have at minimum: observed behavior, expected behavior, and reproduction steps. The reproduction steps are especially critical — they become your verification checklist at the end.

---

## Phase 2 — Investigate: Find the Root Cause

**Caveman level: `full`** — switch now: `/caveman`. Technical findings, root cause analysis, and file traces reported in compressed form.

With the intake complete, explore the codebase _before_ proposing a fix. The goal is to understand _why_ the bug happens, not just _where_ the symptom appears.

**Launch the `code-search` agent** with the bug description and symptoms as input. The agent knows the plugin's architecture and will return exact file paths, line numbers, execution traces, and recent git changes for the affected area.

Once the agent returns, read the key files it identified, then:

- Pinpoint the actual root cause — where behaviour diverges from expected
- Note any `subscrpt_*` hooks that will be touched (Pro depends on these)
- Identify all files the fix must touch (bug may surface in one place, root cause in another)

Document your findings. These feed directly into the OpenSpec artifacts you'll create next.

---

## Phase 3 — Propose: Generate OpenSpec Artifacts

**Caveman level: `full`** — artifact content writes normal; surrounding communication stays compressed.

Now create a structured OpenSpec change that captures everything you've learned.

**Run:**

```
/opsx:propose "fix-<kebab-bug-name>"
```

Generate three artifacts for the `spec-driven` schema:

### proposal.md

- **Observed behavior**: copy from intake
- **Expected behavior**: copy from intake
- **Reproduction steps**: numbered list from intake
- **Affected scope**: from intake
- **Severity**: one of `low` / `medium` / `high` / `critical`
  - critical = payment broken, data loss, security issue
  - high = core subscription lifecycle broken for all users
  - medium = broken for a subset of users or non-critical path
  - low = cosmetic or edge-case

### design.md

- **Root cause**: precise explanation of _why_ the bug happens (not just where)
- **Fix approach**: what will change and why this resolves the root cause
- **Files to change**: list with a one-line description of the change in each
- **Hooks affected**: any `subscrpt_*` hooks added, removed, renamed, or reordered — flag each one since Pro depends on them
- **Risk assessment**: low/medium/high — high if touching payment gateways, subscription lifecycle, or core hook contracts
- **Rollback notes**: how to revert if the fix causes regressions

### tasks.md

Use this checklist structure (adapt as needed):

```
- [ ] Write regression test for the bug (if the codebase has tests)
- [ ] <specific fix unit 1 — e.g., "Fix the condition in Action.php that skips status update">
- [ ] <specific fix unit 2 — if more than one change is needed>
- [ ] Manually verify reproduction steps no longer trigger the bug
- [ ] Run side-effect-check skill
```

Each task should be one logical unit of work — small enough to implement and review in isolation.

---

## Phase 4 — Apply: Implement Incrementally

**Caveman level: `full`** — task summaries and checkpoint messages stay compressed.

Work through the tasks from `tasks.md` **one at a time**. The goal is to keep each change small and reviewable so the user can catch problems early rather than reviewing a large diff at the end.

**For each task:**

1. Implement the smallest coherent unit (e.g., rename a hook across all its occurrences, fix one conditional, update one file)
2. After completing it, summarize briefly:
   - What was changed
   - Which files were modified
   - Why this change addresses the root cause or prepares for the next step
3. Mark the task complete in `tasks.md` (`- [ ]` → `- [x]`)
4. Ask the user to confirm before moving to the next task

**If the user says "continue", "skip explanations", "go ahead", or similar** — treat that as blanket approval for all remaining tasks. Implement everything to completion without further checkpoints, marking tasks complete as you go.

**One exception to blanket approval**: if the actual code contradicts what the design assumed (e.g., a hook doesn't exist, a function signature differs, an edge case appears), always pause and report before proceeding — even if the user said to skip explanations. Design-level surprises need a human decision.

**Examples of logical units:**

- Renaming a hook: update all `do_action('old_hook')` and `add_action('old_hook', ...)` calls across all files, then pause
- Fixing a conditional: update the specific `if` statement causing wrong behavior, then pause
- Adding a missing return value: update the one function, then pause

---

## Phase 5 — Verify and Wrap Up

**Caveman level: `full`** — wrap-up messages stay compressed.

After all tasks are complete:

- Remind the user to manually walk through the reproduction steps to confirm the bug is gone
- The `side-effect-check` skill should have run as a task — if it wasn't completed, run it now
- Offer to archive the OpenSpec change: `/opsx:archive <change-name>`

---

## Project-Specific Guardrails

Follow project-specific guardrails in `CLAUDE.md` (hooks, escaping, nonces, constants, gateways, CSS prefix, assets).
