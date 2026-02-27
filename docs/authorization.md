# Authorization

[← Back to README](README.md)

---

## Roles

| Role | Access |
|------|--------|
| `admin` | Full access to all requests, patrons, titles, settings |
| `selector` | Scoped to their assigned SelectorGroups |

Role is set per-user in the `sfp_users` table and editable via **Settings → Users**.

---

## Selector Groups

A `SelectorGroup` defines the intersection of material types and audiences that a selector can see. A selector must be in a group that covers **both** the request's material type and audience for it to appear.

```
SelectorGroup "Adult Fiction"
  ├── material_types: [Book, eBook, Audiobook]
  └── audiences:     [Adult]
```

A selector assigned to this group sees requests with a book/ebook/audiobook material type **and** the Adult audience. Requests for children's books would not appear.

Admins bypass group scoping entirely and see all requests.

---

## scopeVisibleTo

The `SfpRequest::scopeVisibleTo(Builder, ?Authenticatable)` scope is applied in `RequestController::index()` and `show()`. Resolution order:

```
1. $user === null
   → whereRaw('1 = 0')  (no rows)

2. $user instanceof Dcplibrary\Sfp\Models\User
   → use directly

3. Other Authenticatable (host app user model)
   → look up SFP user by email

4. No SFP user record found
   → if APP_ENV = 'local': show all (dev convenience)
   → else: whereRaw('1 = 0')  (no rows)

5. SFP user is admin
   → no filter (show all)

6. SFP user is selector
   → WHERE material_type_id IN [...] AND audience_id IN [...]
      (IDs from user's accessible material types and audiences)
```

---

## Patron and Title Visibility

`PatronController` and `TitleController` do not currently apply group scoping — all authenticated staff can see all patrons and titles. Only request visibility is scoped.

---

## RequireSfpRole Middleware

The package automatically registers the `sfp.role` middleware alias and appends it to every staff route. It runs **after** the host app's own auth middleware.

**Resolution order:**

```
1. No authenticated user
   → pass through (let upstream auth redirect to login)

2. Authenticated user with no matching sfp_users record (unknown email)
   → return sfp::staff.no-access view (HTTP 403)

3. sfp_users record exists but role is not 'admin' or 'selector'
   → return sfp::staff.no-access view (HTTP 403)

4. sfp_users record exists but active = false
   → return sfp::staff.no-access view (HTTP 403)

5. Role is 'admin' or 'selector' and active = true
   → proceed
```

The no-access view displays:
> "You do not have access to {app.name}. Please contact the administrator to request access."

---

## Authentication

Authentication is handled by the host application (`sfp-laravel`) via Azure Entra ID (OIDC). The `staff_middleware` config key defaults to `['web', 'auth']` and is applied to all staff routes. The package always appends `sfp.role` after whatever middleware the host app configures.

To customize middleware (e.g. add a role gate):

```php
// config/sfp.php in the host app
return [
    'staff_middleware' => ['web', 'auth', 'can:access-sfp'],
];
```

---

## Local Development

When `APP_ENV=local` and no matching SFP user record exists for the authenticated user, all requests are shown (via `scopeVisibleTo`). This allows testing the UI without needing a fully configured `sfp_users` record.

However, the `sfp.role` middleware still runs in local. To avoid hitting the no-access page, add yourself to `sfp_users` with the appropriate role and set `active = true`.

To get a realistic scoped experience in local dev, use the `selector` role and assign yourself to the relevant `SelectorGroup`.
