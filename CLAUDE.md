# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

WordPress plugin "Inline Google Spreadsheet Viewer" (slug `inline-google-spreadsheet-viewer`, text domain `inline-gdocs-viewer`). It provides a `[gdoc key="..."]` shortcode (and an oEmbed handler for bare Google Sheets / Apps Script URLs) that renders data from Google Sheets, CSV files, Google Apps Script web apps, or SQL databases as HTML tables (enhanced with DataTables) or Google Charts, and embeds other documents via the Google Docs Viewer.

There is no build step. All code is plain PHP and browser JavaScript loaded directly by WordPress. Front-end libraries (DataTables 3 and its extensions, JSZip, pdfmake) are bundled unmodified in [assets/vendor/](assets/vendor/) (see its README for versions and licenses). Only the Google Charts loader comes from Google's servers.

## Commands

Development uses Docker (wp-env), Node 20+, and Composer. On Windows Git Bash, set `MSYS_NO_PATHCONV=1` before `wp-env run` commands, or container paths get rewritten.

```sh
composer install && npm install && npx playwright install chromium
npm run env:start     # WordPress 7.x on PHP 8.3: dev site :8888, test site :8889
npm run test:php      # PHPUnit (tests/phpunit/) in the tests-cli container
npm run test:e2e      # Playwright (tests/e2e/plugin.spec.js) against :8888
IGSV_LIVE_SHEET=<public sheet URL> npm run test:e2e:live   # live Google tests, run by hand
composer lint         # PHPCS: WordPress.Security + PHPCompatibilityWP (PHP 7.4+)
```

- Single test: `npx wp-env run tests-cli --env-cwd=wp-content/plugins/inline-gdocs-viewer vendor/bin/phpunit --filter test_name`
- Multisite: run `bash -c "WP_MULTISITE=1 vendor/bin/phpunit"` in the same container.
- Other PHP or WordPress versions: `WP_ENV_PHP_VERSION=8.1 WP_ENV_CORE=WordPress/WordPress#6.2 npx wp-env start`. After switching WordPress versions, re-activate a theme that exists in that version, or the dev site renders blank pages. The wp-env image for PHP 7.4 no longer builds; the CI job `phpunit-74` in [.github/workflows/tests.yml](.github/workflows/tests.yml) shows how to run PHP 7.4 in a plain `php:7.4-cli` container against wp-env's database.
- Releases: run the Release workflow manually ([.github/workflows/release.yml](.github/workflows/release.yml)) with a bump type (patch, minor, major; `bin/next-version.sh`) or an exact version. It runs `bin/bump-version.sh` (sets the version everywhere and dates the `## Unreleased` changelog section), commits to a temporary `release/vX.Y.Z` branch, tests and builds that commit (tests.yml takes a `ref` input), then fast-forwards the branch, tags, publishes, and deletes the temporary branch. Pushing a `vX.Y.Z` tag also releases, but then the version must already be set. `bin/check-version.sh`, `bin/build-zip.sh`, `bin/verify-zip.sh`, and `bin/smoke-test-zip.sh` are the same steps the workflow runs; see "Releasing" in README.md. The zip is built with `git archive`, so new development-only files must be marked `export-ignore` in `.gitattributes`, and new run-time files must be added to the required list in `bin/verify-zip.sh` if they're essential.
- `tests/phpunit/class-igsv-testcase.php` fakes all HTTP through `pre_http_request` (`mock_http()`); unmocked requests fail. The browser tests use a must-use plugin mapped only into the dev site (`tests/e2e/mu-plugin.php`) that serves `tests/e2e/fixtures/` for `https://example.test/` URLs; `tests/e2e/setup.php` creates the test posts. `tests/e2e/live-google.spec.js` uses a real public Google Sheet from the `IGSV_LIVE_SHEET` environment variable (passed to setup.php as an argument; never commit the URL) and compares results with Google's own export and query output; it skips when the variable is unset.

## Architecture

Nearly all logic is in one class, `WP_IGSV\InlineGoogleSpreadsheetViewerPlugin` in [inline-gdocs-viewer.php](inline-gdocs-viewer.php). `register()` (called at the bottom of the file) wires up all hooks statically, and a single instance handles shortcode rendering. `$invocations` counts shortcode uses per page so element IDs stay unique.

**Request flow in `displayShortcode()`:** attributes are normalized with `shortcode_atts()`. The long list of `chart_*` and `datatables_*` attributes must be declared there or WordPress drops them. Then `getDocTypeByKey()` classifies `key` into one of the following types:
- `spreadsheet` (docs.google.com or a bare ID): fetched as CSV export via `getSpreadsheetUrl()`
- `gasapp` (script.google.com): fetched; non-CSV responses are filtered with `wp_kses_post()` unless the post author has `unfiltered_html` (`authorCanUseUnfilteredHtml()`), then pass through the `gdoc_webapp_html` filter
- `csv` (URL ending in `.csv`)
- `docsviewer` (any other host): `detectContentType()` sends a cached `HEAD` request; if the server reports CSV (`isCsvContentType()`), the source is handled like `csv` (table, query), otherwise it's rendered as a Google Docs Viewer iframe. A failed or disallowed `HEAD` falls back to the viewer. Charts from such URLs always use the data source endpoint.
- `wpdb` (`key="wordpress"`): handled by `getSqlOutput()`. Runs only when the `allow_sql_db_queries` setting is on and the shortcode's hash (key + raw query) is in the post's `_gdoc_sql_authorized` meta. `authorizeSqlShortcodes()` (on `save_post`) writes that meta: all SQL shortcodes when the saving user has `gdoc_query_sql_databases`, otherwise only previously authorized ones that are unchanged. `isSafeSelect()` rejects anything but a single plain SELECT; the query runs with `SET SESSION TRANSACTION READ ONLY`. `mysql://` keys are recognized only to show an "unsupported" error.

HTTP sources go through `getHttpOutput()`. All fetching goes through `fetchData()` and `doHttpRequest()`: `isUrlAllowed()` (public http/https only; filter `gdoc_url_allowed`) is checked before the request and on every redirect (`validateRedirect()` on the Requests `before_redirect` hook), and `wp_safe_remote_request()` is used with arguments from `sanitizeHttpOpts()` (an allowlist; never `stream` or `filename`). Non-2xx responses throw. Responses are cached in transients named `gdoc_<sha1>` as JSON (body, content type, code), never as serialized objects. Exceptions carry plain-text messages that `displayShortcode()` escapes. Tabular data becomes HTML in `dataToHtml()` (filter `gdoc_table_html`).

**Queries and charts on CSV:** `query` on a CSV source runs in-process: `csvToDataTable()` calls `runQuery()`, which feeds parsed rows to the bundled `csv_vistable` engine in [lib/](lib/). That engine is a third-party Apache-2.0 library (vistable/visparser/visformat) that executes Google Visualization Query Language; `setup_rows()` also accepts column letters as aliases. `typeCsvColumns()` adds ` as number`/` as datetime` hints to the header row. For charts on non-Google sources, `getGVizChartOutput()` emits a `div.igsv-chart` whose `data-datasource-href` comes from `getDatasourceUrl()`: the key and query, base64url-encoded and HMAC-signed with `wp_salt('auth')`. `maybeServeDatasource()` (on `init`, `?igsv_datasource=1`) calls `handleDatasourceRequest()`, which verifies the signature, ignores any client-supplied query or output format, and always returns a `setResponse(...)` JavaScript call with `nosniff`. Google Sheets charts query Google directly.

**Assets:** `addFrontEndScripts()` registers everything (handles are filterable via `gdoc_enqueued_front_end_scripts` and `_styles`, kept for backward compatibility) and enqueues table assets only if the queried posts contain the shortcode, the `load_assets_everywhere` setting is on, or a shortcode already rendered. Block themes render content before `wp_enqueue_scripts`, so `enqueueTableAssets()` and `enqueueChartAssets()` record the need in `self::$needed` when called too early.

**Front-end JS:**
- [igsv-datatables.js](igsv-datatables.js) initializes DataTables with `new DataTable(...)` (DataTables 3 also registers its jQuery API because jQuery loads first) on tables matching the configured classes. It uses defaults from `igsv_plugin_vars` (localized in `addFrontEndScripts()`/`getLocalizedPluginVars()`), loads `languages/datatables-<lang>.json` only for languages in `igsv_plugin_vars.languages`, and maps the `FixedHeader*`/`FixedColumns*` classes to the `fixedHeader`/`fixedColumns` init options.
- [igsv-gvizcharts.js](igsv-gvizcharts.js) loads Google Charts with `google.charts.load('current', ...)`, reads the `data-chart-*` attributes, removes the `chart` prefix, lowercases the first letter of each key to get Google Charts option names, and draws the chart.

**Settings:** stored in the `gdoc_settings` option (validated by `validateSettings()`, rendered by `renderOptionsPage()`). [uninstall.php](uninstall.php) removes the option, the `_transient_gdoc*` caches, the `_gdoc_sql_authorized` post meta, and the SQL capability. If you add new persistent state, update uninstall.php to remove it too.

## Conventions

- Hook, option, nonce, and capability names use the `gdoc_` prefix (`self::prefix`). Shortcode filters use `self::shortcode . '_...'`. The public filters and registered script and style handles are documented in [docs/reference.md](docs/reference.md). Keep that file in sync when you change them.
- User documentation is in [docs/](docs/): `user-guide.md`, `faq.md`, and `reference.md`. [README.md](README.md) is an overview. There is no `readme.txt`, because the plugin is no longer on WordPress.org. When you add a shortcode attribute, also document it in docs/reference.md, under the shortcode attributes or the chart or DataTables options.
- Versions: the version appears in the plugin header and `const version` in inline-gdocs-viewer.php, in README.md, in CHANGELOG.md, and in the `.pot` header; `bin/bump-version.sh` updates all of them and `bin/check-version.sh` verifies them. Regenerate the translation template with `npx wp-env run cli --env-cwd=wp-content/plugins/inline-gdocs-viewer wp i18n make-pot . languages/inline-gdocs-viewer.pot --exclude=vendor,node_modules,tests,assets`.
- Security model: anything a post author controls (shortcode attributes, content, data source responses) is untrusted unless the author has `unfiltered_html`. Escape every attribute with `esc_attr()` or `esc_url()`, and add a PHPUnit test in `tests/phpunit/` that tries the attack for any new attribute that reaches HTML, HTTP, SQL, or the file system.
- User-facing strings use the `inline-gdocs-viewer` text domain. The translation template is [languages/inline-gdocs-viewer.pot](languages/inline-gdocs-viewer.pot).
- Code style: WordPress-style spacing (`func( $arg )`, a space before the parameter list in declarations) and 4-space indentation.

## Licensing

Every change must comply with the licenses involved:
- **This fork:** the plugin is GPL-3.0 (full text in [LICENSE](LICENSE)). It is a modified version of fabacab/inline-gdocs-viewer, and GPL-3.0 §5(a) requires a dated notice of the changes. When you change behavior, add an entry under "Unreleased" in CHANGELOG.md. Keep the "Modified ..." notice in the plugin header up to date.
- **Code in lib/:** these files are Apache-2.0, Copyright Mark Williams. Keep their license headers unchanged. If you modify one of these files, add a notice in that file stating the change (Apache-2.0 §4(b)).
- **Original authors:** never remove or change their copyright notices, license headers, or credits.
- **New dependencies and copied code:** check that the license is compatible with GPL-3.0 before adding anything. DataTables, pdfmake, and the DataTables translation files in `languages/` are MIT-licensed. JSZip is dual-licensed under MIT and GPL-3.0. pdfmake's `vfs_fonts.js` embeds the Roboto font (Apache-2.0; text in `assets/vendor/pdfmake/`). When adding or updating a bundled library, copy its license file into its `assets/vendor/` folder and update [assets/vendor/README.md](assets/vendor/README.md); a test fails if a folder there has no `LICENSE*` file. Development tools (wp-env, PHPUnit, PHPCS, Playwright) are not shipped and are kept out of `git archive` by [.gitattributes](.gitattributes).
