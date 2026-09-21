# Release Process

Generic release flow for our WordPress plugins. Plugin-specific distribution steps (wp.org SVN, updater manifests, marketplace uploads) are **not** covered here — each plugin documents its own in the [Distribution](#5-distribution) section at the bottom.

---

## 1. Release cadence

| Day       | What happens                                                     |
| --------- | ---------------------------------------------------------------- |
| Mon – Sat | Development. One branch per feature/fix, one PR each.            |
| Sunday    | Release day. Cut the release branch, build, PR to `main`, merge. |

A release period is normally one week (Mon–Sat). Anything not reviewed and merge-ready by Saturday rolls into the next period — do not hold the release for it.

### Branch naming

Work branches are typed, one unit of work per branch:

| Type            | Branch prefix | Example                     |
| --------------- | ------------- | --------------------------- |
| Feature         | `feat/`       | `feat/subs-plans`           |
| Bug fix         | `fix/`        | `fix/revoke-access-on-hold` |
| Refactor        | `refactor/`   | `refactor/admin-header`     |
| Docs            | `docs/`       | `docs/release-process`      |
| Chore / tooling | `chore/`      | `chore/bump-phpcs`          |

Release branches: `release/vX.Y.Z` — e.g. `release/v1.11.3`.

### Choosing the version

`X.Y.Z`:

- `X` — major: way too big a change, or breaking changes.
- `Y` — minor: new features.
- `Z` — patch: fixes and improvements only.

Rules:

- Any new feature in the release → bump `Y`, reset `Z` to `0`.
- Patch-only release → bump `Z`.
- `Z` never goes past `9`: if the current version is `1.1.9` and the release is patch-only, the next version is `1.2.0`, not `1.1.10`.

Decide this version **before** creating the release branch — the branch name carries it.

---

## 2. Release day, step by step

Run everything from the plugin directory.

### Step 1 — Create the release branch

Branch off the current `main`. `vX.Y.Z` in the branch name is the actual version this release will ship as — the same number that goes into `package.json` in step 3 and into the changelog header in step 4:

```bash
git checkout main
git pull origin main
git checkout -b release/v1.11.3
git push -u origin release/v1.11.3
```

### Step 2 — Merge the completed PRs into the release branch

Retarget each finished PR's base to the release branch and merge it there — not into `main`. Only merge PRs that are reviewed and complete; anything unfinished stays on its own branch for the next period.

Do this on GitHub, per PR:

1. Open the PR.
2. Next to the title, click **Edit** and change the base branch from `main` to `release/v1.11.3`.
3. Confirm the diff now shows only that PR's own commits.
4. **Merge pull request**.

Alternatively, with the `gh` CLI:

```bash
gh pr edit <number> --base release/v1.11.3
gh pr merge <number> --merge
```

Pull the merges down and review what is going out:

```bash
git pull origin release/v1.11.3
git log --oneline main..release/v1.11.3
```

### Step 3 — Bump the version in `package.json`

`package.json` is the **single source of truth** for the release version. The release script reads it and substitutes it into every file that carries a version placeholder (main plugin file, readme template, translation template, updater manifest, …). Do not hand-edit version numbers anywhere else, and leave the `#..._VERSION` placeholders in the repo intact.

```jsonc
{
  "version": "1.11.3", // <- only place you edit
}
```

### Step 4 — Add the changelog entry

Add a new block at the **top** of `changelog.txt`, directly under the `*** ... Changelog ***` header. Newest release first, blank line between blocks.

```
YYYY-MM-DD - version X.Y.Z
* new: Add this tag for new features.
* fix: Add this tag for fixed logs.
* improve: Add this tag for improved logs.
* update: Add this tag for updates on non essential files.
* remove: Add this tag for removed item logs.
* refactor: Add this tag for refactored logs.
```

Rules:

- Date is the release date, `YYYY-MM-DD`, zero-padded.
- The version on the header line must match `package.json` exactly.
- One entry per line, always `* tag: Description.`
- Other tags are allowed when none of the above fit, but they must keep the `* tag: description` shape.
- Write for the user, not for the repo: describe the visible behaviour that changed, not the internal class that moved.

Example:

```
2026-08-30 - version 1.11.2
* new: Relative ordering of renewal orders.
* fix: Subscription list filters.
* fix: Guest account reusable token issue.
* fix: Subscription details error on My Account page.
```

The release script parses this file, so the shape matters: a line starting with a 4-digit year is read as a version header, and every `*` line becomes a readme bullet.

### Step 5 — Build the release

```bash
yarn release
```

The release script:

1. **Cleans** previous build output and vendor.
2. **Builds** — production composer install, asset build, translation templates — and copies the shipping files into `releases/<plugin>/`.
3. **Sets the version** — replaces the `#..._VERSION` placeholders with the `package.json` version.
4. **Builds changelogs** — converts `changelog.txt` into WordPress readme format and injects it into `readme-template.txt` at the `[autofill_changelogs___DO_NOT_TOUCH_THIS_LINE]` marker, writing the result to both `releases/<plugin>/readme.txt` and the repo root `readme.txt`.
5. **Zips** the package as `releases/<plugin>_vX.Y.Z.zip`.
6. **Reinstalls dev dependencies.**

Needs on PATH: `jq`, `zip`, `composer`, `yarn`, `wp` (WP-CLI).

> **`readme.txt` is generated.** Every release build overwrites it. Never edit it directly — edit `readme-template.txt` (description, tags, screenshots, tested-up-to, …) and let the build produce `readme.txt`. Changelog content comes from `changelog.txt`, not from the template.

Commit the release changes (build output in `releases/` stays out of git):

```bash
git add package.json changelog.txt readme.txt
git commit -m "chore: 🔧 release v1.11.3"
git push origin release/v1.11.3
```

### Step 6 — Open the PR to `main`

```bash
gh pr create --base main --head release/v1.11.3 --title "Release v1.11.3"
```

The PR description is the changelog for this release, **copied from the generated `readme.txt`** (WordPress readme format):

```
= 1.11.3 - Sep 6, 2026 =
-   new: Relative ordering of renewal orders.
-   fix: Subscription list filters.
```

### Step 7 — Merge

Merge the PR into `main` once review and CI are green. The zip built in step 5 is the release artifact.

---

## 3. Checklist

```
[ ] All release PRs reviewed and merged into release/vX.Y.Z
[ ] Version bumped in package.json (and only there)
[ ] changelog.txt entry added at the top, correct date + version, `* tag: description` lines
[ ] yarn release ran clean
[ ] readme.txt regenerated (not hand-edited)
[ ] package.json + changelog.txt + readme.txt committed and pushed
[ ] PR opened to main, description = changelog from readme.txt
[ ] PR merged
[ ] Distribution steps done (see below)
```

---

## 4. Notes

- Never release directly from `main` or from a feature branch — always through `release/vX.Y.Z`.
- Never edit generated files (`readme.txt`, built assets, `.pot` output) by hand.
- If a merged PR turns out to be broken after step 2, revert it on the release branch rather than delaying the release.

---

## 5. Distribution

- After merging the `release` branch into the `main` branch, create a release tag. Make sure you do not add any prefix to the version tag. E.g.: `2.0.1`.
- Add the file generated when you ran `yarn release` to the GitHub release draft.
- After publishing the release, it will be automatically synced to the WordPress Org.
