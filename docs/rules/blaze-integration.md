# Livewire Blaze integration (packages)

## Goal
Use `livewire/blaze` to speed up **anonymous Blade component** rendering (especially in Livewire-heavy UIs) without making Blaze a required dependency of the package.

This repository is a **Laravel package** installed via Composer in host apps. Blaze must be treated as a **host-app opt-in optimization**.

## Rules (follow all)
- **Do not add** `livewire/blaze` to the package `require` section unless explicitly requested.
- **Do enable Blaze conditionally** (only when the class exists), so consuming apps that don’t install Blaze keep working.
- **Only target anonymous component directories** first (e.g. `resources/views/components`). Start narrow, expand later.
- **Use the default compiler only** initially (`compile: true` by default). Do **not** enable `memo` or `fold` unless explicitly requested and validated.
- **Never add `use Livewire\Blaze\Blaze;` imports** in package code unless Blaze is a hard dependency. Prefer fully-qualified references behind a `class_exists()` guard.

## Package service provider pattern
Add a `registerBlaze()` (or equivalent) method and call it from `boot()` after views are registered.

Template:

```php
protected function registerBlaze(): void
{
    if (! class_exists(\Livewire\Blaze\Blaze::class)) {
        return;
    }

    \Livewire\Blaze\Blaze::optimize()
        ->in(__DIR__ . '/../resources/views/components');
}
```

## Operational notes
- **After installing Blaze in a host app**, clear compiled views:

```bash
php artisan view:clear
```

- **Scope**: only include directories that contain components rendered via `<x-...>` tags.

## Compatibility constraints (don’t ignore)
Blaze targets anonymous components and trades some Blade lifecycle behavior for speed. Avoid enabling Blaze on templates that depend on:
- Class-based Blade components
- View composers / creators / view lifecycle events for those components
- The `$component` variable

If issues appear, exclude the problematic subdirectory (Blaze supports per-directory opt-out) and keep the rest enabled.

