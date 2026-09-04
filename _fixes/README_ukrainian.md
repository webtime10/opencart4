# Fix: Ukrainian language not in Localisation → Languages

## Root cause

OpenCart 4 Installer **only extracts files**. The row in `oc_language` is created only when you click **Install** under **Extensions → Extensions → Languages**.

On this shop, `extension_install` was status=1 (files unpacked), but:
- `oc_extension` had no `language/ukrainian` row
- `oc_language` had no `uk-ua`

Also bugs in `admin/controller/language/ukrainian.php`:
1. Silent `return` on permission check inside `install()` while parent still returns success
2. `editLanguage()` called with 2 args but defined with 1 (PHP 8 ArgumentCountError on reinstall)
3. Wrong `extension` value: `language_ukrainian_4100uk_ua`
4. Broken `glob(DIR_EXTENSION)` path for copying opencart translations

## What was done

1. Added `uk-ua` via `model_localisation_language->addLanguage()` (script `_fixes/add_ukrainian_language.php`)
2. Patched controller (`ensureUkrainianLanguage()` on install/index/save)
3. Registered extension + settings

## Manual values (if creating by hand)

| Field | Value |
|-------|--------|
| Name | Українська |
| Code | uk-ua |
| Locale | uk_UA.UTF-8,uk_UA,ukrainian |
| Extension | language_ukrainian_4100 |
| Sort Order | 0 |
| Status | Enabled |

No `Directory` field in OC 4.1 — path is `extension/{extension}/…/language/{code}/`.

## Correct install steps (OC 4.1.0.3)

1. Extensions → Installer → Upload → Install package
2. Extensions → Extensions → type **Languages** → **Install** on «Українська локалізація»
3. Edit → enable statuses → Save
4. System → Localisation → Languages — «Українська» must be listed
5. System → Settings → Local — select Ukrainian if needed
