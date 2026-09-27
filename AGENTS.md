# Digital Asset Tag Portal - working notes for agents

## Identity

- Product: Digital Asset Tag Portal (repo `NFC`)
- Source of truth for scope: `Digital_Asset_Tag_Portal_Architecture_MVP.md`
- Sibling project that defines the house conventions:
  `/Users/LinkTec/hydraulic/LinkTech-hydraulic`
- Target: same LAMP server and domain as the sibling project, own interface

## Stack rules

- PHP 7.4+ compatible, MySQL/MariaDB via PDO, no Composer, no build step.
- Every PHP entry point starts by defining `LINKTEC_SECURE` and requiring
  `includes/bootstrap.php`.
- Tables are prefixed `dat_` and created only through `config/schema.sql` plus
  `scripts/migrate.php`. The schema stays idempotent.
- Never store plaintext passwords. Never copy the plaintext-password pattern
  from the sibling project's user tables.
- Ship CSS in `assets/css/portal.css`; do not introduce a utility-CSS CDN, it
  would force `unsafe-eval` into the Content-Security-Policy.
- Icons come from Font Awesome (already loaded); emoji are reserved for the
  asset-type marks that the architecture document specifies.

## Domain rules

- `Asset` is the core abstraction. Tags (QR, NFC, later RFID) are separate
  entities pointing at the same stable public ID.
- The public page is rendered from `dat_public_asset()`. Never pass a raw asset
  row to a public view.
- Field visibility is declared in `dat_asset_types()`; private fields must never
  reach a public template.
- Deleting is a soft delete. `dat_asset_is_public()` decides what a tag page
  shows for each status.

## Workflow

```bash
# syntax check every changed file
php -l path/to/file.php

# full check (no database needed)
php tests/smoke.php

# with a running server
php -S localhost:8080 router.php &
php tests/smoke.php --http=http://localhost:8080
```

- Run the smoke suite before committing. It validates the QR encoder against
  known capacity values, verifies Reed-Solomon syndromes, checks the public ID
  alphabet and confirms that private metadata stays out of the public
  projection.
- Keep `includes/lang/en.php` and `includes/lang/de.php` key-identical.
- No real credentials, tokens or customer data in code, docs or tests.
