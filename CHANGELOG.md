# Changelog

## Unreleased (fork)

Changes made on 2026-10-03 in the fork at <https://github.com/e7andy/inline-gdocs-viewer>, a modified version of <https://github.com/fabacab/inline-gdocs-viewer>.

- Maintenance: Remove links to the plugin's former WordPress.org directory listing and support forum. The plugin URI now points to the GitHub repository, and the admin documentation links now point to `docs/reference.md` there. The help tab no longer refers to the support forum.
- Documentation: Replace `readme.txt` (the WordPress.org readme) with `README.md`, `docs/user-guide.md`, `docs/faq.md`, `docs/reference.md`, and this changelog. Leave out the donation links, the original author's support statement, and the screenshot captions, whose images were hosted on WordPress.org. Edit the documentation for clarity.
- Documentation: Fix the `query`, `datatables_order`, and chart color examples.
- Documentation: Add `CLAUDE.md` and the full GPL-3.0 text in `LICENSE`.

## 0.13.2

- Maintenance: Update DataTables libraries. No backwards incompatible changes are expected.

## 0.13.1

- Enhancement: New `csv_headers` shortcode attribute adds support for Google Sheet Query Language HTTP endpoint `headers` parameter. Using `csv_headers=1` in your shortcode may help if you find headers exported from a Google Sheet are missing.

## 0.13.0

This is a maintenance and compatibility update that adds support for the Block Editor in WordPress 5.x and higher.

- Compatibility: Officially support WordPress 5.x and the Block Editor. This update removes the deprecated QuickTags integration from the Classic Editor and fixes minor author-side rendering bugs on WP 5.x.
- Bugfix: Protect against "Undefined index" error when a user enters an incomplete `key` value.

## 0.12.9

- Bugfix: Fix incorrect spacing causing invalid HTML table row markup.

## 0.12.8

- Bugfix: Fix invalid HTML output affecting some CSS hooks.

## 0.12.7

- [Feature](https://github.com/fabacab/inline-gdocs-viewer/pull/23): Add HTML IDs to table rows. Thanks, @ThaiWood. :)
- Bugfix: Compatibility with PHP 7.0 and later.

## 0.12.6

- Update DataTables libraries to current release versions.
- Minor code cleanup. (Fixes broken links in readme, code style, etc.)

## 0.12.5

- Bugfix: Remove `chart_legend_position` attribute and update the documentation. This should be `chart_legend` with a JSON object attribute value.

## 0.12.4

- Enhancement: Google Charts now accept fallback content as part of the shortcode like other options.
- Bugfix: Support URL-encoded MySQL connection strings.
- Security: MySQL datasources have been hardened, but their HTML IDs have changed. If you have styles or scripts looking for specific HTML IDs, you will need to update those resources to match the newly generated ID values.

## 0.12.3

- Bugfix: Fix errors when using MySQL database access shortcodes.

## 0.12.2

- Feature: Support [Google GeoCharts](https://developers.google.com/chart/interactive/docs/gallery/geochart) using the `Geo` chart type (`[gdoc key="ABCDEFG" chart="Geo"]`).
- Bugfix: Exception handling no longer fails to return a human-readable error when using SQL data sources.

## 0.12.1

- Bugfix: Fix regression during activation.

## 0.12

- Automatically force new-style URLs for `key` attribute values that are still using deprecated old-style document IDs.
- Bugfix: Fix collision with class names in some cases. This change requires the use of PHP 5.3 or later.
