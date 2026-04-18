# Syncing saint `is_group` / `sex` to production (SQL updates)

Use this when you have filled **`is_group`** and **`sex`** on **local** PostgreSQL and want to apply the same values on **production** without restoring the whole database.

Each generated statement updates **only** rows where **both** **`id`** and **`name`** match. If production renamed a saint or IDs diverged, that row updates **0** rows and is safe (no wrong row updated).

## Before you start

1. **Back up production** (snapshot or `pg_dump`).
2. Confirm **production schema** already has columns `is_group` and `sex` (run migrations there first).
3. This guide assumes **PostgreSQL** and table **`saint`**, columns: `id`, `name`, `is_group` (boolean), `sex` (`male` | `female` | `unknown`).

## Step 1 — Generate `UPDATE` statements from local

Connect to your **local** database (adjust user/db/host). Examples:

- From host, DB published on 5432:  
  `psql "$DATABASE_URL" -f ...`  
  or  
  `psql -h 127.0.0.1 -U app -d app`
- From Docker (typical compose service `database`):  
  `docker compose exec database psql -U app -d app`

Run **one** of the queries below. They print one `UPDATE` per line to stdout.

### Option A — Only rows you likely changed (smaller file)

Skips rows that still look like defaults (`sex = unknown` and `is_group = false`).

```sql
SELECT 'UPDATE saint SET is_group = ' || is_group::text
    || ', sex = ' || quote_literal(sex::text)
    || ' WHERE id = ' || id::text
    || ' AND name = ' || quote_literal(name)
    || ';'
FROM saint
WHERE sex::text <> 'unknown' OR is_group = true
ORDER BY id;
```

### Option B — Every saint (full mirror of demographics)

```sql
SELECT 'UPDATE saint SET is_group = ' || is_group::text
    || ', sex = ' || quote_literal(sex::text)
    || ' WHERE id = ' || id::text
    || ' AND name = ' || quote_literal(name)
    || ';'
FROM saint
ORDER BY id;
```

### Save to a file

Example (local shell):

```bash
docker compose exec -T database psql -U app -d app -Atq -c "
SELECT 'UPDATE saint SET is_group = ' || is_group::text
    || ', sex = ' || quote_literal(sex::text)
    || ' WHERE id = ' || id::text
    || ' AND name = ' || quote_literal(name)
    || ';'
FROM saint
WHERE sex::text <> 'unknown' OR is_group = true
ORDER BY id;
" > saint_demographics_updates.sql
```

- `-Atq`: tuples only, no headers, quiet (clean SQL lines).
- Use **Option B** query inside `-c "..."` if you want the full set.

Open `saint_demographics_updates.sql` and confirm line count and a few samples look right.

## Step 2 — Review the file

- **`name` must match exactly** the value stored in production (same spelling, spacing, punctuation). `quote_literal` handles apostrophes in names.
- If local and prod **IDs differ** (different DB histories), **every** `UPDATE` will affect **0 rows** until you regenerate using a **name-only** strategy (not covered here). The id+name guard is intentional.

## Step 3 — Execute on production (transaction)

1. Copy `saint_demographics_updates.sql` to a safe place (never commit secrets).
2. Connect to **production** `psql` as a user allowed to `UPDATE saint`.
3. Run inside a transaction so you can roll back:

```sql
BEGIN;
\i /path/to/saint_demographics_updates.sql
-- optional: check a few rows
SELECT id, name, is_group, sex FROM saint WHERE id IN (1,2,3);
ROLLBACK;   -- first dry run: verify no errors, then re-run with COMMIT
```

When satisfied:

```sql
BEGIN;
\i /path/to/saint_demographics_updates.sql
COMMIT;
```

Or paste the file contents into your SQL client and wrap with `BEGIN;` / `COMMIT;`.

## Step 4 — Verify

```sql
SELECT COUNT(*) FROM saint WHERE sex::text = 'unknown' AND is_group = false;
-- compare rough counts to expectations

SELECT id, name, is_group, sex FROM saint ORDER BY id LIMIT 20;
```

Optionally compare **counts by sex**:

```sql
SELECT sex::text, COUNT(*) FROM saint GROUP BY sex ORDER BY 1;
```

## Troubleshooting

| Symptom | Likely cause |
|--------|----------------|
| Many updates, **0 rows** affected | Prod `id` or `name` differs from local; align data or use a different key strategy. |
| Error on `sex` | Prod enum/column type mismatch; run migrations. |
| Duplicate `name` in prod | Two rows same name; id+name still picks one row per statement; fix duplicates or hand-edit SQL. |

## Security

- Do not store production credentials or dumps in the git repo.
- Prefer running SQL over VPN/SSH to prod, not from a shared machine.
