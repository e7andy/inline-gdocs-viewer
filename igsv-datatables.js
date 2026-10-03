/**
 * Inline Google Spreadsheet Viewer's DataTables integrations.
 *
 * @file Loads and applies DataTables to any tables on the page.
 * @license GPL-3.0
 */

(function () { // start immediately-invoked function expresion (IIFE)

// DataTables
jQuery(function () {
    var DataTable = window.DataTable || jQuery.fn.dataTable;
    if (!DataTable || typeof igsv_plugin_vars === 'undefined') {
        return;
    }

    // Set/load defaults.
    if (igsv_plugin_vars.datatables_defaults_object) {
        jQuery.extend(true, DataTable.defaults, igsv_plugin_vars.datatables_defaults_object);
    }

    var languages = igsv_plugin_vars.languages || [];

    // Initialize tables.
    jQuery(igsv_plugin_vars.datatables_classes).each(function () {
        var table = jQuery(this);
        var dt_opts = {};
        var x, i;

        if (false === table.hasClass('no-responsive')) {
            dt_opts.responsive = true;
        }

        // Only load translations that ship with the plugin.
        var lang = table.attr('lang');
        if (lang && -1 !== jQuery.inArray(lang, languages)) {
            dt_opts.language = {
                'url': igsv_plugin_vars.lang_dir + '/datatables-' + lang + '.json'
            };
        }

        // FixedHeader: freeze the header and/or footer.
        if (table.is('.FixedHeader') || table.is('.FixedHeader-top')) {
            dt_opts.fixedHeader = { header: true, footer: table.is('.FixedHeader-footer') };
        } else if (table.is('.FixedHeader-footer')) {
            dt_opts.fixedHeader = { header: false, footer: true };
        }

        // FixedColumns: freeze columns at the start (left) or end (right).
        var start = 0;
        var end = 0;
        if (table.is('.FixedHeader-left')) {
            start = 1;
        }
        if (table.is('.FixedHeader-right')) {
            end = 1;
        }
        if (table.is('.FixedColumns')) {
            start = Math.max(start, 1);
        }
        if ((x = this.className.match(/FixedColumns-(left|right)-([0-9]+)/g))) {
            for (i = 0; i < x.length; i++) {
                var z = x[i].split('-');
                if ('left' === z[1]) {
                    start = parseInt(z[2], 10);
                } else {
                    end = parseInt(z[2], 10);
                }
            }
        }
        if (start || end) {
            dt_opts.fixedColumns = { start: start, end: end };
            // Fixed columns need horizontal scrolling instead of Responsive.
            dt_opts.scrollX = true;
            delete dt_opts.responsive;
        }

        new DataTable(this, dt_opts);
    });
});

})(); // end IIFE
