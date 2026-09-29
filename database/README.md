# KP Memos Database

Run in filename order by `php bin/install.php`, which also creates the
restricted application account.

- `01-schema.sql` — tables (idempotent)
- `10-*.sql` … `50-*.sql` — stored procedures (dropped and recreated on every run)

The application account is granted `EXECUTE` on the schema and nothing
else. Every procedure runs with its definer's rights (the account that ran
the installer), so the application can never read or write a table except
through a `kpm_` procedure.
