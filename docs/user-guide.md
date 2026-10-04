# User guide

You can turn your spreadsheet into an interactive chart or graph, embed documents other than spreadsheets, and customize the HTML of your table with the `[gdoc key=""]` [WordPress shortcode](https://codex.wordpress.org/Shortcode). The only required attribute is `key`, which specifies the document to retrieve. All other attributes are optional.

For every attribute and option, see the [reference](reference.md). For common problems, see the [FAQ](faq.md).

## Quick start

Paste the URL of your public [Google Spreadsheet](https://support.google.com/docs/answer/37579?hl=en) or [Google Apps Script web app](https://developers.google.com/apps-script/guides/web) on its own line in a WordPress post or page, then save. Your data appears in a sortable, searchable HTML table. A web app's output is shown using the HTML that the web app produces.

The data must be public:

- **Google Spreadsheets:** share with either the "Public on the web" or "Anyone with the link" option ([learn how to share your spreadsheet](https://support.google.com/drive/?p=visibility_options&hl=en_US)). Private spreadsheets and spreadsheets shared with "Specific people" aren't supported.
- **Web apps:** deploy with the "Anyone, even anonymous" [access permission](https://developers.google.com/apps-script/guides/web#permissions).
- **CSV files:** the file must be public, so it opens without logging in to the site that hosts it.

## Google Spreadsheets

After you save the sharing setting, copy the spreadsheet's URL from your browser's address bar into the shortcode. For example, to display the spreadsheet at `https://docs.google.com/spreadsheets/d/ABCDEFG/edit#gid=123456`:

```text
[gdoc key="https://docs.google.com/spreadsheets/d/ABCDEFG/edit#gid=123456"]
```

## CSV files

CSV files work the same way as Google Spreadsheets. Set `key` to the file's URL to display it as an HTML table. The address doesn't have to end in `.csv`: a web service's export address works too, as long as the server says it's sending CSV (the `text/csv` content type).

```text
[gdoc key="http://example.com/research_data.csv"]
```

## HTML tables

To set the table's `title`, `<caption>`, and a custom `class`:

```text
[gdoc key="ABCDEFG" class="my-sheet" title="Tooltip text displayed on hover"]This is the table's caption.[/gdoc]
```

This produces HTML like the following:

```html
<table id="igsv-ABCDEFG" class="igsv-table my-sheet" title="Tooltip text displayed on hover">
    <caption>This is the table's caption.</caption>
    <!-- ...rest of table code using spreadsheet data here... -->
</table>
```

All tables are enhanced with jQuery [DataTables](https://datatables.net/), which adds sorting, searching, and pagination. To turn this off for one table, add the `no-datatables` class:

```text
[gdoc key="ABCDEFG" class="no-datatables"]
```

To show your Google Sheet's cell background colors in the table, add `cell_colors="yes"`:

```text
[gdoc key="ABCDEFG" cell_colors="yes"]
```

This works for Google Sheets without a `query`. Text colors and other formatting aren't copied.

Web addresses and email addresses in your data are turned into links. To turn this off, set `linkify` to `no`:

```text
[gdoc key="ABCDEFG" linkify="no"]
```

You can customize each table with shortcode attributes, or every table on your site from the plugin's settings screen. For example, you can freeze the table header or columns and set the pagination length. The [reference](reference.md) lists every attribute.

## Charts

You can graph data from Google Spreadsheets or CSV files as interactive charts. Add the `chart` attribute with one of these chart types:

- [`Annotation`](https://developers.google.com/chart/interactive/docs/gallery/annotationchart)
- [`Area`](https://developers.google.com/chart/interactive/docs/gallery/areachart)
- [`Bar`](https://developers.google.com/chart/interactive/docs/gallery/areachart)
- [`Bubble`](https://developers.google.com/chart/interactive/docs/gallery/bubblechart)
- [`Candlestick`](https://developers.google.com/chart/interactive/docs/gallery/candlestickchart)
- [`Column`](https://developers.google.com/chart/interactive/docs/gallery/columnchart)
- [`Combo`](https://developers.google.com/chart/interactive/docs/gallery/combochart)
- [`Gauge`](https://developers.google.com/chart/interactive/docs/gallery/gauge)
- [`Geo`](https://developers.google.com/chart/interactive/docs/gallery/geochart)
- [`Histogram`](https://developers.google.com/chart/interactive/docs/gallery/histogram)
- [`Line`](https://developers.google.com/chart/interactive/docs/gallery/linechart)
- [`Pie`](https://developers.google.com/chart/interactive/docs/gallery/piechart)
- [`Scatter`](https://developers.google.com/chart/interactive/docs/gallery/scatterchart)
- [`Stepped`](https://developers.google.com/chart/interactive/docs/gallery/steppedareachart) (stepped area)
- [`Timeline`](https://developers.google.com/chart/interactive/docs/gallery/timeline)

Say you have data for a sports league, with team names in the first column and each team's total goals in the second. To make a bar chart with a title:

```text
[gdoc key="ABCDEFG" chart="Bar" title="Total goals per team"]
```

Charts take many options, such as colors. This makes a 3D red and green pie chart whose slices are labeled with your data's values:

```text
[gdoc key="ABCDEFG" chart="Pie" chart_colors="red green" chart_dimensions="3" chart_pie_slice_text="value"]
```

## Pre-processing data with queries

To filter or reshape a Google Spreadsheet or CSV file before it's displayed, pass a [Google Charts API Query Language](https://developers.google.com/chart/interactive/docs/querylanguage#Language_Syntax) query in the `query` attribute. You can then work with the data as if it were a relational database table. For example, if the team name is in column `A` and the score is in column `B`, this shows the highest-scoring team:

```text
[gdoc key="ABCDEFG" query="select A, B order by B desc limit 1"]
```

In queries on CSV files, you can also refer to a column by its header text, such as `select Team where Goals %3E 6`.

Queries also help when one spreadsheet holds complex data for several charts. Each chart can select just the part of the spreadsheet it needs.

## Your WordPress database

Once an administrator turns on the SQL queries option in the plugin's settings screen, privileged users can query the WordPress database. Set `key` to `wordpress` and give a [MySQL `SELECT` statement](https://dev.mysql.com/doc/refman/8.0/en/select.html) in `query`. This can show data that other plugins or WordPress itself store in your site's database.

For example, to list user registration dates from the current site:

```text
[gdoc key="wordpress" query="SELECT display_name AS Name, user_registered AS 'Registration Date' FROM wp_users"]
```

A query runs only after a user with the `gdoc_query_sql_databases` capability (an Administrator, by default) saves the post that contains it. If another user adds or changes a query, it stops running until such a user saves the post again. Only single `SELECT` statements are accepted, and they run read-only. The [reference](reference.md#sql-queries) has the details.

Remote MySQL databases (`mysql://` keys) are no longer supported.

## Google Apps Script web apps

You can also use the URL of any Google Apps Script web app. The app's output is inserted into your post or page, so you can display any data you like. The shortcode works the same way as for Google Spreadsheets.

If the post's author may not publish unfiltered HTML, the app's HTML is filtered like post content, so scripts and other unsafe HTML are removed. If the app returns CSV (with the `text/csv` content type), it's shown as a table.

For example, say listeners of your podcast email their questions to a Gmail account, and you want to show some information about those emails on your website. Sort the emails with [Gmail filters](https://support.google.com/mail/answer/6579?hl=en) and [labels](https://support.google.com/mail/answer/118708?hl=en). Then write a [Google Apps Script](https://developers.google.com/apps-script/overview) that counts the messages under each label and returns the counts as an HTML list fragment. [Deploy that script as a web app](https://developers.google.com/apps-script/guides/web#deploying_a_script_as_a_web_app) and give its URL to the `gdoc` shortcode:

```text
[gdoc key="https://script.google.com/macros/s/ABCDEFG/exec"]
```

Your website then updates on its own whenever a new question arrives.

## Embedding other documents

To show a preview of any file that's online, set `key` to the file's URL. (If the server sends CSV, the plugin shows a table instead.)

```text
[gdoc key="http://example.com/my_final_paper.pdf"]
```

To change how the preview looks, use the `width`, `height`, or `style` attributes:

```text
[gdoc key="http://example.com/my_final_paper.pdf" style="min-height:780px;border:none;"]
```
