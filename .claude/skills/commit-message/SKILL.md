---
name: commit-message
description: >
  Use this skill whenever you need to write, format, or suggest a git commit message for this project.
  Covers the exact commit format (type + emoji + imperative subject), all valid types with their emojis,
  optional body and footer conventions, and breaking change syntax.
  Trigger whenever the user asks you to commit, write a commit message, stage changes, or describe what to commit.
---

# Commit Message Format — subscription plugin

Commits follow the [Conventional Commits](https://www.conventionalcommits.org/) style, extended with emojis via `cz-customizable`. The tool lives at `yarn commit` but you write messages in this exact format.

## Structure

```
type: emoji subject

body (optional)

BREAKING CHANGE 🧨 description (optional)

footer (optional)
```

The **subject line** is the most important part. Keep it short and use the imperative mood ("add feature", not "added feature" or "adds feature"). The emoji is part of the type token — always include it.

## Types

| Type        | Token          | When to use                                                  |
| ----------- | -------------- | ------------------------------------------------------------ |
| New feature | `feat: ✨`     | Adding new user-facing functionality                         |
| Bug fix     | `fix: 🐛`      | Correcting broken behaviour                                  |
| Refactor    | `refactor: 🛠️` | Code restructuring with no behaviour change                  |
| Style       | `style: 🎨`    | Formatting, whitespace, missing semicolons — no logic change |
| Chore       | `chore: 🤖`    | Build process, tooling, dependency updates                   |
| Docs        | `docs: 📝`     | Documentation only                                           |
| CI          | `ci: 🎡`       | CI/CD pipeline changes                                       |
| Performance | `perf: 🚀`     | Code changes that improve speed or resource usage            |
| Release     | `release: 🎉`  | Version bump / release commit                                |
| Test        | `test: 🧪`     | Adding or fixing tests                                       |
| Rollback    | `rollback 🔙`  | Reverting to a previous commit                               |

## Subject line rules

- **Imperative mood**: "add pause button", not "added" or "adds"
- **Lowercase** after the emoji: `feat: ✨ add pause button`
- **No period** at the end
- **50 chars or less** is ideal; keep it scannable

**Examples from this repo:**

```
feat: ✨ add subscription pause feature
fix: 🐛 renewal date not updating after payment retry
refactor: 🛠️ extract auto-renewal logic into AutoRenewal class
chore: 🤖 update @wordpress/scripts to v30
docs: 📝 document subscrpt_ hook naming convention
release: 🎉 v1.9.3
```

## Scopes

This project has **no defined scopes** — leave the scope out entirely.

## Body (optional)

Add a body when the subject line doesn't fully explain the _why_. Separate from the subject with a blank line. Use `|` to insert line breaks when writing via `yarn commit`.

```
feat: ✨ add grace period email notification

Send a reminder email when a subscription enters the grace period
rather than silently expiring it. This reduces involuntary churn|
by giving customers a chance to update their payment method.
```

## Breaking changes

Breaking changes are allowed on: `feat: ✨`, `fix: 🐛`, `refactor: 🛠️`, `perf: 🚀`, `rollback 🔙`.

Add a `BREAKING CHANGE 🧨` block after the body (or directly after the subject if there's no body):

```
refactor: 🛠️ rename WP_SUBSCRIPTION_PATH to SUBSCRPT_PATH

BREAKING CHANGE 🧨 WP_SUBSCRIPTION_PATH constant has been removed.
Use SUBSCRPT_PATH instead. Legacy aliases remain in LegacyCompat.php
for one major version.
```

## Footer (optional)

Reference closed issues:

```
fix: 🐛 cart item data missing for block checkout

Fixes #142
```

## Running the interactive commit wizard

```bash
yarn commit
```

This launches the `cz-customizable` interactive prompt that walks through type → subject → body → breaking changes → footer. It enforces this exact format so you never have to remember the emoji.
