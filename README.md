# CMS

A small, self-hosted CMS for a personal site: posts written in Markdown, shaped by templates you define, published as a fast server-rendered site, with a control panel to write and look after them.

- **Templates** define a kind of post (recipes, notes, projects…): its fields, a [Mustache](https://mustache.github.io/) layout, a color and an address (`/recipes/...`). Fields can be text, Markdown, numbers, dates, yes/no, lists, select boxes, links, images, galleries or links to other posts.
- **Markdown** with extras: callouts (`> [!TIP]`), keyboard keys (`[[Ctrl]]+[[C]]`), figures with captions, emoji shortcodes, footnotes and syntax highlighting. Raw HTML is shown as text.
- **Widgets** inside Markdown, which still read well without JavaScript: `{{ timer:10m }}`, `{{ stopwatch }}`, `{{ countdown:2026-12-25 }}`, `{{ temp:180c }}`, `{{ recipe-servings:4 }}` with `{{ recipe-amount:200 g }}` that scale together, `{{ spoiler:… }}`, `{{ copy:… }}`, `{{ qr:… }}` and `{{ youtube:… }}`.
- **The site**: home page, a page per template and per tag, search, RSS feeds, sitemap, share images for link previews, a table of contents, related posts and light/dark themes.
- **The control panel** (`/cp`): editor with live preview and local autosave, revisions with diffs, media library with resized WebP copies, tags, sidebar links and profiles, trash, users with admin/editor roles, and a dashboard with privacy-friendly stats (views, searches, clicks and referrers: daily counts only, nothing about the visitor).

## Stack

- PHP 8.4+, [Laravel 13](https://laravel.com), SQLite
- Public site: Blade views, Tailwind CSS 4 and a little TypeScript
- Control panel: [Inertia](https://inertiajs.com) with Vue 3 and TypeScript, routes shared with [Wayfinder](https://github.com/laravel/wayfinder)
- Built with Vite+ (`vp`): bundling, linting, formatting and tests
- Tests: [Pest](https://pestphp.com) for PHP, Vitest (through `vp test`) for the frontend

## Requirements

- **PHP 8.4.1 or newer** with the `gd` (with WebP), `pdo_sqlite`, `iconv`, `exif`, `fileinfo` and `openssl` extensions
- Composer 2
- Node 22 or newer, with npm

On Arch-based systems, `gd` and `pdo_sqlite` come in the `php-gd` and `php-sqlite` packages, and `gd`, `pdo_sqlite`, `iconv` and `exif` need to be uncommented in `/etc/php/php.ini`. Check with `php -m`, or `composer check-platform-reqs`.

## Getting started

```sh
composer setup
php artisan app:create-user
composer dev
```

`composer setup` installs the dependencies, creates `.env` and its key, runs the migrations (creating the SQLite database, `database/database.sqlite`) and builds the frontend. `app:create-user` asks for the first admin; other people are invited from the control panel's users page.

`composer dev` starts the web server, Vite, a queue worker and the log viewer together. The site is at <http://localhost:8000> and the control panel at <http://localhost:8000/cp>.

## Commands

| Command                          | What it does                                                               |
| -------------------------------- | -------------------------------------------------------------------------- |
| `composer dev`                   | Run the site with hot reloading                                            |
| `composer test`                  | PHP style (Pint), static analysis (PHPStan), PHP tests                     |
| `composer ci:check`              | Everything CI runs: the above, plus the frontend checks and tests          |
| `npm test`                       | Frontend tests (`npm run test:watch` to keep watching)                     |
| `npm run check`                  | Frontend formatting and lint (`check:fix` to fix)                          |
| `npm run types:check`            | TypeScript and Vue type checks                                             |
| `composer lint`                  | Fix PHP style                                                              |
| `npm run build`                  | Build the frontend for production                                          |
| `php artisan app:image-variants` | Make resized copies of images that lack them (`--all` to redo every image) |

## Tests

- PHP tests are in `tests/Feature` and `tests/Unit` and run against an in-memory SQLite database. Pages render the built assets, so run `npm run build` once before `php artisan test` on a fresh checkout.
- Frontend tests are in `tests/js`, mirroring `resources/js`. They run in a simulated browser (happy-dom): the widgets are tested against the same HTML that `app/Cms/Widgets` writes, so keep the two in step when changing either.

## Project layout

```
app/Cms/                  The CMS itself: Markdown, widgets, layouts, images, stats, search…
app/Http/Controllers/Cp/  Control panel (Inertia pages)
app/Http/Controllers/Site/ Public site (Blade views)
resources/js/pages/cp/    Control panel pages (Vue)
resources/js/widgets/     Widget behavior on the site and in previews
resources/js/site.ts      Small enhancements for the public site
resources/views/site/     Public site templates
routes/web.php            Site routes; routes/site.php has the catch-all post routes
routes/cp.php             Control panel routes
```

Public post addresses are catch-all routes (`/{template}/{post}`), registered last, so a template handle can not be one of the site's own paths. Those are listed in `Template::RESERVED_HANDLES`: add new top-level routes there too.

## Deploying

The site runs on a single server, with SQLite and files on disk.

**First time:**

1. Clone the repository and point the web server's document root at `public/`.
2. Create `.env` from `.env.example` and set at least `APP_NAME`, `APP_URL`, `APP_ENV=production` and `APP_DEBUG=false`. Then run `php artisan key:generate`.
3. Run `./deploy.sh` (below), then `php artisan app:create-user`.

**Updates:** run `./deploy.sh` on the server. It puts the site in maintenance mode, pulls the branch, installs dependencies, builds the frontend, migrates the database, makes resized images, rebuilds the caches and fixes file permissions for the web server. If a step fails, the site stays in maintenance mode so visitors never see a half-updated site; fix the error and run it again. `PHP_BIN`, `WEB_USER` and `BRANCH` can be overridden, see the top of the script.

There is no scheduler or queue worker to run. Expired items in the trash are deleted when the dashboard or the trash is opened.

**Back up** `database/database.sqlite` and `storage/app/public` (the uploaded images). Use `sqlite3 database/database.sqlite ".backup backup.sqlite"` rather than copying the file while the site is running.
