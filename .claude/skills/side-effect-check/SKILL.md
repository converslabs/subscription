---
name: side-effect-check
description: >
  Use this skill after you fix a bug or implement a feature.
  Trigger whenever the user asks about side effects.
---

# Side Effects Check — subscription plugin

**1. subscrpt\_\* hook contract**
Check whether any existing hook was renamed, removed, had its parameters changed, or had its firing order changed. The Pro plugin depends on these hooks — any change silently breaks Pro. If a hook must change, keep the old version firing (with a deprecation notice) for at least one major version.

**2. Backwards compatibility**
Check whether any public-facing API changed: constants (`SUBSCRPT_*`), global functions (`subscrpt_*`), or class method signatures in the public namespace. If yes, add legacy aliases in `includes/LegacyCompat.php` and note it in the changelog.

**3. Template / frontend impact**
Check whether any change affects customer-facing templates (`templates/myaccount/`, `templates/emails/`) or the My Account subscription pages. If templates were changed, note that site owners who have overridden templates in their theme will need to update them.
