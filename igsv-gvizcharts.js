/**
 * Inline Google Spreadsheet Viewer's Google Chart API integrations.
 *
 * @file Loads and draws Google Chart API visualizations.
 * @license GPL-3.0
 */

(function () { // start immediately-invoked function expresion (IIFE)

if (typeof google === 'undefined' || !google.charts) {
    return;
}

google.charts.load('current', {
    'packages' : [
        'corechart',
        'annotationchart',
        'gauge',
        'geochart',
        'timeline'
    ],
    'language' : (document.documentElement.lang || 'en').split('-')[0]
});

google.charts.setOnLoadCallback(function () {
    jQuery('.igsv-chart').each(drawChart);
});

function drawChart () {
    var el = this,
        query = new google.visualization.Query(jQuery(el).data('datasource-href')),
        options = lowerFirstCharInKeys(stripPrefixInKeys('chart', jQuery(el).data())),
        x;

    // Special-case chart options.
    options.title = jQuery(el).attr('title');
    delete options.type; // handled elsewhere
    delete options.datasourceHref;
    for (x in options) {
        switch (x) {
            case 'colors':
                options.colors = String(jQuery(el).data('chart-colors')).split(' ');
                break;
            case 'dimensions':
                options.is3D = ('3' === String(options.dimensions));
                delete options.dimensions;
                break;
        }
    }

    query.send(function (response) {
        if (response.isError()) {
            google.visualization.errors.addErrorFromQueryResponse(el, response);
            return;
        }
        var data = response.getDataTable(),
            chart;
        switch (String(jQuery(el).data('chart-type')).toLowerCase()) {
            case 'annotation':
            case 'annotatedtimeline': // retired by Google; draw an Annotation chart instead
                chart = new google.visualization.AnnotationChart(el);
                break;
            case 'area':
                chart = new google.visualization.AreaChart(el);
                break;
            case 'bar':
                chart = new google.visualization.BarChart(el);
                break;
            case 'bubble':
                chart = new google.visualization.BubbleChart(el);
                break;
            case 'candlestick':
                chart = new google.visualization.CandlestickChart(el);
                break;
            case 'combo':
                chart = new google.visualization.ComboChart(el);
                break;
            case 'gauge':
                chart = new google.visualization.Gauge(el);
                break;
            case 'geo':
                chart = new google.visualization.GeoChart(el);
                break;
            case 'histogram':
                chart = new google.visualization.Histogram(el);
                break;
            case 'line':
                chart = new google.visualization.LineChart(el);
                break;
            case 'pie':
                chart = new google.visualization.PieChart(el);
                break;
            case 'scatter':
                chart = new google.visualization.ScatterChart(el);
                break;
            case 'stepped':
            case 'steppedarea':
                chart = new google.visualization.SteppedAreaChart(el);
                break;
            case 'timeline':
                chart = new google.visualization.Timeline(el);
                break;
            case 'column':
            default:
                chart = new google.visualization.ColumnChart(el);
                break;
        }
        chart.draw(data, options);
    });
}

// Chart helpers.
function stripPrefixInKeys (prefix, obj) {
    var new_obj = {}, x;
    for (x in obj) {
        if (0 === x.indexOf(prefix)) {
            new_obj[x.slice(prefix.length)] = obj[x];
        } else {
            new_obj[x] = obj[x];
        }
    }
    return new_obj;
}
function lowerFirstCharInKeys (obj) {
    var new_obj = {}, x;
    for (x in obj) {
        new_obj[x.charAt(0).toLowerCase() + x.slice(1)] = obj[x];
    }
    return new_obj;
}

})(); // end IIFE
