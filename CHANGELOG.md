# Changelog

## Unreleased

- Added: `cell_colors="yes"` shows a Google Sheet's cell background colors in the table. The colors come from the sheet's embed view; they work for Google Sheets without `query`, and a sheet whose colors can't be read is shown without them.
- Fixed: A Google Sheet that isn't shared publicly now shows the "share it with Anyone with the link" message. Google answers such requests with HTTP 401 (or 403 or 404), which the plugin showed as a bare HTTP error.
- Changed: Data source requests now wait up to 15 seconds instead of WordPress's default 5, because Apps Script web apps that are starting up often take longer. `http_opts` can still set a timeout from 1 to 30 seconds.
- Development: Live tests against a real public sheet, an unshared sheet, an Apps Script web app, and files on GitHub, run weekly by GitHub Actions. Their resources are described in `tests/e2e/live/`.
- Changed: CSV from addresses that don't end in `.csv`, such as a web service's export URL, is now shown as a table (and works with `query`) when the server sends a CSV content type. The plugin checks the content type with a cached `HEAD` request; other documents still open in the Google Docs Viewer and are not downloaded by your server.
- Development: Publishing a draft release no longer starts a second Release run that fails: the workflow skips tag pushes for releases that already exist.
- Development: Added tests for CSV without a `.csv` address, Google Sheets by bare ID and by tab (`gid`), sheets that aren't shared, Apps Script charts, and browser tests for Apps Script web apps (HTML and CSV) and SQL tables.

## 1.0.1 - 2026-10-04

- Changed: Links that `linkify` makes from web addresses in tables now open in a new tab, with `rel="noopener noreferrer"`. The new `link_target` attribute (`_blank` or `_self`) chooses where they open. Links on the plugin's settings page also open in a new tab.
- Development: The release zip is now named `inline-gdocs-viewer-X.Y.Z.zip`. The folder inside is still `inline-google-spreadsheet-viewer/`, so installing it upgrades existing installs.
- Development: A manual release raises the version automatically. Choose patch, minor, or major (or an exact version) when running the Release workflow; it sets the version everywhere, turns the "Unreleased" changelog section into the new version's section, and adds that commit to `master` only after the tests and build pass.
- Development: The zip under a release run's **Artifacts** is now the installable zip itself instead of a zip inside a zip. The Release workflow has a **build-only** option that tests and builds the zip without publishing a release.

## 1.0.0 - 2026-10-03

The first release of this fork. It continues from upstream 0.13.2; this version was briefly numbered 0.14.0 on `master` before release.

Changes made on 2026-10-03 in the fork at <https://github.com/e7andy/inline-gdocs-viewer>, a modified version of <https://github.com/fabacab/inline-gdocs-viewer>. This release fixes security problems found in an audit of 0.13.2. Update as soon as possible.

### Security

- Security: Authors (Contributor and up) could write files anywhere on the server, and so run their own code, through the `http_opts` attribute (`stream` and `filename`). `http_opts` now accepts only `method` (GET, POST, HEAD), `timeout` (1-30), `redirection` (0-5), `user-agent`, `headers`, and `body`.
- Security: The chart data proxy let anyone who could see a chart page make the server fetch any URL, including internal services and cloud metadata addresses, and read the result. The proxy is replaced by an endpoint (`?igsv_datasource=1`) that only serves data sources defined by a shortcode, signed with the site's secret key. The old `gdoc_get_datasource_nonce` endpoint is gone, and rendering a chart no longer writes to the database.
- Security: All data source requests now refuse addresses that are not public `http` or `https` addresses (including redirects to them), and use `wp_safe_remote_request()`. A new `gdoc_url_allowed` filter can allow trusted private addresses.
- Security: The query engine's HTML error output repeated the query unescaped, which allowed reflected cross-site scripting. The data source endpoint now only returns JavaScript `setResponse()` calls with `X-Content-Type-Options: nosniff`, and the engine escapes its HTML error output.
- Security: `chart_*` attribute values could break out of their HTML attribute (cross-site scripting). They are now escaped, and the content inside a chart shortcode is filtered with `wp_kses_post()`.
- Security: HTML from Apps Script web apps, including bare web app URLs pasted into a post, is filtered with `wp_kses_post()` unless the post's author may publish unfiltered HTML.
- Security: `datatables_ajax`, `datatables_data`, and `datatables_server_side` are ignored unless the post's author may publish unfiltered HTML, because DataTables displays that data as HTML.
- Security: A SQL query runs only if the post was last saved, with that exact query, by a user with the `gdoc_query_sql_databases` capability. Previously, an Editor could add a query to a post written by an Administrator. Queries must be a single `SELECT` without comments, `INTO`, `LOAD_FILE()`, `SLEEP()`, `BENCHMARK()`, or locking; they run in a read-only transaction; and SQL errors are no longer shown on the page.
- Security: Responses are cached as JSON instead of serialized PHP objects, so the cache is never passed to `unserialize()`.
- Security: The DataTables defaults on the settings page are escaped. The `lang` attribute and the time zone are validated.

### Changed

- Changed (breaking): Remote MySQL data sources (`mysql://` keys) are no longer supported, because they put database passwords in post content.
- Changed: DataTables, its extensions, JSZip, and pdfmake are bundled in `assets/vendor/` instead of loaded from CDNs, and updated: DataTables 3.1.3, Buttons 4.1.2, Select 4.1.1, FixedHeader 5.1.2, FixedColumns 6.1.1, Responsive 4.1.1, JSZip 3.10.2, and pdfmake 0.3.11. This fixes known vulnerabilities in the old versions and stops sending visitors' IP addresses to CDNs.
- Changed: Scripts and styles load only on pages that show the shortcode. A new setting, **Load table scripts on every page?**, restores the old behavior for tables written by hand. Block themes are supported.
- Changed: Charts use the current Google Charts loader (`https://www.gstatic.com/charts/loader.js`) instead of the retired `jsapi` loader. `AnnotatedTimeLine`, which Google retired, draws an `Annotation` chart.
- Changed: CSV files and queries on them are fetched and run directly in PHP, instead of through an HTTP request to the site itself. Queries on CSV files can refer to columns by letter (`A`, `B`, ...) as well as by header text, and number columns are detected so that comparisons such as `B > 6` work.
- Changed: Error pages from data sources (HTTP status other than 2xx) are reported as errors instead of being shown and cached. A Google Sheet that isn't shared publicly shows an error instead of Google's sign-in page.
- Changed: The Google Docs Viewer uses `https://docs.google.com/viewer`.
- Changed: The default DataTables layout uses the `layout` option. Saved settings that use the older `dom` option keep working.
- Changed: Requires WordPress 6.2 and PHP 7.4 or later. Tested with WordPress 6.2 and 7.1 and PHP 7.4 to 8.5.

### Fixed

- Fixed: The plugin crashed on PHP 8 (`get_magic_quotes_gpc()`, `error_log()` arguments), so charts and CSV tables did not work. Many PHP 8 warnings and deprecations are fixed.
- Fixed: In the query engine, `order by` never worked inside the plugin's namespace, the tokenizer and date formatter misdetected the end of a string on PHP 8, and `gmstrftime()` (deprecated in PHP 8.1) is replaced.
- Fixed: CSV lines longer than 4096 bytes were split into several rows.
- Fixed: `chart_dimensions` turned on 3D whatever its value.
- Fixed: `uninstall.php` now also removes the new post meta and removes the capability from every role.

### Development

- Added PHPUnit tests (`tests/phpunit/`), Playwright browser tests (`tests/e2e/`), PHPCS with the WordPress security rules and PHPCompatibility, a wp-env configuration, and a GitHub Actions workflow.
- Added a release workflow: pushing a `vX.Y.Z` tag, or running it manually from the Actions tab, tests the code, builds and checks the plugin zip, installs it in WordPress, and publishes a GitHub release. Release scripts are in `bin/`.
- The bundled query engine in `lib/` (Apache-2.0, Mark Williams) is modified; each file lists its changes below its license header.

### Earlier changes on 2026-10-03

- Maintenance: Remove links to the plugin's former WordPress.org directory listing and support forum. The plugin URI now points to the GitHub repository, and the admin documentation links now point to `docs/reference.md` there. The help tab no longer refers to the support forum.
- Documentation: Replace `readme.txt` (the WordPress.org readme) with `README.md`, `docs/user-guide.md`, `docs/faq.md`, `docs/reference.md`, and this changelog. Leave out the donation links, the original author's support statement, and the screenshot captions, whose images were hosted on WordPress.org. Edit the documentation for clarity.
- Documentation: Fix the `query`, `datatables_order`, and chart color examples.
- Documentation: Add `CLAUDE.md` and the full GPL-3.0 text in `LICENSE`.

## Releases before the fork

The releases below were made by maymay, the original author, in [fabacab/inline-gdocs-viewer](https://github.com/fabacab/inline-gdocs-viewer) and on WordPress.org.

### 0.13.2

- Maintenance: Update DataTables libraries. No backwards incompatible changes are expected.

### 0.13.1

- Enhancement: New `csv_headers` shortcode attribute adds support for Google Sheet Query Language HTTP endpoint `headers` parameter. Using `csv_headers=1` in your shortcode may help if you find headers exported from a Google Sheet are missing.

### 0.13.0

This is a maintenance and compatibility update that adds support for the Block Editor in WordPress 5.x and higher.

- Compatibility: Officially support WordPress 5.x and the Block Editor. This update removes the deprecated QuickTags integration from the Classic Editor and fixes minor author-side rendering bugs on WP 5.x.
- Bugfix: Protect against "Undefined index" error when a user enters an incomplete `key` value.

### 0.12.9

- Bugfix: Fix incorrect spacing causing invalid HTML table row markup.

### 0.12.8

- Bugfix: Fix invalid HTML output affecting some CSS hooks.

### 0.12.7

- [Feature](https://github.com/fabacab/inline-gdocs-viewer/pull/23): Add HTML IDs to table rows. Thanks, @ThaiWood. :)
- Bugfix: Compatibility with PHP 7.0 and later.

### 0.12.6

- Update DataTables libraries to current release versions.
- Minor code cleanup. (Fixes broken links in readme, code style, etc.)

### 0.12.5

- Bugfix: Remove `chart_legend_position` attribute and update the documentation. This should be `chart_legend` with a JSON object attribute value.

### 0.12.4

- Enhancement: Google Charts now accept fallback content as part of the shortcode like other options.
- Bugfix: Support URL-encoded MySQL connection strings.
- Security: MySQL datasources have been hardened, but their HTML IDs have changed. If you have styles or scripts looking for specific HTML IDs, you will need to update those resources to match the newly generated ID values.

### 0.12.3

- Bugfix: Fix errors when using MySQL database access shortcodes.

### 0.12.2

- Feature: Support [Google GeoCharts](https://developers.google.com/chart/interactive/docs/gallery/geochart) using the `Geo` chart type (`[gdoc key="ABCDEFG" chart="Geo"]`).
- Bugfix: Exception handling no longer fails to return a human-readable error when using SQL data sources.

### 0.12.1

- Bugfix: Fix regression during activation.

### 0.12

- Automatically force new-style URLs for `key` attribute values that are still using deprecated old-style document IDs.
- Bugfix: Fix collision with class names in some cases. This change requires the use of PHP 5.3 or later.
