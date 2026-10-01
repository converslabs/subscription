# Release Process

Generic release flow for our WordPress plugins. Plugin-specific distribution steps (wp.org SVN, updater manifests, marketplace uploads) are **not** covered here — each plugin documents its own in the [Distribution](#6-distribution) section at the bottom.

---

## 1. Branches and cadence

| Branch   | Holds                                                                                            |
| -------- | ------------------------------------------------------------------------------------------------ |
| `main`   | Released code only. Every merge into `main` is a release and gets a tag.                         |
| `dev`    | The next release. Every work branch starts here and every PR targets it.                         |
| `feat/…` | One unit of work, branched from `dev`, merged back into `dev` through a PR. See the table below. |

| Day       | What happens                                                                                       |
| --------- | -------------------------------------------------------------------------------------------------- |
| Mon – Sat | Development. One branch per feature/fix, one PR each, all with base `dev`.                         |
| Sunday    | Release day. Merge the selected PRs into `dev`, prepare the release, merge `dev` into `main`, tag. |

A release period is normally one week (Mon–Sat). Anything not reviewed and merge-ready by Saturday stays open and rolls into the next period — do not hold the release for it.

**Everything merged into `dev` ships in the next release.** Merge a PR into `dev` only when it is selected for the release; leave the rest open.

### Branch naming

Work branches are typed, one unit of work per branch, always created from `dev`:

| Type            | Branch prefix | Example                     |
| --------------- | ------------- | --------------------------- |
| Feature         | `feat/`       | `feat/subs-plans`           |
| Bug fix         | `fix/`        | `fix/revoke-access-on-hold` |
| Refactor        | `refactor/`   | `refactor/admin-header`     |
| Docs            | `docs/`       | `docs/release-process`      |
| Chore / tooling | `chore/`      | `chore/bump-phpcs`          |

```bash
git checkout dev
git pull origin dev
git checkout -b feat/subs-plans
# … work, commit, push …
gh pr create --base dev
```

There are no release branches. `dev` is the release candidate.

### Choosing the version

`X.Y.Z`:

- `X` — major: way too big a change, or breaking changes.
- `Y` — minor: new features.
- `Z` — patch: fixes and improvements only.

Rules:

- Any new feature in the release → bump `Y`, reset `Z` to `0`.
- Patch-only release → bump `Z`.
- `Z` never goes past `9`: if the current version is `1.1.9` and the release is patch-only, the next version is `1.2.0`, not `1.1.10`.

Decide the version from what is selected for the release, before step 2.

---

## 2. Release day, step by step

Run everything from the plugin directory.

### Step 1 — Merge the selected PRs into `dev`

Merge each reviewed, complete PR that is going out. Leave everything else open for the next period.

```bash
gh pr merge <number> --merge
```

Pull the merges down and review what is going out:

```bash
git checkout dev
git pull origin dev
git log --oneline --first-parent main..dev
```

### Step 2 — Bump the version in `package.json`

`package.json` is the **single source of truth** for the release version. The release script reads it and substitutes it into every file that carries a version placeholder (main plugin file, readme template, translation template, updater manifest, …). Do not hand-edit version numbers anywhere else, and leave the `#..._VERSION` placeholders in the repo intact.

```jsonc
{
  "version": "1.11.3", // <- only place you edit
}
```

### Step 3 — Add the changelog entry

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

### Step 4 — Build the release

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

Needs on PATH: `jq`, `zip`, `composer`, `yarn`, `wp` (WP-CLI). On the WPSubscription bench, `./wps release free` or `./wps release pro` runs the same script inside the container.

> **`readme.txt` is generated.** Every release build overwrites it. Never edit it directly — edit `readme-template.txt` (description, tags, screenshots, tested-up-to, …) and let the build produce `readme.txt`. Changelog content comes from `changelog.txt`, not from the template.

Install the zip on a clean site and check that it activates before going further.

Commit the release changes on `dev` (build output in `releases/` stays out of git):

```bash
git add package.json changelog.txt readme.txt languages/
git commit -m "release: 🎉 v1.11.3"
git push origin dev
```

### Step 5 — Merge `dev` into `main`

Open a PR from `dev` to `main`. This is the recommended path: it leaves a reviewable record of the release and runs CI.

```bash
gh pr create --base main --head dev --title "Release v1.11.3"
gh pr merge <number> --merge
```

The PR description is the changelog for this release, **copied from the generated `readme.txt`** (WordPress readme format):

```
= 1.11.3 - Sep 6, 2026 =
-   new: Relative ordering of renewal orders.
-   fix: Subscription list filters.
```

A direct merge is allowed when the release has already been reviewed on `dev`. Always use `--no-ff`, so every release is one merge commit on `main`:

```bash
git checkout main
git pull origin main
git merge --no-ff dev -m "Release v1.11.3"
git push origin main
```

Always merge — never squash or rebase `dev` into `main`. A merge keeps `main` a strict ancestor of `dev`, so the next release merges cleanly without bringing `main` back into `dev`.

### Step 6 — Tag and publish

Tag the merge commit on `main` with the bare version, no prefix (`1.11.3`, not `v1.11.3`), and attach the zip from step 4:

```bash
git checkout main
git pull origin main
gh release create 1.11.3 releases/<plugin>_v1.11.3.zip \
  --target main --title "v1.11.3" --notes-file <changelog-block>
```

Then complete the plugin's [Distribution](#6-distribution) steps.

---

## 3. Hotfixes

A fix that cannot wait for the next release goes straight to `main`:

1. Branch from `main`: `git checkout -b fix/<name> origin/main`.
2. Open the PR with base `main`, merge it, then follow steps 2–6 on `main` with a patch version.
3. Merge `main` back into `dev` so the fix and the version bump are not lost:

   ```bash
   git checkout dev && git pull origin dev
   git merge origin/main
   git push origin dev
   ```

---

## 4. Checklist

```
[ ] Selected PRs reviewed and merged into dev; everything else left open
[ ] Version bumped in package.json (and only there)
[ ] changelog.txt entry added at the top, correct date + version, `* tag: description` lines
[ ] yarn release ran clean, and the zip installs and activates
[ ] readme.txt regenerated (not hand-edited)
[ ] package.json + changelog.txt + readme.txt + languages/ committed and pushed to dev
[ ] dev merged into main (PR or --no-ff merge)
[ ] Tag X.Y.Z created on main, zip attached to the GitHub release
[ ] Distribution steps done (see below)
```

---

## 5. Notes

- Never release from a work branch, and never commit directly to `main` except through a hotfix.
- Never edit generated files (`readme.txt`, built assets, `.pot` output) by hand.
- If a PR merged into `dev` turns out to be broken, revert it on `dev` rather than delaying the release.
- Updating anything on the `main` branch triggers the `Update Plugin Assets/Readme` action. It checks any changes in the `readme.txt` and publishes them to WordPress Org. With this flow that happens only at release time.

---

## 6. Distribution

- After merging `dev` into `main`, create a release tag on `main`.
  > Make sure you do not add any prefix to the version tag. E.g.: `2.0.1`.
- Add the file generated when you ran `yarn release` to the GitHub release draft.
- After publishing the release, the new version will be automatically synced to the WordPress Org.
