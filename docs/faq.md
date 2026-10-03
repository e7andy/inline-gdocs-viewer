# Frequently asked questions

## Will my website be updated when my Google Spreadsheets change?

Yes. Changes to your Google Spreadsheets appear on your website within a few minutes.

To make pages load faster, the plugin caches spreadsheets for 10 minutes. If you're making many changes quickly, or you don't want to wait for the cache to expire, add `use_cache="no"` to your shortcode to turn off caching:

```text
[gdoc key="ABCDEFG" use_cache="no"]
```

After you save and reload the page, updates should appear almost instantly. Turning off the cache can make your site slower, so do it only for small spreadsheets (roughly 100 rows or fewer) or while debugging.

## The default style is ugly. Can I change it?

Yes, if you can edit your theme's stylesheet. The plugin's HTML includes plenty of [CSS](https://en.wikipedia.org/wiki/Cascading_Style_Sheets) hooks. Use the `igsv-table` class to target the plugin's `<table>` element.

Each row (`<tr>`) and cell (`<td>`) also gets a position class. The first `<tr>` has the `row-1` class, the second has `row-2`, and the last has `row-N`, where `N` is the number of rows in the table. Cells work the same way by column: the first cell in a row has the `col-1` class, the second `col-2`, and so on:

```css
.igsv-table .row-2 .col-5 { /* styles for the cell in the 2nd row, 5th column */ }
```

Rows and cells also get an `odd` or `even` class, which makes zebra-striping easy in browsers that don't support CSS3 selectors.

```css
.igsv-table tr.odd  { /* styles for odd-numbered rows   (row 1, 3, 5...) */ }
.igsv-table tr.even { /* styles for even-numbered rows  (row 2, 4, 6...) */ }
.igsv-table td.odd  { /* styles for odd-numbered cells  (column 1, 3, 5...) */ }
.igsv-table td.even { /* styles for even-numbered cells (column 2, 4, 6...) */ }
```

## A table appears, but it's not my spreadsheet's data! And it looks weird!

If you still use the "old" Google Spreadsheets, check that you've published your spreadsheet. Follow steps 1 and 2 in [Google Spreadsheets Help: Publishing to the Web](http://docs.google.com/support/bin/answer.py?hl=en&answer=47134). If you use the "new" Google Spreadsheets, make sure you've chosen either the ["Public on the web" or "Anyone with the link" sharing option](https://support.google.com/drive/answer/2494886?p=visibility_options).

## A Google Login page appears where my Google Apps Script output should be.

Check that you deployed your web app with the "Anyone, even anonymous" access permission. [Learn more about web app permissions](https://developers.google.com/apps-script/guides/web#permissions).

## Nothing appears where my chart should be.

The best way to find the problem is to show the chart's data as a plain HTML table. Remove the `chart` attribute from your shortcode and look at the data the chart receives.

Charts usually fail because the spreadsheet's layout doesn't match what the chart type expects. Each chart type needs a certain number of rows or columns. If your spreadsheet isn't laid out for a chart, use the `query` attribute to select only the rows or columns the chart needs. Otherwise, create a new sheet with the right layout and use it as the `key` in your shortcode.

To learn the right spreadsheet layout for each chart type, see [Google's Chart Gallery documentation](https://developers.google.com/chart/interactive/docs/gallery).

## Can I remove certain columns from appearing on my webpage?

If you use the "new" Google Spreadsheets, `select` only the columns you want with a [Google Charts API Query Language](https://developers.google.com/chart/interactive/docs/querylanguage#Language_Syntax) query in the `query` attribute. For example, to show only the first three columns of a spreadsheet:

```text
[gdoc key="ABCDEFG" query="select A, B, C"]
```

You can also hide columns with CSS, for example `.col-4 { display: none; }`.

## How do I change the default settings? Can I turn paging off, change the page length, or change the sort order?

All [DataTables options](https://datatables.net/reference/option/) are available as shortcode attributes. The attribute name is `datatables_` plus the DataTables option name, converted from camelCase to snake_case. For example, to turn off paging, set the [DataTables `paging` option](https://datatables.net/reference/option/paging) to `false`:

```text
[gdoc key="ABCDEFG" datatables_paging="false"]
```

To change how many rows appear per page, use the [DataTables `pageLength` option](https://datatables.net/reference/option/pageLength). It defaults to `10`. To show 15 rows per page:

```text
[gdoc key="ABCDEFG" datatables_page_length="15"]
```

Some DataTables options take JavaScript array literals. One is the [DataTables `order` option](https://datatables.net/reference/option/order), which sets a table's initial sort order. Square brackets (`[` and `]`) inside a shortcode confuse WordPress's parser, so write them URL-encoded, as `%5B` and `%5D`. By default, tables sort by the first column in ascending order. To sort by the second column in descending order instead, give `order` the two-dimensional array `[[ 1, "desc" ]]` (columns are numbered from 0). With the brackets URL-encoded, the shortcode is:

```text
[gdoc key="ABCDEFG" datatables_order='%5B%5B 1, "desc" %5D%5D']
```

A JSON string inside a shortcode attribute (here, `"desc"`) must use double quotes, so wrap the attribute value itself in single quotes.

If you can add JavaScript to your theme, you can do all of this and more with the DataTables API, which works on any table the plugin enhances.

For example, to turn off paging, add JavaScript like this to your theme:

```js
jQuery(window).on('load', function () {
    jQuery('#igsv-MY_TABLE_KEY').DataTable().page.len(-1).draw();
});
```

To sort the table by the second column in descending order:

```js
jQuery(window).on('load', function () {
    jQuery('#igsv-MY_TABLE_KEY').DataTable().order([1, 'desc']).draw();
});
```

Replace `MY_TABLE_KEY` with your spreadsheet's Google document ID.

For more ways to customize tables, see the [DataTables API reference](https://datatables.net/reference/api).

You can also sort the table with the `query` attribute, using a [Google Charts API Query Language query with an `order by` clause](https://developers.google.com/chart/interactive/docs/querylanguage#Order_By). The data then arrives already sorted in the HTML, so you may want to turn off DataTables' sorting in the browser.

## How do I customize my chart?

Shortcode attributes give you many options for how your chart looks and behaves. Which options are available depends on the chart type. See the [Google Chart API documentation](https://developers.google.com/chart/interactive/docs/gallery) for the options each chart type supports.

Each option has a shortcode attribute with a similar name. For example, the `colors` option is the `chart_colors` attribute. It takes a space-separated list of colors, the same way `class` takes a list of class names:

```text
[gdoc key="ABCDEFG" chart="Pie" chart_colors="red green"]
```

To make a 3D chart, set `chart_dimensions="3"`.

With a few exceptions, the attribute name is `chart_` plus the Google Chart API option name, converted from camelCase to snake_case. For example, to turn off interactivity by setting the chart's `enableInteractivity` option to `false`:

```text
[gdoc key="ABCDEFG" chart="Pie" chart_enable_interactivity="false"]
```

Some options take an `Object` value. For these, use a [JSON](https://www.json.org/) object as the attribute value. For example, to set the properties of the `backgroundColor` option:

```text
[gdoc key="ABCDEFG" chart="Pie" chart_background_color='{"fill":"yellow","stroke":"red","strokeWidth":5}']
```

When the value is a JSON object, wrap the attribute value in single quotes.

The [reference](reference.md#chart-customization-options) lists every chart option attribute.

## Why do I get errors when I use the `query` attribute?

If your `query` contains an angle bracket, such as a less-than (`<`) or greater-than (`>`) sign, [WordPress assumes you're writing HTML](https://core.trac.wordpress.org/ticket/28564). It removes everything except the first word of the query, which causes a syntax error. Write these characters URL-encoded instead: `%3C` for `<` and `%3E` for `>`. WordPress leaves them alone, and the plugin decodes them correctly.

## How do I remove stylesheets or scripts that this plugin adds and I don't need?

Use the `gdoc_enqueued_front_end_styles` or `gdoc_enqueued_front_end_scripts` filter. For example, to stop the plugin from loading the Google Charts script, add code like this to your theme's `functions.php` file:

```php
function igsv_dequeue_google_charts_script ($scripts) {
    unset($scripts['igsv-gvizcharts']);
    return $scripts;
}
add_filter('gdoc_enqueued_front_end_scripts', 'igsv_dequeue_google_charts_script');
```

The [reference](reference.md#registered-script-and-stylesheet-handles) lists every script and stylesheet handle the plugin registers.

The scripts and stylesheets load only on pages that show the shortcode, unless you turn on **Load table scripts on every page?** in the plugin's settings.

## Why does my CSV file on my own server or intranet show an error?

To stop authors from using the plugin to reach services that aren't public, the plugin only fetches `http` and `https` addresses whose host resolves to a public IP address. Addresses such as `localhost`, `127.0.0.1`, `10.x.x.x`, `192.168.x.x`, and cloud metadata addresses are refused, and so are redirects to them.

If you trust a private address, allow it with the `gdoc_url_allowed` filter. The [reference](reference.md#filters) has an example.

## Why does my Apps Script web app's output look different?

If the post's author may not publish unfiltered HTML (for example, Authors and Contributors, or everyone on a multisite network except Super Admins), the web app's HTML is filtered like post content: scripts, event handlers, and other unsafe HTML are removed. Publish the post as an Administrator or Editor to show the web app's HTML unchanged.

## Why did my SQL query stop working?

Since version 0.14.0, a SQL query runs only if the post was last saved by a user with the `gdoc_query_sql_databases` capability (Administrators, by default). If someone else added or changed the query, an Administrator needs to review the post and save it again. Remote MySQL databases (`mysql://` keys) are no longer supported. See [SQL queries](reference.md#sql-queries).
