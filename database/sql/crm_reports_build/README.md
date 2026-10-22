# CRM Reports deploy build

`apply_crm_reports_v1.php` (in `database/sql/`) is generated, never edited by hand:

    php database/sql/crm_reports_build/build_crm_patch.php

- `crm_template.php` – the deploy machinery (dry run / --apply / --rollback, backup, undo, boot probe, migrations).
- `crm_edits.php` – the exact find/replace pairs for the shared files (routes, menu, lead page, meeting forms, providers).
- `ar_crm_reports.json` – Arabic strings appended to `resources/lang/ar.json` (textually, never re-encoded).
- `check_anchors.php` – every anchor must match once in `git show HEAD:` and in `pulled_prod/`.
- `make_fake_baseline.php` – turns a copy of the tree back into "server before the patch" for testing the script.

A v2 must be a new file name and must treat the v1 result as the server baseline.
