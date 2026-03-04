# Admin Docs

## Roles
- **Admin**: Full access, including Settings and lookup tables.
- **Selector**: Scoped access based on selector group assignments.

## Settings
Settings are editable under **Settings → General** and **Settings → Catalog**.

### Popular author auto-order exclusions
- **Setting**: `auto_order_author_exclusions` (one author per line)
- **Message**: `auto_order_author_exclusion_message` (HTML)
- **Behavior**: Only triggers when a title has a confidently-detected future release date (e.g. `YYYY-MM-DD`) and the author matches the exclusion list.

## Queue / background jobs
- Polaris patron lookups run via the queue (`LookupPatronInPolaris`).
- Ensure a queue worker is running in production.

## Data cleanup
- **Requests → Request detail**: Admins can delete an individual request.

