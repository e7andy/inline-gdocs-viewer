# Reference

## Shortcode attributes

The plugin has one shortcode, `gdoc`. It does different things depending on the attributes you combine. Every attribute must have a value. The attributes and the values they accept are listed below.

- `key`: the document to retrieve.
    - **Required.** Every `gdoc` shortcode must have exactly one `key` attribute. All other attributes are optional.
    - `key` can be one of six types:
        - The full URL of a publicly shared Google Spreadsheet, like `[gdoc key="https://docs.google.com/spreadsheets/d/ABCDEFG/htmlview#gid=123456"]`
        - The full URL of a Google Apps Script web app, like `[gdoc key="https://script.google.com/macros/s/ABCDEFG/exec"]`
        - The full URL of a CSV file or of a web service that returns CSV data, like `[gdoc key="http://viewportsizes.com/devices.csv"]`
        - The full URL of a document on the web. PDF, DOC, XLS, and other formats that the [Google Docs Viewer](https://googlesystem.blogspot.com/2015/02/google-docs-viewer-page-no-longer.html) supports are shown in the viewer, like `[gdoc key="http://example.com/my_final_paper.pdf"]`
        - The keyword `wordpress`, to run a SQL query on the current site's database, like `[gdoc key="wordpress" query="SELECT * FROM custom_table"]`
        - A MySQL connection URL, to run a SQL query on any MySQL server, like `[gdoc key="mysql://user:password@server.example.com:12345/database" query="SELECT * FROM custom_table"]`
- `chart`: shows the data as a chart instead of a table. Valid values:
    - `AnnotatedTimeLine`
    - `Annotation`
    - `Area`
    - `Bar`
    - `Bubble`
    - `Candlestick`
    - `Column`
    - `Combo`
    - `Gauge`
    - `Geo`
    - `Histogram`
    - `Line`
    - `Pie`
    - `Scatter`
    - `Stepped`
    - `Timeline`
- `class`: a custom HTML `class` value, or a space-separated list of values. These class names have special meanings:
    - `no-datatables` turns off all DataTables features.
    - `no-responsive` turns off only DataTables' [Responsive](https://datatables.net/extensions/responsive/) features.
    - `FixedHeader`, or its synonym `FixedHeader-top`, keeps the table header (its `<thead>` content) at the top of the window while scrolling vertically.
    - `FixedHeader-footer` keeps the table footer (its `<tfoot>` content) at the bottom of the window while scrolling vertically.
    - `FixedHeader-left` or `FixedHeader-right` keeps the leftmost or rightmost column in view while scrolling horizontally. You also need `datatables_scroll_x="true"` in your shortcode to allow horizontal scrolling.
    - `FixedColumns-left-N` or `FixedColumns-right-N` keeps the leftmost or rightmost `N` columns in view. For example, `class="FixedColumns-left-3"` keeps the three leftmost columns in view.
- `csv_headers`: whether to include text headers in a Google Sheet's CSV export when you use `query` or `chart`. Use `1` to include them. (Default: `0`, which leaves them out, the same as Google's default.)
- `expire_in`: how long to cache responses, in seconds. Set it to `0` to cache forever. (Default: `600`, which is 10 minutes.)
- `footer_rows`: how many trailing rows go in the table's `<tfoot>` element. (Default: `0`.)
- `header_cols`: how many cells at the start of each row are written as `<th>` elements. (Default: `0`.)
- `header_rows`: how many leading rows go in the table's `<thead>` element. (Default: `1`.)
- `height`: the height of the containing HTML element. Tables ignore this, so use `style` for them. (Default: calculated automatically.)
- `http_opts`: a JSON string of options for the [WordPress HTTP API](https://codex.wordpress.org/HTTP_API), like `[gdoc key="ABCDEFG" http_opts='{"method": "POST", "blocking": false, "user-agent": "My Custom User Agent String"}']`.
- `lang`: the [ISO 639](https://www.iso.org/iso-639-language-codes.html) language code for the language of the spreadsheet's content. For example, `nl-NL` declares that the content is in Dutch. (Default: your site's [language setting](https://codex.wordpress.org/WordPress_in_Your_Language).)
- `linkify`: whether to turn URLs, email addresses, and the like into clickable links. Set it to `no` to turn this off. (Default: `true`.)
- `query`: a [Google Query Language](https://developers.google.com/chart/interactive/docs/querylanguage#Language_Syntax) query when the data source is a Google Spreadsheet or CSV file, or a SQL `SELECT` statement when it's a MySQL database. *Note:* write angle brackets (`<` and `>`) in queries URL-encoded, as `%3C` and `%3E`, so WordPress doesn't treat them as HTML. (Default: none.)
- `strip`: how many leading rows of the data source to leave out of the HTML table. (Default: `0`.)
- `style`: an inline CSS rule for the containing HTML element. For example, to give a table a fixed height, use `[gdoc key="ABCDEFG" style="height: 480px;"]`. (Default: none.)
- `summary`: a short description of the data for the [`summary` attribute](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/table#attr-summary) of the `<table>`. HTML5 pages shouldn't use this. Use a table caption instead. (Default: none.)
- `title`: a title for your chart or table. Browsers usually show it as a tooltip when you hover over the table, and charts show it as a heading. (Default: none.)
- `use_cache`: whether to cache the spreadsheet data. Set it to `no` to turn off caching for this shortcode. (Default: `true`.)
- `width`: the width of the containing HTML element. Tables ignore this, so use `style` for them. (Default: `100%`.)

## Chart customization options

These options work only together with the `chart` attribute.

The full list of chart option attributes is below. See [Google's Chart Gallery documentation](https://developers.google.com/chart/interactive/docs/gallery) to learn which chart types support which options.

- `chart_aggregation_target`
- `chart_all_values_suffix`
- `chart_allow_html`
- `chart_allow_redraw`
- `chart_animation`
- `chart_annotations`
- `chart_annotations_width`
- `chart_area_opacity`
- `chart_avoid_overlapping_grid_lines`
- `chart_axis_titles_position`
- `chart_background_color`
- `chart_bars`
- `chart_bubble`
- `chart_candlestick`
- `chart_chart_area`
- `chart_color_axis`
- `chart_colors`
- `chart_crosshair`
- `chart_curve_type`
- `chart_data_opacity`
- `chart_dataless_region_color`
- `chart_date_format`
- `chart_default_color`
- `chart_dimensions`
- `chart_display_annotations`
- `chart_display_annotations_filter`
- `chart_display_date_bar_separator`
- `chart_display_exact_values`
- `chart_display_legend_dots`
- `chart_display_legend_values`
- `chart_display_mode`
- `chart_display_range_selector`
- `chart_display_zoom_buttons`
- `chart_domain`
- `chart_enable_interactivity`
- `chart_enable_region_interactivity`
- `chart_explorer`
- `chart_fill`
- `chart_focus_target`
- `chart_font_name`
- `chart_font_size`
- `chart_force_i_frame`
- `chart_green_color`
- `chart_green_from`
- `chart_green_to`
- `chart_h_axes`
- `chart_h_axis`
- `chart_height`
- `chart_highlight_dot`
- `chart_interpolate_nulls`
- `chart_is_stacked`
- `chart_keep_aspect_ratio`
- `chart_legend`
- `chart_line_width`
- `chart_magnifying_glass`
- `chart_major_ticks`
- `chart_marker_opacity`
- `chart_max`
- `chart_min`
- `chart_minor_ticks`
- `chart_number_formats`
- `chart_orientation`
- `chart_pie_hole`
- `chart_pie_residue_slice_color`
- `chart_pie_residue_slice_label`
- `chart_pie_slice_border_color`
- `chart_pie_slice_text`
- `chart_pie_slice_text_style`
- `chart_pie_start_angle`
- `chart_point_shape`
- `chart_point_size`
- `chart_red_color`
- `chart_red_from`
- `chart_red_to`
- `chart_region`
- `chart_resolution`
- `chart_reverse_categories`
- `chart_scale_columns`
- `chart_scale_format`
- `chart_scale_type`
- `chart_selection_mode`
- `chart_series`
- `chart_size_axis`
- `chart_slice_visibility_threshold`
- `chart_slices`
- `chart_table`
- `chart_theme`
- `chart_thickness`
- `chart_timeline`
- `chart_title_position`
- `chart_title_text_style`
- `chart_tooltip`
- `chart_trendlines`
- `chart_v_axes`
- `chart_v_axis`
- `chart_width`
- `chart_wmode`
- `chart_yellow_color`
- `chart_yellow_from`
- `chart_yellow_to`
- `chart_zoom_end_time`
- `chart_zoom_start_time`

## DataTables customization options

These options have no effect on a table that has the `no-datatables` class.

The full list of core DataTables attributes is below. See the [DataTables options reference](https://datatables.net/reference/option/) for details on each option.

- `datatables_auto_width`
- `datatables_defer_render`
- `datatables_info`
- `datatables_j_query_UI`
- `datatables_length_change`
- `datatables_ordering`
- `datatables_paging`
- `datatables_processing`
- `datatables_scroll_x`
- `datatables_scroll_y`
- `datatables_searching`
- `datatables_server_side`
- `datatables_state_save`
- `datatables_ajax`
- `datatables_data`
- `datatables_defer_loading`
- `datatables_destroy`
- `datatables_display_start`
- `datatables_dom`
- `datatables_length_menu`
- `datatables_order_cells_top`
- `datatables_order_classes`
- `datatables_order`
- `datatables_order_fixed`
- `datatables_order_multi`
- `datatables_page_length`
- `datatables_paging_type`
- `datatables_renderer`
- `datatables_retrieve`
- `datatables_scroll_collapse`
- `datatables_search_cols`
- `datatables_search_delay`
- `datatables_search`
- `datatables_state_duration`
- `datatables_stripe_classes`
- `datatables_select`
- `datatables_tab_index`
- `datatables_column_defs`
- `datatables_columns`

The bundled DataTables extensions also have their own attributes:

- `datatables_buttons` customizes the [DataTables Buttons extension](https://datatables.net/extensions/buttons/).

## Plugin hooks

These are the hooks the plugin provides. Developers of other plugins and themes can use them to change how this plugin works.

### Filters

- `gdoc_table_html`: filters the data after it's converted to an HTML `<table>` element.
    - This filter is most often used to run [`html_entity_decode()`](https://www.php.net/html-entity-decode), so that raw HTML in the data source is displayed. For example, a spreadsheet cell containing `<img src="https://example.com/my-image.png" alt="" />` then shows an image. But if a malicious user can edit the data source, this is easy to abuse and is a security risk. Be very careful, and don't do this unless you're sure you need it. A much safer approach is to store only the image's filename (`my-image.png`) in the data source. Your filter function then adds the cell's value to a base URL, so you control where embedded objects come from.
    - A related use is running [WordPress shortcodes](https://codex.wordpress.org/Shortcode) found in the data source. This can also cause problems, such as broken pages, because most shortcode functions don't expect to run inside an HTML `<table>`. Don't do it unless you're sure the shortcodes involved won't cause trouble.
    - This filter runs right after the HTML conversion and *before* the HTML goes through [`make_clickable()`](https://codex.wordpress.org/Function_Reference/make_clickable). The `linkify` attribute therefore still affects the final output whatever your filter does. Don't call `make_clickable()` yourself.
- `gdoc_viewer_html`: like `gdoc_table_html`, but for the `<iframe>` that loads the [Google Docs Viewer](https://googlesystem.blogspot.com/2015/02/google-docs-viewer-page-no-longer.html). Use it, for example, to change the fallback content for browsers that don't support `<iframe>` elements.
- `gdoc_webapp_html`: like `gdoc_table_html`, but for the HTTP response body from a [Google Apps Script web app](https://developers.google.com/apps-script/guides/web). Use it to modify a web app's output, much as you would [filter `the_content`](https://developer.wordpress.org/reference/hooks/the_content/) of a WordPress post. The first argument is the HTTP response body. The second is an array of all the attributes and values passed to this use of the shortcode.
- `gdoc_query`: filters the query. The first argument is the value of the `query` attribute, or `false` if there's no query. The second is an array of all the attributes and values passed to this use of the shortcode.
    - This filter is often used to build a query from dynamic content, such as the current user's email address or username.
- `gdoc_enqueued_front_end_styles`: an array of `$handle => array(...)` entries, each holding the parameters passed to [`wp_enqueue_style()`](https://developer.wordpress.org/reference/functions/wp_enqueue_style/). [`unset()`](https://www.php.net/unset) an entry to stop the plugin from loading that stylesheet. Remove stylesheets you know you won't need to make your site faster.
- `gdoc_enqueued_front_end_scripts`: an array of `$handle => array(...)` entries, each holding the parameters passed to [`wp_enqueue_script()`](https://developer.wordpress.org/reference/functions/wp_enqueue_script/). [`unset()`](https://www.php.net/unset) an entry to stop the plugin from loading that script. Remove scripts you know you won't need to make your site faster.

## Registered script and stylesheet handles

The plugin always loads many scripts, so that it can also enhance tables written directly into a page instead of generated from a data source. To keep these off pages that don't need them, remove handles with the `gdoc_enqueued_front_end_*` filters. The registered handles are listed below.

**Scripts**

- `jquery-datatables`
- `datatables-buttons`
- `datatables-buttons-colvis`
- `datatables-buttons-print`
- `pdfmake`
- `pdfmake-fonts`
- `jszip`
- `datatables-buttons-html5`
- `datatables-select`
- `datatables-fixedheader`
- `datatables-fixedcolumns`
- `datatables-responsive`
- `igsv-datatables`
- `google-ajax-api`
- `igsv-gvizcharts`

**Stylesheets**

- `jquery-datatables`
- `datatables-buttons`
- `datatables-select`
- `datatables-fixedheader`
- `datatables-fixedcolumns`
- `datatables-responsive`
