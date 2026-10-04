# Inline Google Spreadsheet Viewer

A WordPress plugin that embeds public Google Sheets, Google Apps Script web apps, CSV files, and SQL query results in posts and pages. Data is shown as a sortable, searchable HTML table or an interactive Google Chart. The plugin can also embed live previews of PDF, DOC, XLS, and other documents through the Google Docs Viewer.

- **Version:** 1.0.0
- **Requires:** WordPress 6.2 or later, PHP 7.4 or later (tested with WordPress 6.2 and 7.1, and PHP 7.4 to 8.5)
- **License:** [GPL-3.0](https://www.gnu.org/licenses/gpl-3.0.html)

This repository is a fork of [fabacab/inline-gdocs-viewer](https://github.com/fabacab/inline-gdocs-viewer). This README is an overview. The full documentation is in [docs/](docs/):

- [User guide](docs/user-guide.md): how to use each data source, with examples
- [FAQ](docs/faq.md): troubleshooting, styling, and customizing tables and charts
- [Reference](docs/reference.md): every shortcode attribute, chart option, DataTables option, filter, and script handle
- [Changelog](CHANGELOG.md)

## Installation

1. Copy this directory into `wp-content/plugins/` as `inline-google-spreadsheet-viewer`.
2. Activate the plugin from the **Plugins** screen in WordPress.
3. Add a `[gdoc key="..."]` shortcode to a post or page.

## Quick start

Paste the URL of a public Google Sheet or Google Apps Script web app on its own line in a post and save. The data appears as a sortable, searchable table.

To use options, write the shortcode out. `key` is the only required attribute:

```text
[gdoc key="https://docs.google.com/spreadsheets/d/ABCDEFG/edit#gid=123456"]
```

Data sources must be publicly readable:

- **Google Sheets:** share as "Public on the web" or "Anyone with the link". Private sheets aren't supported.
- **Apps Script web apps:** deploy with "Anyone, even anonymous" access.
- **CSV files:** the URL must work without logging in.

## Data sources

The plugin chooses how to handle a source from the `key` value:

| `key` value | Result |
| --- | --- |
| Google Sheets URL (or document ID) | HTML table or chart |
| `https://script.google.com/...` web app URL | The web app's output (CSV output becomes a table). Unsafe HTML is removed unless the author may post unfiltered HTML |
| URL ending in `.csv` | HTML table or chart |
| `wordpress` | Runs a SQL `SELECT` on the site's database |
| Any other URL | Google Docs Viewer `<iframe>` |

```text
[gdoc key="http://example.com/research_data.csv"]
[gdoc key="https://script.google.com/macros/s/ABCDEFG/exec"]
[gdoc key="http://example.com/my_final_paper.pdf" style="min-height:780px;border:none;"]
```

Data sources must be public `http` or `https` addresses; addresses on your server or private network are refused unless you allow them with the `gdoc_url_allowed` filter.

SQL sources work only after an administrator turns on SQL queries in the plugin's settings screen. A query runs only if the post was last saved by a user with the `gdoc_query_sql_databases` capability (Administrators, by default), and only single read-only `SELECT` statements are accepted. Remote MySQL databases are no longer supported:

```text
[gdoc key="wordpress" query="SELECT display_name AS Name, user_registered AS 'Registration Date' FROM wp_users"]
```

## Tables

Tables are enhanced with [DataTables](https://datatables.net/), which adds sorting, searching, paging, and buttons for column visibility, CSV, Excel, PDF, and print. To customize a table:

```text
[gdoc key="ABCDEFG" class="my-sheet" title="Tooltip text"]This becomes the table's caption.[/gdoc]
```

- `class="no-datatables"` turns off DataTables, and `class="no-responsive"` turns off only the Responsive extension.
- `class="FixedHeader"` freezes the header row. `class="FixedColumns-left-3"` freezes the three leftmost columns.
- `linkify="no"` stops URLs and email addresses from being turned into links. Links open in a new tab; use `link_target="_self"` to open them in the same tab.
- `header_rows`, `footer_rows`, `header_cols`, and `strip` control the `<thead>`, the `<tfoot>`, `<th>` cells, and how many leading rows are skipped.

To set any [DataTables option](https://datatables.net/reference/option/), use `datatables_` plus the option name in snake_case. For example, `pageLength` becomes `datatables_page_length`:

```text
[gdoc key="ABCDEFG" datatables_paging="false"]
[gdoc key="ABCDEFG" datatables_page_length="15"]
```

WordPress misreads `[` and `]` inside shortcodes, so write array values with `%5B` and `%5D`. JSON values must be wrapped in single quotes. To sort by the second column, descending (columns are numbered from 0):

```text
[gdoc key="ABCDEFG" datatables_order='%5B%5B 1, "desc" %5D%5D']
```

Every table has the `igsv-table` class, and its rows and cells have `row-N`, `col-N`, and `odd`/`even` classes for styling:

```css
.igsv-table .row-2 .col-5 { /* the cell in row 2, column 5 */ }
```

## Charts

Add a `chart` attribute to draw a [Google Chart](https://developers.google.com/chart/interactive/docs/gallery) instead of a table. The supported types are `Annotation`, `Area`, `Bar`, `Bubble`, `Candlestick`, `Column`, `Combo`, `Gauge`, `Geo`, `Histogram`, `Line`, `Pie`, `Scatter`, `Stepped`, and `Timeline`. (`AnnotatedTimeLine`, which Google retired, now draws an `Annotation` chart.)

```text
[gdoc key="ABCDEFG" chart="Bar" title="Total goals per team"]
```

To set chart options, use `chart_` plus the option name in snake_case. For example, `enableInteractivity` becomes `chart_enable_interactivity`. Object values are JSON in single quotes:

```text
[gdoc key="ABCDEFG" chart="Pie" chart_colors="red green" chart_dimensions="3" chart_pie_slice_text="value"]
[gdoc key="ABCDEFG" chart="Pie" chart_background_color='{"fill":"yellow","stroke":"red","strokeWidth":5}']
```

If a chart doesn't appear, remove `chart` to see the data as a table. Each chart type expects its data in a particular layout of rows and columns.

## Queries

Use `query` to filter or reshape a Google Sheet or CSV file with the [Google Visualization Query Language](https://developers.google.com/chart/interactive/docs/querylanguage) before it's displayed:

```text
[gdoc key="ABCDEFG" query="select A, B, C"]
[gdoc key="ABCDEFG" query="select A, B order by B desc limit 1"]
```

WordPress removes text after `<` or `>` in shortcode attributes. In queries, write them as `%3C` and `%3E`. In queries on CSV files, you can refer to columns by letter or by their header text.

## Caching

Fetched data is cached with WordPress transients for 10 minutes. Use `expire_in` to set the cache time in seconds (`0` caches forever), or `use_cache="no"` to turn off caching while you're editing.

## Developer hooks

| Filter | What it filters |
| --- | --- |
| `gdoc_table_html` | Table HTML, before `make_clickable()` runs |
| `gdoc_viewer_html` | The Google Docs Viewer `<iframe>` HTML |
| `gdoc_webapp_html` | An Apps Script web app's response body (also receives the shortcode attributes) |
| `gdoc_query` | The `query` value (also receives the shortcode attributes) |
| `gdoc_enqueued_front_end_styles` | The stylesheets the plugin enqueues |
| `gdoc_enqueued_front_end_scripts` | The scripts the plugin enqueues |
| `gdoc_url_allowed` | Whether a data source URL may be fetched (only public addresses by default) |

The plugin loads its scripts and stylesheets only on pages that show the shortcode, or on every page if you turn on **Load table scripts on every page?** in its settings. To remove the ones a site doesn't need, unset their handles with the enqueue filters:

```php
add_filter( 'gdoc_enqueued_front_end_scripts', function ( $scripts ) {
    unset( $scripts['igsv-gvizcharts'] );
    return $scripts;
} );
```

The [reference](docs/reference.md) lists every registered handle, along with every shortcode attribute, chart option, and DataTables option.

## Repository layout

| Path | Contents |
| --- | --- |
| `inline-gdocs-viewer.php` | The plugin: shortcode, data fetching and caching, HTML rendering, settings screen, and the signed data source endpoint for charts |
| `igsv-datatables.js`, `igsv-gvizcharts.js` | Front-end setup for DataTables and Google Charts |
| `assets/vendor/` | Bundled DataTables, JSZip, and pdfmake files, each with its license ([details](assets/vendor/README.md)) |
| `lib/` | A bundled query-language engine (Apache-2.0) that runs queries on CSV data on the server |
| `languages/` | DataTables translation files and the plugin's `.pot` translation template |
| `uninstall.php` | Deletes the plugin's settings, cached data, post meta, and capabilities when it's uninstalled |
| `tests/` | PHPUnit tests (`tests/phpunit/`) and Playwright browser tests (`tests/e2e/`) |

There's no build step. The Google Charts loader is the only script loaded from a third party.

## Development

You need Docker, Node.js 20 or later, and Composer.

```sh
composer install          # PHPUnit, PHPCS, and the WordPress coding standards
npm install               # wp-env and Playwright
npx playwright install chromium
npm run env:start         # WordPress at http://localhost:8888 (tests use :8889)

npm run test:php          # PHPUnit in the wp-env test container
npm run test:e2e          # Browser tests against http://localhost:8888
IGSV_LIVE_SHEET="https://docs.google.com/spreadsheets/d/<id>/edit" npm run test:e2e:live
                          # Live tests against a real public Google Sheet
composer lint             # PHPCS: WordPress security rules and PHP 7.4+ compatibility
```

Run a single PHPUnit test with `npx wp-env run tests-cli --env-cwd=wp-content/plugins/inline-gdocs-viewer vendor/bin/phpunit --filter test_name`. To test another PHP version, start wp-env with `WP_ENV_PHP_VERSION=8.1` (for example). GitHub Actions runs all of these on every push; see [.github/workflows/tests.yml](.github/workflows/tests.yml).

The live tests (`tests/e2e/live-google.spec.js`) fetch a real public Google Sheet, so run them by hand before a release; they skip themselves when `IGSV_LIVE_SHEET` isn't set. They compare the plugin's tables, queries, and charts with what Google returns for the same request. The sheet needs headers in row 1, a text column A, a date-like text column C, and a number column I.

The browser tests use a must-use plugin (`tests/e2e/mu-plugin.php`, mapped into the development site only) that serves fixture CSV files for `https://example.test/` URLs.

## Releasing

Releases are built and published by GitHub Actions ([.github/workflows/release.yml](.github/workflows/release.yml)). Each release has the installable zip, `inline-gdocs-viewer-X.Y.Z.zip`, its SHA-256 checksum, and the version's section of [CHANGELOG.md](CHANGELOG.md) as release notes. The zip contains one folder, `inline-google-spreadsheet-viewer/` (the plugin's original folder name), so installing it upgrades an existing install of the plugin instead of adding a second copy. Versions follow [semantic versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.

1. List the changes under `## Unreleased (fork)` in CHANGELOG.md as you make them.
2. Set the new version everywhere and date the changelog section:

    ```sh
    bin/bump-version.sh 1.2.3
    ```

    If you changed any translatable strings, also regenerate the `.pot` file (see CLAUDE.md). Review the changes, commit them, and push to `master`.
3. Release, in one of two ways:
    - **Tag push:** `git tag v1.2.3 && git push origin v1.2.3`
    - **Manually:** on GitHub, open **Actions > Release > Run workflow**, choose the branch, and enter `1.2.3`. You can also choose to create a draft or a pre-release. The `v1.2.3` tag is created when the release is published.
    - **Build only:** run the Release workflow manually with **build-only** ticked. It tests and builds the zip without publishing anything; download the zip from the run's page under **Artifacts**.

The workflow then checks that the code states the version everywhere and that the changelog has notes for it, runs the full test workflow, builds the zip, checks its contents and PHP syntax, installs it in WordPress and renders a table, and only then publishes the release. If any step fails, nothing is published. Versions with a suffix, such as `1.2.3-beta.1`, are published as pre-releases. The zip on the release page and the one under the run's **Artifacts** are the same installable file; upload either one in WordPress under **Plugins > Add New > Upload Plugin**. (Use the release zip, not GitHub's automatic "Source code" archives, which contain the development files.)

To build and check a zip on your own machine (with wp-env running for the smoke test):

```sh
bin/check-version.sh                        # every place states the same version
zip=$(bin/build-zip.sh)                     # dist/inline-gdocs-viewer-X.Y.Z.zip from HEAD
bin/verify-zip.sh "$zip"                    # required files and licenses present, no development files
bin/smoke-test-zip.sh "$zip"                # installs the zip in wp-env and renders a table
```

`bin/build-zip.sh` packages committed files only. To include uncommitted changes, pass `$(git stash create)` as the commit. Files marked `export-ignore` in [.gitattributes](.gitattributes) (tests, development configuration, and `bin/`) are left out.

## License

This plugin is free software released under the [GNU General Public License v3.0](LICENSE).

This repository is a modified version of [fabacab/inline-gdocs-viewer](https://github.com/fabacab/inline-gdocs-viewer), the plugin originally written by maymay. Changes began on 2026-10-03, and each one is listed in [CHANGELOG.md](CHANGELOG.md). The documentation in `docs/` and `CHANGELOG.md` is adapted from the original plugin's `readme.txt`.

The query engine in `lib/` (`vistable.php`, `visparser.php`, `visformat.php`) is Copyright 2008-2009 Mark Williams and licensed under the [Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0), which is compatible with GPL-3.0. Its original license headers are kept unchanged, and each modified file lists its changes below the header.

The libraries in `assets/vendor/` are MIT-licensed, except JSZip (MIT or GPL-3.0) and the Roboto font embedded in pdfmake (Apache-2.0). Each library's folder contains its license.
