---
name: new-feature
description: >
  OpenSpec-integrated new feature pipeline for this WordPress subscription plugin.
  Use this skill whenever the user describes a feature to build, asks to add something
  new, says "I want X to do Y", references a GitHub issue for a feature, or runs
  /new-feature. Trigger even for casual phrasing ("can we add...", "it would be nice
  if...", "users are asking for X"). The skill drives the full lifecycle:
  clarify → explore → spec → implement incrementally → verify.
---

# OpenSpec New Feature Pipeline

This skill combines structured requirement gathering, codebase exploration, and OpenSpec artifact generation with incremental implementation — so features are built deliberately against a spec, not improvised.

---

## Preamble — Activate Caveman Mode

Before anything else, invoke caveman lite for the intake phase:

```
/caveman lite
```

Switch levels as the pipeline progresses — see per-phase instructions below. Code blocks, OpenSpec artifacts, and commit messages always write normal.

---

## Phase 1 — Intake: Define the Feature

**Caveman level: `lite`** — keep questions readable and easy to answer.

Before touching any code or creating any OpenSpec artifacts, gather enough information to understand what needs to be built. Ask only for what isn't already provided — don't repeat questions the user already answered.

**Ask for these if missing:**

1. **What it does** — one-sentence description of the feature
2. **Why it's needed** — user problem or business reason it solves
3. **Acceptance criteria** — numbered list of "done" conditions (what must be true for the feature to be complete)
4. **Affected areas** — admin UI, frontend, My Account, cron, payment gateway, REST API, WooCommerce Blocks?
5. **Priority** — blocking a release / normal / nice-to-have
6. **GitHub issue URL** — if one exists, read it first and skip any questions already answered there

If the user gives a GitHub issue URL, read it first and skip any questions already answered there.

Do not proceed to Phase 2 until you have at minimum: what it does, why it's needed, and acceptance criteria. The acceptance criteria are especially critical — they become your verification checklist at the end.

---

## Phase 2 — Explore: Understand the Codebase

**Caveman level: `full`** — switch now: `/caveman`. Exploration findings and integration notes reported in compressed form.

With the intake complete, explore the codebase _before_ designing anything. The goal is to find existing patterns to follow and integration points to use, not to improvise from scratch.

**What to do:**

- Find existing implementations in the affected area (similar classes, hooks, templates, AJAX handlers)
- Identify integration points: which WordPress/WooCommerce hooks to use, which files to extend
- Check if similar functionality already exists and can be reused or extended instead of rebuilt
- Note any `subscrpt_*` hooks the feature will need to fire or listen to
- Check if the current branch is appropriate for this feature — if unrelated, invoke the `git-branch-creation` skill

Document your findings. These feed directly into the OpenSpec artifacts you'll create next.

---

## Phase 3 — Propose: Generate OpenSpec Artifacts

**Caveman level: `full`** — artifact content writes normal; surrounding communication stays compressed.

Now create a structured OpenSpec change that captures the requirements and design.

**Run:**

```
/opsx:propose "feat-<kebab-feature-name>"
```

Generate three artifacts for the `spec-driven` schema:

### proposal.md

- **Feature description**: one-sentence summary from intake
- **Motivation**: why this is needed (from intake)
- **Acceptance criteria**: numbered list from intake
- **Affected scope**: which areas of the plugin are touched
- **Priority**: one of `low` / `medium` / `high` / `must-have`
- **GitHub issue**: URL if one exists

### design.md

- **Implementation approach**: how it will work end-to-end — the data flow, user interaction, and system behaviour
- **Files to create or modify**: list with a one-line description of the change in each
- **New hooks to fire**: any new `do_action('subscrpt_*', ...)` — document each with parameters and when it fires; new hooks are a public API that Pro may depend on
- **Existing hooks used**: `add_action`/`add_filter` calls and why each is the right integration point
- **Risk assessment**: low/medium/high — high if touching payment gateways, subscription lifecycle, or core hook contracts
- **Backwards-compatibility notes**: anything that could break existing behaviour or Pro integrations

### tasks.md

Use this checklist structure (adapt as needed):

```
- [ ] Create GitHub issue (if not already done)
- [ ] Create feature branch (if not already done)
- [ ] <implementation unit 1 — e.g., "Register new subscrpt_* hook in Action.php">
- [ ] <implementation unit 2 — e.g., "Add admin settings field for the new option">
- [ ] <implementation unit N>
- [ ] Run side-effect-check skill
- [ ] Manually verify each acceptance criteria item
```

Each task should be one logical unit of work — small enough to implement and review in isolation.

---

## Phase 4 — Apply: Implement Incrementally

**Caveman level: `full`** — task summaries and checkpoint messages stay compressed.

Work through the tasks from `tasks.md` **one at a time**. The goal is to keep each change small and reviewable so the user can catch scope drift or design issues early.

**For each task:**

1. Implement the smallest coherent unit (e.g., add one hook, create one settings field, build one template)
2. After completing it, summarize briefly:
   - What was changed
   - Which files were created or modified
   - How this connects to the next task or the overall design
3. Mark the task complete in `tasks.md` (`- [ ]` → `- [x]`)
4. Ask the user to confirm before moving to the next task

**If the user says "continue", "skip explanations", "go ahead", or similar** — treat that as blanket approval for all remaining tasks. Implement everything to completion without further checkpoints, marking tasks complete as you go.

**One exception to blanket approval**: if the actual code contradicts what the design assumed (e.g., a hook doesn't exist at the right point, a WooCommerce API changed, an edge case appears that affects scope), always pause and report before proceeding — even if the user said to skip explanations. Design-level surprises need a human decision.

**Examples of logical units:**

- Adding a new hook: add the `do_action()` call and document it, then pause
- New settings field: add the field registration, sanitization, and display in one unit, then pause
- New template: create the template file and the function that loads it, then pause

---

## Phase 5 — Verify and Wrap Up

**Caveman level: `full`** — wrap-up messages stay compressed.

After all tasks are complete:

- Walk through each acceptance criteria item from Phase 1 — confirm each is met
- The `side-effect-check` skill should have run as a task — if it wasn't completed, run it now
- Commit: `feat: ✨ <short description>`
- Offer to archive the OpenSpec change: `/opsx:archive <change-name>`

---

## Project-Specific Guardrails

Keep these in mind throughout — they apply to all new code in this plugin:

- **New hooks**: Any new `do_action('subscrpt_*')` or `apply_filters('subscrpt_*')` must have PHPDoc with parameter descriptions. New hooks are an immediate public API — Pro may start depending on them as soon as they ship.
- **Escaping**: All new output must be escaped (`esc_html`, `esc_attr`, `wp_kses_post`). No exceptions.
- **Nonces + capabilities**: New AJAX handlers must verify a nonce and `current_user_can()`. New REST endpoints must declare `permission_callback`.
- **Constants**: Use `SUBSCRPT_*` in all new code. Never use the deprecated `WP_SUBSCRIPTION_*` names.
- **Payment gateways**: Classes that extend Stripe or PayPal must be wrapped in `class_exists()` at their init point — never instantiate without this guard.
- **CSS classes**: New UI components use the `subscrpt-` prefix. Do not use `wp-subscription-` for new elements (that prefix is legacy; don't extend it).
- **Assets**: Register scripts/styles first, then enqueue only on pages where needed. Use `strategy => 'defer'` where possible. Never bundle copies of WordPress-bundled libraries.
- **Hook contract**: If the feature requires modifying an existing `subscrpt_*` hook signature (parameters, order, when it fires), keep the old behaviour alongside the new for at least one major version and flag it explicitly in design.md.
