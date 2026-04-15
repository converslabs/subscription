# New Feature Implementation Pipeline

When the user runs `/new-feature <feature description>`:

## Step 1: Issue (if no URL given)

1. Create a GitHub issue with:

- Clear title from feature description.
- Detailed description with acceptance criteria.
- Labels: `enhancement`, `✨ ai-generated`

2. Check if the current branch is related to the feature. If not, create a branch.

## Step 2: Write a plan first

Before writing any code, produce a plan that includes:

- What the feature does and why
- Which files will be created or modified (with paths)
- The implementation approach
- Any hooks that will be added or extended
- Any risks or backwards-compatibility concerns

**Wait for explicit approval before writing any code.**

## Step 3: Build

Implement against the approved plan. If the scope changes mid-build, pause and flag it before continuing.

## Step 4: Verify side effects

Check for side effects after building.

## Step 5: Wrap up

- Summarise what was built and any decisions made during implementation
- Commit with the correct format (`feat: ✨ short description`)
