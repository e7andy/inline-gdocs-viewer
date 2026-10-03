# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

WordPress plugin "Inline Google Spreadsheet Viewer" (slug `inline-google-spreadsheet-viewer`, text domain `inline-gdocs-viewer`). It provides a `[gdoc key="..."]` shortcode (and an oEmbed handler for bare Google Sheets / Apps Script URLs) that renders data from Google Sheets, CSV files, Google Apps Script web apps, or SQL databases as HTML tables (enhanced with DataTables) or Google Charts, and embeds other documents via the Google Docs Viewer.

There is no build step, package manager, linter, or test suite. All code is plain PHP and browser JavaScript loaded directly by WordPress. To test changes, install the directory into a WordPress site's `wp-content/plugins/` and exercise the shortcode in a post. Front-end libraries (DataTables and its extensions, pdfmake, JSZip, Google `jsapi`) are loaded from CDNs, not vendored.

## Architecture

Nearly all logic is in one class, `WP_IGSV\InlineGoogleSpreadsheetViewerPlugin` in [inline-gdocs-viewer.php](inline-gdocs-viewer.php). `register()` (called at the bottom of the file) wires up all hooks statically, and a single instance handles shortcode rendering. `$invocations` counts shortcode uses per page so element IDs stay unique.

**Request flow in `displayShortcode()`:** attributes are normalized with `shortcode_atts()`. The long list of `chart_*` and `datatables_*` attributes must be declared there or WordPress drops them. Then `getDocTypeByKey()` classifies `key` into one of the following types:
- `spreadsheet` (docs.google.com or a bare ID): fetched as CSV export via `getSpreadsheetUrl()`
- `gasapp` (script.google.com): fetched as-is; non-CSV responses pass through the `gdoc_webapp_html` filter
- `csv` (URL ending in `.csv`)
- `docsviewer` (any other host): rendered as a Google Docs Viewer iframe
- `wpdb` (`key="wordpress"`) or `mysql` (`mysql://user:pass@host/db`): handled by `getSqlOutput()`. Only `SELECT` queries are allowed, only when the `allow_sql_db_queries` setting is on, and only when the post author has the `gdoc_query_sql_databases` capability.

HTTP sources go through `getHttpOutput()`. `fetchData()` caches responses in transients (`use_cache`, `expire_in`). Tabular data becomes HTML in `dataToHtml()` (filter `gdoc_table_html`).

**Charts and the GViz proxy:** for charts on non-Google sources (and for `query` on CSV), the plugin acts as its own Google Visualization data source. `getGVizChartOutput()` emits a `div.igsv-chart` with `data-chart-*` attributes and a nonce-protected `data-datasource-href` that points back to the site. On `init`, `maybeFetchGvizDataSource()` intercepts that request (`gdoc_get_datasource_nonce`), fetches the CSV, and runs it through the bundled `csv_vistable` query engine in [lib/](lib/). That engine is a third-party Apache-2.0 library (vistable/visparser/visformat) that executes Google Visualization Query Language server-side. `setGVizCsvDataTypes()` type-hints CSV columns so charts don't treat everything as strings.

**Front-end JS:**
- [igsv-datatables.js](igsv-datatables.js) initializes DataTables on tables matching the configured classes. It uses defaults from `igsv_plugin_vars` (localized in `addFrontEndScripts()`/`getLocalizedPluginVars()`), loads the language file `languages/datatables-<lang>.json`, and applies the FixedHeader/FixedColumns extensions based on table classes.
- [igsv-gvizcharts.js](igsv-gvizcharts.js) reads the `data-chart-*` attributes, removes the `chart` prefix, lowercases the first letter of each key to get Google Charts option names, and draws the chart.

**Settings:** stored in the `gdoc_settings` option (validated by `validateSettings()`, rendered by `renderOptionsPage()`). [uninstall.php](uninstall.php) removes the option, the `_transient_gdoc*` caches, and the SQL capability. If you add new persistent state, update uninstall.php to remove it too.

## Conventions

- Hook, option, nonce, and capability names use the `gdoc_` prefix (`self::prefix`). Shortcode filters use `self::shortcode . '_...'`. The public filters and registered script and style handles are documented in [docs/reference.md](docs/reference.md). Keep that file in sync when you change them.
- User documentation is in [docs/](docs/): `user-guide.md`, `faq.md`, and `reference.md`. [README.md](README.md) is an overview. There is no `readme.txt`, because the plugin is no longer on WordPress.org. When you add a shortcode attribute, also document it in docs/reference.md, under the shortcode attributes or the chart or DataTables options.
- Releases: bump `Version:` in the plugin header of inline-gdocs-viewer.php and the version in README.md, and add an entry to [CHANGELOG.md](CHANGELOG.md).
- User-facing strings use the `inline-gdocs-viewer` text domain. The translation template is [languages/inline-gdocs-viewer.pot](languages/inline-gdocs-viewer.pot).
- Code style: WordPress-style spacing (`func( $arg )`, a space before the parameter list in declarations) and 4-space indentation.

## Licensing

Every change must comply with the licenses involved:
- **This fork:** the plugin is GPL-3.0 (full text in [LICENSE](LICENSE)). It is a modified version of fabacab/inline-gdocs-viewer, and GPL-3.0 §5(a) requires a dated notice of the changes. When you change behavior, add an entry under "Unreleased (fork)" in CHANGELOG.md. Keep the "Modified ..." notice in the plugin header up to date.
- **Code in lib/:** these files are Apache-2.0, Copyright Mark Williams. Keep their license headers unchanged. If you modify one of these files, add a notice in that file stating the change (Apache-2.0 §4(b)).
- **Original authors:** never remove or change their copyright notices, license headers, or credits.
- **New dependencies and copied code:** check that the license is compatible with GPL-3.0 before adding anything. DataTables, pdfmake, and the DataTables translation files in `languages/` are MIT-licensed. JSZip is dual-licensed under MIT and GPL-3.0. All of these load from CDNs except the translation files.
