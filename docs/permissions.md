# Permissions — Request Visibility

[← Back to README](README.md)

---

## Overview

Request visibility is controlled entirely by `SfpRequest::scopeVisibleTo()` in
`src/Models/SfpRequest.php`. Every staff list and detail view passes through this
scope, so a user can never see a request the scope excludes.

Four database-stored settings (in the `settings` table) drive the behaviour:

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `requests_visibility_open_access` | boolean | `0` | Bypass all scoping — every staff user sees every request |
| `requests_visibility_strict_groups` | boolean | `1` | Require selector-group pairing for SFP requests |
| `assignment_enabled` | boolean | `0` | Let assignees see their assigned requests even when scoping would block them |
| `ill_selector_group_id` | integer | *(auto)* | ID of the selector group that grants ILL queue access (Staff → Settings shows a name dropdown; stored value is still the ID) |

Settings are cached per-key for one hour (`Setting::get()` uses `Cache::remember`).
Writes via `Setting::set()` bust the relevant cache key.

---

## Decision Tree

The scope evaluates the following rules **in order**. The first match wins.

```
1. null user
   → no rows (WHERE 1 = 0)

2. Resolve to SFP User
   a. $user instanceof Dcplibrary\Sfp\Models\User → use directly
   b. Any other Authenticatable → look up sfp_users by email
   c. No match found:
      - APP_ENV = local → show all (dev convenience)
      - Otherwise       → no rows

3. Admin (role = 'admin')
   → no filter — sees ALL requests (SFP + ILL)

4. Open access ON (requests_visibility_open_access = 1)
   → no filter — any staff user sees ALL requests (SFP + ILL)

5. Assignment override (assignment_enabled = 1)
   → user sees requests assigned to them (assigned_to_user_id = user.id)
     **OR** requests that pass the scoped-access rules below

6. Scoped access (the "normal" path for selectors)
   Evaluated per request_kind:

   a. ILL requests
      - ill_selector_group_id > 0 AND user is a member of that group → visible
      - ill_selector_group_id = 0 (not configured)                   → hidden
      - User is not in the ILL group                                 → hidden

   b. SFP requests
      - strict_groups OFF → all SFP requests are visible
      - strict_groups ON  → only requests matching a group the user belongs to
        (see "Group Pairing" below)
```

---

## Group Pairing (Strict Mode)

When `requests_visibility_strict_groups` is enabled, each `SelectorGroup` defines
an intersection of material types and audiences. A request is visible to a user
only when **a single group** the user belongs to covers **both** the request's
`material_type_id` and `audience_id`.

This is enforced with an `EXISTS` subquery that joins all three pivot tables
(`selector_group_user`, `selector_group_material_type`, `selector_group_audience`)
**on the same `selector_group_id`**. This prevents "bridging" — if a user belongs
to Group A (Books × Adult) and Group B (DVDs × Kids), they will **not** see a
request for Books × Kids, because no single group covers that pair.

```
EXISTS (
  SELECT 1
  FROM   selector_group_user        AS sgu
  JOIN   selector_group_material_type AS sgmt
           ON sgmt.selector_group_id = sgu.selector_group_id
          AND sgmt.material_type_id  = requests.material_type_id
  JOIN   selector_group_audience     AS sga
           ON sga.selector_group_id  = sgu.selector_group_id
          AND sga.audience_id        = requests.audience_id
  WHERE  sgu.user_id = :userId
)
```

### Consequences

- A selector with **no** group memberships sees **no** SFP requests.
- A selector in one group sees only the (material_type × audience) pairs that
  group defines.
- A selector in multiple groups sees the **union** of each group's own pairs,
  but never the cartesian product across groups.

---

## ILL Access

ILL access is **not** a role. It is determined by membership in the selector
group whose ID is stored in `ill_selector_group_id`. That group can be named
anything (e.g. "ILL", "Cathats"). The check is:

```
User::hasIllAccess()
  → isAdmin()  OR  inSelectorGroup(ill_selector_group_id)
```

When `ill_selector_group_id` is 0 or blank, **no** non-admin user can see ILL
requests (the scope injects `WHERE 1 = 0` for the ILL branch).

---

## Assignment Override

When `assignment_enabled` is on, the scope wraps the entire scoped-access
predicate in an `OR`:

```sql
WHERE assigned_to_user_id = :userId
   OR ( <scoped access predicate> )
```

This means a selector can always see a request they are assigned to, even if:
- They are not in any selector group.
- The request's material type / audience is outside their groups.
- The request is ILL and they are not in the ILL group.

---

## Relationship to Other Scopes

| Scope | Model | What it filters |
|-------|-------|-----------------|
| `SfpRequest::scopeVisibleTo` | `SfpRequest` | material_type_id + audience_id + request_kind |
| `Material::scopeVisibleTo` | `Material` | material_type_id only (materials have no audience) |

`Material::scopeVisibleTo` is applied in `TitleController` for the title list,
detail, bulk-status, and merge actions.

---

## Code References

- **Scope implementation**: `src/Models/SfpRequest.php` — `scopeVisibleTo()` and `applyScopedAccess()`
- **User helpers**: `src/Models/User.php` — `isAdmin()`, `hasIllAccess()`, `inSelectorGroup()`
- **Setting model**: `src/Models/Setting.php` — `get()` with cache, `set()` with bust
- **Group model**: `src/Models/SelectorGroup.php` — pivot relationships
- **Seeder defaults**: `database/seeders/SettingsSeeder.php`
- **Integration tests**: `tests/Integration/RequestPermissionsTest.php`
