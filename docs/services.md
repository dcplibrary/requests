# Services

[← Back to README](README.md)

All services live in `src/Services/` under the `Dcplibrary\Sfp\Services` namespace.

---

## BibliocommonsService

Searches the BiblioCommons catalog using the internal gateway JSON API (the same endpoint the catalog's React SPA calls via XHR).

### Configuration

| Setting key | Description |
|-------------|-------------|
| `catalog_library_slug` | Subdomain slug, e.g. `dcpl` for `dcpl.bibliocommons.com` |

### Methods

#### `search(string $title, string $author, string $audienceBiblioValue, ?string $year = null): array`

Builds a boolean query and calls the gateway API.

**Returns:**
```php
[
    'results' => [
        [
            'bib_id'      => 'string',
            'title'       => 'string',
            'subtitle'    => 'string',
            'author'      => 'string',
            'format'      => 'string',   // BiblioCommons format code, e.g. 'BK'
            'year'        => 'string',
            'edition'     => 'string',
            'isbns'       => ['string'],
            'jacket'      => '?string',  // Medium jacket URL
            'catalog_url' => 'string',
        ],
        // max 5 results
    ],
    'total'   => int,
    'url'     => 'string', // Human-readable catalog browse URL
]
```

**Search query format:**

```
title:(The Covenant of Water) contributor:(Verghese) audience:"adult" pubyear:[2022 TO 2024]
```

- Only the author's **last name** is used in the `contributor:` filter — avoids mismatches from first-name spelling variations or MARC inversion ("Verghese, Abraham" vs "Abraham Verghese")
- Year filtering only applies to recent titles (within 2 years). Older books may have later editions in the catalog under a different year.
- Results are capped at 5.

**On any error:** returns `['results' => [], 'total' => 0, 'url' => $browseUrl]` and logs a warning/error.

---

## IsbnDbService

Fallback bibliographic source when catalog search returns no results.

### Configuration

| Config key | Description |
|------------|-------------|
| `sfp.isbndb.key` | ISBNdb v2 API key (from `SFP_ISBNDB_KEY` env) |

### Methods

#### `search(string $title, string $author): array`

Two-stage search:

1. **By title** — Search ISBNdb for the title, filter results by author last name.
2. **By author (fallback)** — If stage 1 finds no matches (common when a generic title has hundreds of results), search by author last name and filter by significant title words. Stopwords (`a`, `an`, `the`, `of`, `in`, `me`, `so`, etc.) are excluded from the keyword filter.

**Returns:**
```php
[
    'results' => [
        [
            'isbn'          => '?string',
            'isbn13'        => '?string',
            'title'         => 'string',
            'title_long'    => 'string',
            'authors'       => ['string'],
            'author_string' => 'string',
            'publisher'     => '?string',
            'publish_date'  => '?string',
            'edition'       => '?string',
            'overview'      => '?string',
            'image'         => '?string',
            'binding'       => '?string',
        ],
        // max 5 results
    ],
    'total' => int,
]
```

**On unconfigured key or error:** returns `['results' => [], 'total' => 0]`.

---

## CoverService

Generates book cover image URLs, preferring Syndetics when configured.

### Configuration

| Setting key | Description |
|-------------|-------------|
| `syndetics_client` | Syndetics client ID (e.g. `davia`) |

### Methods

#### `url(?string $isbn, ?string $fallback = null): ?string`

| Condition | Returns |
|-----------|---------|
| ISBN present + `syndetics_client` configured | `https://www.syndetics.com/index.aspx?isbn={isbn}&issn=/LC.JPG&client={client}` |
| No ISBN or no client configured | `$fallback` (or `null` if fallback is empty) |

### Usage in SfpForm

`SfpForm::withCovers(array $results, string $source): array` decorates result arrays with a `cover_url` key by calling this service for each item. Catalog results use `isbns[0]` + `jacket` as fallback; ISBNdb results use `isbn13`/`isbn` + `image` as fallback.

---

## PatronService

Creates or retrieves patron records during form submission.

### Methods

#### `findOrCreate(array $data): array`

| Key | Type | Description |
|-----|------|-------------|
| `barcode` | string | Library card number (lookup key) |
| `name_first` | string | |
| `name_last` | string | |
| `phone` | string | |
| `email` | string\|null | |

**Returns:** `['patron' => Patron, 'created' => bool]`

For new patrons, dispatches `LookupPatronInPolaris::dispatch($patron->id)` to the queue immediately after creation.

Existing patrons are returned as-is; their data is not updated on subsequent submissions (Polaris data may have already been applied).
