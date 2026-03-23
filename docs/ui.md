# UI conventions

## Icons

- Use **Heroicons (outline)** for all icons in the SFP UI.
- Prefer consistent sizing (`w-4 h-4` or `w-5 h-5`) and accessible gray on white UI surfaces (default `text-gray-500`, hover `text-gray-700`).
- Avoid mixing icon sets/styles within the staff UI.

### Request status icon picker

- Canonical list: `RequestStatusIconCatalog::solidLabels()` (solid key → label).
- Each solid has a matching `*-outline` name; outline SVG paths live in `HeroiconsOutlinePaths`.
- The picker grid is **A–Z by label**; for each icon it shows **solid then outline** so pairs stay together (includes **Bell** and **Bell Alert** / `bell-alert`).
- When adding an icon: extend the catalog, add solid paths in `status-icon.blade.php`, add outline paths in `HeroiconsOutlinePaths`, and run `RequestStatusIconOutlineParityTest`.

