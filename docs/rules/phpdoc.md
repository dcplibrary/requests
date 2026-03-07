# PHPDoc / DocBlocks rule

## Goal
DocBlocks must be readable by IDEs (PhpStorm, VS Code) and compatible with documentation generators (including Doxygen / phpDocumentor).

## Rules (follow all)
- **Use standard PHPDoc blocks**: `/** ... */` (not `/* ... */` and not `//`).
- **Document intent + contract**. Don’t narrate obvious implementation details.
- **Keep tags accurate and minimal**. Bad PHPDoc is worse than none.
- **Prefer native types first** (parameter/return type hints, typed properties). Use PHPDoc to add detail native types can’t express.

## What to document
- **Public APIs**: all public methods and public classes should have a DocBlock.
- **Non-obvious internals**: protected/private methods only when the behavior or constraints aren’t obvious.
- **Eloquent models**: class DocBlock should include key `@property` / `@property-read` entries that help IDE autocompletion.

## Required tags when applicable
- **`@param`**: required when the type isn’t fully expressed in the signature (e.g. `array`, generics, shapes).
- **`@return`**: required when return type is `array`, `iterable`, `Collection`, or otherwise benefits from generics/shapes.
- **`@throws`**: required when a method intentionally throws (or rethrows) specific exceptions callers should handle.

## Preferred typing patterns (IDE-friendly)
- **Generics for collections** (when helpful):
  - `@return \Illuminate\Support\Collection<int, \Dcplibrary\Sfp\Models\SfpRequest>`
- **Array shapes** (when returning associative arrays with known keys):
  - `@return array{total:int, results:list<array{bib_id:string, title:string}>}`
- **Lists**:
  - `list<string>` / `list<int>`
- **Associative arrays**:
  - `array<string, mixed>`
- **Nullable/union**:
  - `string|null` or `string|int` (mirror the real behavior)

## Doxygen compatibility notes
- Use common tags Doxygen understands: `@param`, `@return`, `@throws`, `@deprecated`, `@see`.
- Prefer fully-qualified class names in tags when ambiguity is possible.
- Keep one logical statement per line inside DocBlocks for clean rendered output.

## phpDocumentor + GitHub Pages setup (project/package recipe)

This repository uses **GitHub Actions + GitHub Pages** to publish API docs as static HTML.

### Files to add
- **`phpdoc.dist.xml`** at the repo root (source paths, ignore paths, output folder)
- **`.github/workflows/phpdoc.yml`** to build and deploy to Pages
- **`.gitignore`** entry for `build/phpdoc/`

### Why this uses a PHAR in CI (not a Composer dev dependency)
In package repos like this one, `phpunit/phpunit` pulls in newer Symfony components (e.g. `symfony/console` v7+). Current phpDocumentor releases require older Symfony constraints, which can make Composer installs conflict.

Using the official **PHAR** in CI avoids dependency conflicts while still generating the same docs output.

### GitHub Pages settings (one-time, in the repo UI)
In the repository’s **Settings → Pages**:
- Set **Source** to **GitHub Actions**

After the workflow runs on `main`, the docs site is published at the repository Pages URL (typically `https://<org>.github.io/<repo>/`).

### Running locally (optional)
If you want to generate docs locally without adding Composer dependencies, you can also use the PHAR:

```bash
curl -LsS -o phpDocumentor.phar https://phpdoc.org/phpDocumentor.phar
php phpDocumentor.phar -c phpdoc.dist.xml
open build/phpdoc/api/index.html
```

## Examples

```php
/**
 * Scope: only requests the given user is authorized to see.
 *
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
{
    // ...
}
```

