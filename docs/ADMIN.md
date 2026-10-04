# Administration

`/admin/` reads every table in the portal. It is gated on a role stored on an
account, not on a hidden URL, and the panel itself cannot write: there is no
insert, update or delete anywhere in `includes/admin.php`.

## Creating the account

Run this on the server, from the project root:

```sh
php scripts/create-admin.php --email=you@example.com
```

It prints a generated password once. Nothing in the system can read a password
back, so put it in a password manager before you close the terminal.

```sh
php scripts/create-admin.php --list                       # who exists, and their role
php scripts/create-admin.php --email=you@example.com --reset   # new password
php scripts/create-admin.php --email=you@example.com --password='...'
```

Promoting an existing account keeps its password; `--reset` replaces it. The
administrator signs in through the ordinary login form, and the dashboard then
shows an extra link to the panel.

## What it shows

- `/admin/` lists every table with its row count and column count
- `/admin/table.php?name=dat_messages` shows the rows, newest first, 50 at a
  time, with a plain text filter across the columns

Everything is escaped on the way out and table names are checked against what
the server reports before they are used in a query, because MySQL does not
accept placeholders for identifiers.

## Handling

The rows are raw. `dat_users` holds password hashes, `dat_messages` holds the
anonymous tokens that let a finder reopen their own thread, and `dat_login_logs`
holds hashed IP addresses. Treat the whole panel as confidential:

- use a password for this account that you use nowhere else
- do not share the session, and sign out on a shared machine
- if the password leaks, run `--reset` immediately

## Cloudflare and email addresses

Cloudflare's email obfuscation rewrites anything that looks like an address
into a link that its own script has to decode. The site's
Content-Security-Policy blocks that script, so the address would arrive broken.
The row table is therefore wrapped in Cloudflare's documented `<!--email_off-->`
markers. If addresses ever start arriving as `__cf_email__` links again, those
markers are what to check.
