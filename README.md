# Inline Google Spreadsheet Viewer

A WordPress plugin that embeds public Google Sheets, Google Apps Script web apps, CSV files, and SQL query results in posts and pages. Data is shown as a sortable, searchable HTML table or an interactive Google Chart. The plugin can also embed live previews of PDF, DOC, XLS, and other documents through the Google Docs Viewer.

- **Version:** 0.13.2
- **Requires:** WordPress 4.0 or later, PHP 5.3 or later (tested up to WordPress 5.4)
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
| `https://script.google.com/...` web app URL | The web app's output, inserted as-is (CSV output becomes a table) |
| URL ending in `.csv` | HTML table or chart |
| `wordpress` | Runs a SQL `SELECT` on the site's database |
| `mysql://user:password@host:port/database` | Runs a SQL `SELECT` on a remote MySQL server |
| Any other URL | Google Docs Viewer `<iframe>` |

```text
[gdoc key="http://example.com/research_data.csv"]
[gdoc key="https://script.google.com/macros/s/ABCDEFG/exec"]
[gdoc key="http://example.com/my_final_paper.pdf" style="min-height:780px;border:none;"]
```

SQL sources work only after an administrator turns on SQL queries in the plugin's settings screen. The post author must also have the `gdoc_query_sql_databases` capability, and only `SELECT` statements are accepted:

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
- `linkify="no"` stops URLs and email addresses from being turned into links.
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

Add a `chart` attribute to draw a [Google Chart](https://developers.google.com/chart/interactive/docs/gallery) instead of a table. The supported types are `AnnotatedTimeLine`, `Annotation`, `Area`, `Bar`, `Bubble`, `Candlestick`, `Column`, `Combo`, `Gauge`, `Geo`, `Histogram`, `Line`, `Pie`, `Scatter`, `Stepped`, and `Timeline`.

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

WordPress removes text after `<` or `>` in shortcode attributes. In queries, write them as `%3C` and `%3E`.

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

The plugin loads its scripts and stylesheets on every front-end page. To remove the ones a site doesn't need, unset their handles with the enqueue filters:

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
| `inline-gdocs-viewer.php` | The plugin: shortcode, data fetching and caching, HTML rendering, settings screen, and the data-source proxy for charts |
| `igsv-datatables.js`, `igsv-gvizcharts.js` | Front-end setup for DataTables and Google Charts |
| `lib/` | A bundled query-language engine (Apache-2.0) that runs queries on CSV data on the server |
| `languages/` | DataTables translation files and the plugin's `.pot` translation template |
| `uninstall.php` | Deletes the plugin's settings, cached data, and capabilities when it's uninstalled |

There's no build step. Third-party front-end libraries load from CDNs.

## License

This plugin is free software released under the [GNU General Public License v3.0](LICENSE).

This repository is a modified version of [fabacab/inline-gdocs-viewer](https://github.com/fabacab/inline-gdocs-viewer), the plugin originally written by maymay. Changes began on 2026-10-03, and each one is listed in [CHANGELOG.md](CHANGELOG.md). The documentation in `docs/` and `CHANGELOG.md` is adapted from the original plugin's `readme.txt`.

The query engine in `lib/` (`vistable.php`, `visparser.php`, `visformat.php`) is Copyright 2008-2009 Mark Williams and licensed under the [Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0), which is compatible with GPL-3.0. Its original license headers are kept unchanged.
