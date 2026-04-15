# Bug Fixing Pipeline

When the user runs `/fix-bug <bug description or issue URL>`:

## Step 1: Issue (if no URL given)

1. Create a GitHub issue with:

- Clear title from the issue description.
- Detailed description of the issue.
- Labels: `bug`, `✨ ai-generated`

2. Check if the current branch is related to the fix. If not, create a branch.

## Step 2: Gather details before touching any code

Always ask the reporter (or clarify from the issue) before starting:

1. **Expected vs actual behaviour** — what should happen, and what is happening instead?
2. **Is it a regression?** — did this work in a previous version? If yes, which version broke it?

Do not start investigating or writing code until both of these are clear.

## Step 3: Investigate

Read all relevant files and trace the execution path. Confirm the root cause before proposing or writing a fix.

## Step 4: Write a plan first

Before writing any code, produce a plan that includes:

- What the fix does and why
- Which files will be created or modified (with paths)
- The implementation approach
- Any hooks that will be added or extended
- Any risks or backwards-compatibility concerns

**Wait for explicit approval before writing any code.**

## Step 5: Fix and verify

check for side effects after applying the fix.

## Step 6: Wrap up

- Summarise what was changed and why (root cause → fix applied)
- Commit with the correct format (`fix: 🐛 short description`)
