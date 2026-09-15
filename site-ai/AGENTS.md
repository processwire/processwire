# AI Starter site profile – conventions

This site was installed from ProcessWire’s `site-ai` profile. This file describes its
structure and conventions for AI agents (and people) building on it. Follow these
conventions unless the site owner asks otherwise. For the ProcessWire API itself, see
`/AGENTS.md` in the installation root and the `API.md` files in `/wire/`.

## Output strategy: Markup Regions

`/site/config.php` enables Markup Regions, with `/site/templates/_init.php` prepended and
`/site/templates/_main.php` appended to every template file.

- `_main.php` defines the complete HTML document.
- A template file outputs only the regions it changes. An element with an `id` that
  matches an element in `_main.php` replaces it. Use `pw-append`, `pw-prepend`,
  `pw-before`, `pw-after` or `pw-remove` attributes to do otherwise.
- Do not output a full `<html>` document from a template file, and do not mix in another
  output strategy (such as delayed output with `$content` variables).
- Docs: <https://processwire.com/docs/front-end/output/markup-regions/>

Region ids defined in `_main.php`:

| id | Element | Default content |
|---|---|---|
| `html-head` | `<head>` | Meta tags, title, stylesheets, script |
| `html-body` | `<body>` | Entire body |
| `site-header` | `<header>` | Site name and main navigation |
| `site-nav` | `<nav>` | Homepage and its visible children |
| `breadcrumbs` | `<nav>` | Parent page links (not on the homepage) |
| `page-header` | `<header>` | `h1#headline` title and the `summary` field |
| `headline` | `<h1>` | Page title |
| `content` | `<div>` | The `body` field |
| `site-footer` | `<footer>` | Copyright and edit link |

For example, to add stylesheet only on one template, output
`<link rel="stylesheet" href="…" pw-append="html-head">` from that template file.

## Files

| File | Purpose |
|---|---|
| `templates/_init.php` | Prepended to template files. Shared variables (`$home`). Included on every render, so never declare functions here. |
| `templates/_func.php` | Shared functions, included once from `_init.php`. |
| `templates/_main.php` | Document shell and region definitions. |
| `templates/home.php` | Homepage template file. |
| `templates/basic-page.php` | Basic page template file. |
| `templates/styles/base.css` | Foundation stylesheet. Avoid editing; see CSS below. |
| `templates/styles/main.css` | Site design tokens and components. |
| `templates/scripts/main.js` | Site scripts, loaded with `defer`. |
| `templates/admin.php` | Admin controller. Admin-only hooks go above the `require`. |
| `classes/HomePage.php`, `classes/BasicPagePage.php` | Custom Page classes. |
| `init.php`, `ready.php` | Site-wide hooks and bootstrap code. |

## Fields and templates

| Field | Type | Notes |
|---|---|---|
| `title` | `FieldtypePageTitle` | Entity-encoded for output. |
| `summary` | `FieldtypeTextarea` | Plain text (`contentType` 0), entity-encoded for output. Used in page headers, meta descriptions and listings. |
| `body` | `FieldtypeTextarea` | Rich text via `InputfieldTinyMCE` (`contentType` 1, HTML Purifier on). Output as HTML. |
| `image` | `FieldtypeImage` | Single image: `maxFiles` 1, `outputFormat` 2, so the value is a `Pageimage` or `null`. |

Templates `home` and `basic-page` both use `title`, `summary`, `body` and `image`.

When creating fields:

- Use `InputfieldTinyMCE` with `contentType` 1 for rich text. Never CKEditor.
- Add `TextformatterEntities` to plain text fields that are output in markup.
- Set `outputFormat` explicitly on file and image fields: 2 for a single value, 1 for
  multiple. Name single-value fields in the singular (`image`) and multi-value fields in
  the plural (`images`).

## Page classes

`$config->usePageClasses` is enabled. A template named `blog-post` automatically uses
class `BlogPostPage` in `/site/classes/BlogPostPage.php` when that file exists. Page
classes extend `Page` and should document their fields with `@property` annotations.
Put logic specific to a template’s pages in its Page class rather than in template files.

## CSS

- `base.css` is the foundation: reset, typography, layout primitives, site structure,
  `.prose` for rich text, and basic components. Everything in it uses design tokens
  (CSS custom properties).
- `main.css` loads after it. Give a site its own look by overriding tokens in `main.css`
  (colors, fonts, type scale, spacing, radius, widths), then add components below them.
- Layout primitives: `.container` (centered column), `.stack` (vertical spacing, set
  `--stack-space`), `.cluster` (wrapping row, `--cluster-space`), `.grid` / `.card-grid`
  (responsive columns, `--grid-min`, `--grid-space`).
- Components: `.card`, `.card-title`, `.button`, `.subnav`, `.page-image`, `.skip-link`,
  `.visually-hidden`.
- No CSS framework or build step is required. Keep pages usable at narrow and wide widths.

## JavaScript

Vanilla JavaScript in `scripts/main.js`, loaded with `defer`. Enhance progressively:
every page must work without JavaScript.

## Hooks

- Hooks affecting both the front end and the admin: `/site/ready.php` or `/site/init.php`,
  or a file included from either.
- Front-end-only hooks: `/site/templates/_init.php` (or `_func.php` for functions).
- Admin-only hooks: `/site/templates/admin.php`, above the `require` line.
- Do not create a module just to hold hooks. Create a module only when the site needs
  module features such as configuration, install/uninstall, permissions or a reusable API.

## Output safety

- `title` and `summary` are already entity-encoded by `TextformatterEntities` when
  output formatting is on (the default on the front end). Do not encode them again.
- `body` is HTML (purified on input). Output it directly.
- Entity-encode any other text that is not already encoded, e.g. with
  `$sanitizer->entities()`, and anything from user input.

## Images

Image fields may be empty. Always output images through `siteImage()` in `_func.php`,
never with direct `<img>` markup, so a missing image never causes errors, warnings or
broken `<img>` elements:

```php
<?= siteImage($page->image, 1200, 'page-image') ?>
```

`siteImage()` returns a `<figure>` for a `Pageimage` (or the first image of a
`Pageimages`). When there is no image, it returns a placeholder `<figure>` with the same
class plus `image-placeholder`: a blurred, token-colored area with an "Image" label, so
site owners can see where to add images. Placeholders are controlled by
`$config->siteImagePlaceholders` in `/site/config.php`:

- `true`: show placeholders to everyone (default for a new site)
- `'editors'`: show only to users who can edit the page
- `false`: never show them (`siteImage()` returns a blank string)

Design layouts to look right both with images and with placeholders. When placeholders are
disabled, image areas collapse, so layouts must also work without the image element.
Placeholders use a 3:2 aspect ratio; set `--placeholder-ratio` on the figure's class to
change it (e.g. `.hero { --placeholder-ratio: 16 / 9; }`).
