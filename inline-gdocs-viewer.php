<?php
/**
 * The Inline Google Spreadsheets Viewer plugin for WordPress.
 *
 * WordPress plugin header information:
 *
 * * Plugin Name: Inline Google Spreadsheet Viewer
 * * Plugin URI: https://github.com/e7andy/inline-gdocs-viewer
 * * Description: Retrieves data from a public Google Spreadsheet or CSV file and displays it as an HTML table or interactive chart. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Inline%20Google%20Spreadsheet%20Viewer&amp;item_number=Inline%20Google%20Spreadsheet%20Viewer&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the developer of Inline Google Spreadsheet Viewer">donate</a>. &hearts; Thank you!</strong>
 * * Version: 1.0.1
 * * Requires at least: 6.0
 * * Requires PHP: 7.4
 * * Text Domain: inline-gdocs-viewer
 * * Domain Path: /languages
 *
 * @link https://developer.wordpress.org/plugins/the-basics/header-requirements/
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * Modified 2026-10-03 in the fork at https://github.com/e7andy/inline-gdocs-viewer
 * (original: https://github.com/fabacab/inline-gdocs-viewer). See the change log
 * in CHANGELOG.md for details.
 *
 * @package WordPress\Plugin\InlineGoogleSpreadsheetViewer
 */

namespace WP_IGSV;

if ( ! defined( 'ABSPATH' ) ) { exit(); } // Disallow direct HTTP access.

/**
 * Plugin class.
 */
class InlineGoogleSpreadsheetViewerPlugin {

    /**
     * The shortcode itself.
     *
     * @var string
     */
    const shortcode = 'gdoc';

    /**
     * Internal prefix for settings, etc., derived from shortcode.
     *
     * @var string
     */
    const prefix = 'gdoc_';

    /**
     * Plugin version, used to bust browser caches of bundled assets.
     *
     * @var string
     */
    const version = '1.0.1';

    /**
     * Post meta key listing the SQL shortcodes that a user allowed to run SQL saved.
     *
     * @var string
     */
    const sql_meta_key = '_gdoc_sql_authorized';

    /**
     * Query parameter for the chart data source endpoint.
     *
     * @var string
     */
    const datasource_param = 'igsv_datasource';

    /**
     * Default table class.
     *
     * @var string
     */
    private static $dt_class = 'igsv-table';

    /**
     * Number of invocations for each page load.
     *
     * @var int
     */
    private $invocations = 0;

    /**
     * Regular expression to match a Google Sheet address in an URI.
     *
     * @var string
     */
    private static $gdoc_url_regex =
        '!https://(?:docs\.google\.com/spreadsheets/d/|script\.google\.com/macros/s/)([^/]+)!';

    /**
     * Chart types that the front-end script can draw.
     *
     * @var string[]
     */
    private static $chart_types = array(
        'Annotation', 'Area', 'Bar', 'Bubble', 'Candlestick', 'Column', 'Combo', 'Gauge',
        'Geo', 'Histogram', 'Line', 'Pie', 'Scatter', 'Stepped', 'Timeline',
    );

    /**
     * Script handles that only charts need.
     *
     * @var string[]
     */
    private static $chart_script_handles = array( 'google-ajax-api', 'igsv-gvizcharts' );

    /**
     * Handles of the scripts and styles registered by this plugin, after filtering.
     *
     * @var array
     */
    private static $registered = array( 'scripts' => array(), 'styles' => array() );

    /**
     * Assets that shortcodes rendered before `wp_enqueue_scripts` need.
     *
     * Block themes render post content before that hook runs, so the
     * assets are enqueued when it does.
     *
     * @var array
     */
    private static $needed = array( 'table' => false, 'chart' => false );

    /**
     * Entry code for WordPress framework.
     */
    public static function register () {
        add_action( 'plugins_loaded', array( __CLASS__, 'registerL10n' ) );
        add_action( 'init', array( __CLASS__, 'maybeServeDatasource' ) );
        add_action( 'admin_init', array( __CLASS__, 'registerSettings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'registerAdminMenu' ) );
        add_action( 'admin_head', array( __CLASS__, 'registerContextualHelp' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'addAdminScripts' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'addFrontEndScripts' ) );
        add_action( 'save_post', array( __CLASS__, 'authorizeSqlShortcodes' ), 10, 2 );

        $plugin = new self();
        add_shortcode( self::shortcode, array( $plugin, 'displayShortcode' ) );

        wp_embed_register_handler(
            self::shortcode . 'spreadsheet',
            self::$gdoc_url_regex,
            array( __CLASS__, 'oEmbedHandler' )
        );

        register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
    }

    /**
     * The DataTables options the plugin uses unless the site sets its own.
     *
     * @return array
     */
    private static function getDefaultDataTablesOptions () {
        return array(
            'layout'  => array( 'top1' => 'buttons' ),
            'buttons' => array( 'colvis', 'copy', 'csv', 'excel', 'pdf', 'print' ),
        );
    }

    /**
     * Sets up plugin during activation.
     */
    public static function activate () {
        $options = get_option( self::prefix . 'settings' );
        if ( ! is_array( $options ) ) {
            $options = array();
        }
        if ( ! isset( $options['datatables_classes'] ) ) {
            $options['datatables_classes'] = self::$dt_class;
        }
        if ( empty( $options['datatables_defaults_object'] ) ) {
            $options['datatables_defaults_object'] = self::getDefaultDataTablesOptions();
        }
        update_option( self::prefix . 'settings', $options );
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( self::prefix . 'query_sql_databases', true );
        }
    }

    /**
     * Loads i18n from languages directory.
     */
    public static function registerL10n () {
        load_plugin_textdomain( 'inline-gdocs-viewer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * @see https://developer.wordpress.org/reference/hooks/admin_init/
     */
    public static function registerSettings () {
        register_setting(
            self::prefix . 'settings',
            self::prefix . 'settings',
            array( __CLASS__, 'validateSettings' )
        );
    }

    /**
     * Adds the option page.
     */
    public static function registerAdminMenu () {
        add_options_page(
            __( 'Inline Google Spreadsheet Viewer Settings', 'inline-gdocs-viewer' ),
            __( 'Inline Google Spreadsheet Viewer', 'inline-gdocs-viewer' ),
            'manage_options',
            self::prefix . 'settings',
            array( __CLASS__, 'renderOptionsPage' )
        );
    }

    /**
     * @see https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
     */
    public static function addAdminScripts () {
        wp_enqueue_style(
            'inline-gdocs-viewer',
            plugins_url( 'inline-gdocs-viewer.css', __FILE__ ),
            array(),
            self::version
        );
    }

    /**
     * Returns the URL of a bundled third-party file.
     *
     * @param string $path Path relative to assets/vendor/.
     *
     * @return string
     */
    private static function vendorUrl ( $path ) {
        return plugins_url( 'assets/vendor/' . $path, __FILE__ );
    }

    /**
     * Registers the front-end scripts and styles, and enqueues them when the
     * current page needs them.
     *
     * Scripts are enqueued when a shortcode renders a table or chart, when the
     * queried posts contain the shortcode, or on every page if the "load
     * everywhere" setting is on (for tables written by hand).
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
     */
    public static function addFrontEndScripts () {
        $styles = array(
            'jquery-datatables' => array(
                'src' => self::vendorUrl( 'datatables/dataTables.dataTables.min.css' ),
            ),
            'datatables-buttons' => array(
                'src' => self::vendorUrl( 'datatables-buttons/buttons.dataTables.min.css' ),
            ),
            'datatables-select' => array(
                'src' => self::vendorUrl( 'datatables-select/select.dataTables.min.css' ),
            ),
            'datatables-fixedheader' => array(
                'src' => self::vendorUrl( 'datatables-fixedheader/fixedHeader.dataTables.min.css' ),
            ),
            'datatables-fixedcolumns' => array(
                'src' => self::vendorUrl( 'datatables-fixedcolumns/fixedColumns.dataTables.min.css' ),
            ),
            'datatables-responsive' => array(
                'src' => self::vendorUrl( 'datatables-responsive/responsive.dataTables.min.css' ),
            ),
        );

        $scripts = array(
            'jquery-datatables' => array(
                'src'  => self::vendorUrl( 'datatables/dataTables.min.js' ),
                'deps' => array( 'jquery' ),
            ),
            'datatables-buttons' => array(
                'src'  => self::vendorUrl( 'datatables-buttons/dataTables.buttons.min.js' ),
                'deps' => array( 'jquery-datatables' ),
            ),
            'datatables-buttons-colvis' => array(
                'src'  => self::vendorUrl( 'datatables-buttons/buttons.colVis.min.js' ),
                'deps' => array( 'datatables-buttons' ),
            ),
            'datatables-buttons-print' => array(
                'src'  => self::vendorUrl( 'datatables-buttons/buttons.print.min.js' ),
                'deps' => array( 'datatables-buttons' ),
            ),
            // PDFMake (required for DataTables' PDF buttons)
            'pdfmake' => array(
                'src'  => self::vendorUrl( 'pdfmake/pdfmake.min.js' ),
                'deps' => array( 'datatables-buttons' ),
            ),
            'pdfmake-fonts' => array(
                'src'  => self::vendorUrl( 'pdfmake/vfs_fonts.js' ),
                'deps' => array( 'pdfmake' ),
            ),
            // JSZip (required for DataTables' Excel button)
            'jszip' => array(
                'src'  => self::vendorUrl( 'jszip/jszip.min.js' ),
                'deps' => array( 'datatables-buttons' ),
            ),
            'datatables-buttons-html5' => array(
                'src'  => self::vendorUrl( 'datatables-buttons/buttons.html5.min.js' ),
                'deps' => array( 'datatables-buttons' ),
            ),
            'datatables-select' => array(
                'src'  => self::vendorUrl( 'datatables-select/dataTables.select.min.js' ),
                'deps' => array( 'jquery-datatables' ),
            ),
            'datatables-fixedheader' => array(
                'src'  => self::vendorUrl( 'datatables-fixedheader/dataTables.fixedHeader.min.js' ),
                'deps' => array( 'jquery-datatables' ),
            ),
            'datatables-fixedcolumns' => array(
                'src'  => self::vendorUrl( 'datatables-fixedcolumns/dataTables.fixedColumns.min.js' ),
                'deps' => array( 'jquery-datatables' ),
            ),
            'datatables-responsive' => array(
                'src'  => self::vendorUrl( 'datatables-responsive/dataTables.responsive.min.js' ),
                'deps' => array( 'jquery-datatables' ),
            ),
            'igsv-datatables' => array(
                'src'  => plugins_url( 'igsv-datatables.js', __FILE__ ),
                'deps' => array( 'jquery-datatables' ),
            ),
            // Google Charts loader. The handle keeps its old name so existing
            // `gdoc_enqueued_front_end_scripts` filters keep working.
            'google-ajax-api' => array(
                'src' => 'https://www.gstatic.com/charts/loader.js',
                'ver' => null,
            ),
            'igsv-gvizcharts' => array(
                'src'  => plugins_url( 'igsv-gvizcharts.js', __FILE__ ),
                'deps' => array( 'jquery', 'google-ajax-api' ),
            ),
        );

        $styles  = apply_filters( self::prefix . 'enqueued_front_end_styles', $styles );
        $scripts = apply_filters( self::prefix . 'enqueued_front_end_scripts', $scripts );

        self::$registered = array( 'scripts' => array(), 'styles' => array() );
        foreach ( (array) $styles as $handle => $style ) {
            wp_register_style( $handle, $style['src'], array(), array_key_exists( 'ver', $style ) ? $style['ver'] : self::version );
            self::$registered['styles'][] = $handle;
        }
        foreach ( (array) $scripts as $handle => $script ) {
            wp_register_script(
                $handle,
                $script['src'],
                isset( $script['deps'] ) ? array_values( array_filter( $script['deps'], function ( $dep ) use ( $scripts ) {
                    return 'jquery' === $dep || isset( $scripts[ $dep ] );
                } ) ) : array(),
                array_key_exists( 'ver', $script ) ? $script['ver'] : self::version,
                true
            );
            self::$registered['scripts'][] = $handle;
        }

        if ( wp_script_is( 'igsv-datatables', 'registered' ) ) {
            wp_localize_script( 'igsv-datatables', 'igsv_plugin_vars', self::getLocalizedPluginVars() );
        }

        $options = get_option( self::prefix . 'settings', array() );
        if ( self::$needed['table'] || ! empty( $options['load_assets_everywhere'] ) || self::queriedPostsUseShortcode() ) {
            self::enqueueTableAssets();
        }
        if ( self::$needed['chart'] ) {
            self::enqueueChartAssets();
        }
        self::$needed = array( 'table' => false, 'chart' => false );
    }

    /**
     * Whether any of the main query's posts contain the shortcode.
     *
     * @return bool
     */
    private static function queriedPostsUseShortcode () {
        global $wp_query;
        if ( empty( $wp_query ) || empty( $wp_query->posts ) ) {
            return false;
        }
        foreach ( $wp_query->posts as $post ) {
            if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, self::shortcode ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Enqueues the registered scripts and styles that tables need.
     */
    private static function enqueueTableAssets () {
        if ( ! did_action( 'wp_enqueue_scripts' ) ) {
            self::$needed['table'] = true;
            return;
        }
        foreach ( self::$registered['styles'] as $handle ) {
            wp_enqueue_style( $handle );
        }
        foreach ( self::$registered['scripts'] as $handle ) {
            if ( ! in_array( $handle, self::$chart_script_handles, true ) ) {
                wp_enqueue_script( $handle );
            }
        }
    }

    /**
     * Enqueues the registered scripts that charts need.
     */
    private static function enqueueChartAssets () {
        if ( ! did_action( 'wp_enqueue_scripts' ) ) {
            self::$needed['chart'] = true;
            return;
        }
        foreach ( self::$chart_script_handles as $handle ) {
            if ( in_array( $handle, self::$registered['scripts'], true ) ) {
                wp_enqueue_script( $handle );
            }
        }
    }

    /**
     * Deterministically makes a unique transient name.
     *
     * Names start with the shortcode so that uninstall.php can remove them.
     *
     * @param array $parts Everything that changes the HTTP request.
     *
     * @return string
     *
     * @see https://developer.wordpress.org/apis/transients/
     */
    private static function getTransientName ( array $parts ) {
        return self::shortcode . '_' . hash( 'sha1', wp_json_encode( $parts ) );
    }

    /**
     * Gets a cached response.
     *
     * Responses are cached as JSON (body, content type, and status code
     * only), never as serialized PHP objects.
     *
     * @param string $transient
     *
     * @return array|false
     */
    private static function getTransient ( $transient ) {
        $cached = get_transient( $transient );
        if ( ! is_string( $cached ) ) {
            return false;
        }
        $data = json_decode( $cached, true );
        if ( ! is_array( $data ) || ! isset( $data['body'], $data['content_type'], $data['code'] ) || ! is_string( $data['body'] ) ) {
            return false;
        }
        return $data;
    }

    /**
     * Caches a response.
     *
     * @param string $transient
     * @param array  $data
     * @param int    $expiry
     *
     * @return bool
     */
    private static function setTransient ( $transient, array $data, $expiry ) {
        return set_transient( $transient, wp_json_encode( $data ), max( 0, (int) $expiry ) );
    }

    /**
     * Lazily tests the provided Google Doc "key" (URL or document ID)
     * to determine what type of document it really is. Valid doc
     * types are one of: `spreadsheet`, `gasapp`, `docsviewer`, `csv`,
     * `wpdb`, or `mysql`.
     *
     * @param string $key The key passed from the shortcode.
     * @return string A keyword referring to the type of document the key refers to.
     */
    private static function getDocTypeByKey ( $key ) {
        $p    = wp_parse_url( (string) $key );
        $p    = is_array( $p ) ? $p : array();
        $path = isset( $p['path'] ) ? $p['path'] : '';
        if ( 'csv' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
            return 'csv';
        }
        if ( empty( $p['scheme'] ) && 'wordpress' === $path ) {
            return 'wpdb';
        }
        if ( isset( $p['scheme'] ) && 'mysql' === strtolower( $p['scheme'] ) ) {
            return 'mysql';
        }
        if ( isset( $p['host'] ) ) {
            switch ( strtolower( $p['host'] ) ) {
                case 'docs.google.com':
                    return 'spreadsheet';
                case 'script.google.com':
                    return 'gasapp';
                default:
                    return 'docsviewer';
            }
        }
        return 'spreadsheet';
    }

    /**
     * Gets a Google spreadsheet URL from its key.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string
     */
    private function getSpreadsheetUrl ( $atts ) {
        $parts = wp_parse_url( (string) $atts['key'] );
        $parts = is_array( $parts ) ? $parts : array();
        $path  = isset( $parts['path'] ) ? $parts['path'] : '';
        // Force a full URL path if only the document ID was passed in.
        if ( false === strpos( $path, '/' ) ) {
            $path = '/spreadsheets/d/' . rawurlencode( $path ) . '/view';
        }
        $gid = $atts['gid'];
        if ( ! empty( $parts['fragment'] ) ) {
            $frag = array();
            parse_str( $parts['fragment'], $frag );
            if ( ! empty( $frag['gid'] ) ) {
                $gid = $frag['gid'];
            }
        }
        // Google serves sheets over HTTPS only, from docs.google.com.
        $doc_url = 'https://docs.google.com' . $path;
        $action  = ( $atts['query'] || $atts['chart'] )
            ? 'gviz/tq?tqx=out:csv&tq=' . rawurlencode( (string) $atts['query'] ) . '&headers=' . absint( $atts['csv_headers'] )
            : 'export?format=csv';
        $m = array();
        preg_match( '/\/(edit|view|pubhtml|htmlview).*$/', $doc_url, $m );
        $url = empty( $m[0] )
            ? trailingslashit( $doc_url ) . $action
            : substr( $doc_url, 0, -strlen( $m[0] ) ) . '/' . $action;
        if ( false !== $gid && '' !== (string) $gid && preg_match( '/^\d+/', (string) $gid, $gm ) ) {
            $url .= '&gid=' . $gm[0];
        }
        return $url;
    }

    /**
     * Returns the signed URL of this site's chart data source endpoint.
     *
     * The data source definition is signed with the site's secret salt, so
     * the endpoint only ever fetches URLs that a shortcode on this site asked
     * for. Nothing is stored in the database.
     *
     * @param string $key   The data source URL.
     * @param string $query The Google Visualization Query Language query.
     *
     * @return string
     */
    private static function getDatasourceUrl ( $key, $query ) {
        $src = self::base64UrlEncode( wp_json_encode( array( 'k' => (string) $key, 'q' => (string) $query ) ) );
        return add_query_arg(
            array(
                self::datasource_param => 1,
                'igsv_src'             => $src,
                'igsv_sig'             => self::signDatasource( $src ),
            ),
            home_url( '/' )
        );
    }

    /**
     * @param string $src
     *
     * @return string
     */
    private static function signDatasource ( $src ) {
        return hash_hmac( 'sha256', 'igsv_datasource|' . $src, wp_salt( 'auth' ) );
    }

    /**
     * @param string $data
     *
     * @return string
     */
    private static function base64UrlEncode ( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * @param string $data
     *
     * @return string|false
     */
    private static function base64UrlDecode ( $data ) {
        return base64_decode( strtr( (string) $data, '-_', '+/' ), true );
    }

    /**
     * Sanitizes the "query" part of the shortcode.
     *
     * @param string $query
     *
     * @return string
     */
    private static function sanitizeQuery ( $query ) {
        // Due to shortcode parsing limitations of angle brackets (< and > characters),
        // manually decode only the URL encoded values for those values, which are
        // themselves expected to be entered manually by the user. That is, to supply
        // the shortcode with a less than sign, the user ought enter %3C, but after
        // the initial urlencode($query), this will encode the percent sign, returning
        // instead the value %253C, so we manually replace this in the query ourselves.
        return rawurldecode(
            str_replace(
                '%253E',
                '%3E',
                str_replace( '%253C', '%3C', rawurlencode( (string) $query ) )
            )
        );
    }

    /**
     * Gets the shortcode's ID for output as an HTML ID attribute.
     *
     * @param string $key
     *
     * @return string
     */
    private function getDocId ( $key ) {
        $m = array();
        preg_match( self::$gdoc_url_regex, (string) $key, $m );
        if ( ! empty( $m[1] ) ) {
            return $m[1];
        }
        if ( in_array( self::getDocTypeByKey( $key ), array( 'wpdb', 'mysql' ), true ) ) {
            return 'sql-' . substr( hash( 'sha256', wp_salt() . $key ), 0, 16 );
        }
        return sanitize_title_with_dashes( $key );
    }

    /**
     * Gets the roles permitted to use SQL statements in shortcodes.
     *
     * @return array The roles capable of executing SQL directly from a shortcode.
     */
    private static function getSqlCapableRoles () {
        $sql_capable_roles = array();
        foreach ( wp_roles()->roles as $k => $v ) {
            if ( ! empty( $v['capabilities'][ self::prefix . 'query_sql_databases' ] ) ) {
                $sql_capable_roles[ $k ] = $v;
            }
        }
        return $sql_capable_roles;
    }

    /**
     * Whether a content type (without parameters such as charset) is CSV.
     *
     * @param string $content_type
     *
     * @return bool
     */
    private static function isCsvContentType ( $content_type ) {
        return in_array( strtolower( (string) $content_type ), array( 'text/csv', 'application/csv', 'text/comma-separated-values' ), true );
    }

    /**
     * Asks a server what type of content a URL serves, with a HEAD request.
     *
     * Used to tell CSV data apart from documents for the Google Docs Viewer.
     * The answer is cached like other responses. Returns an empty string if
     * the URL may not be fetched or the request fails; the caller then shows
     * the Docs Viewer, which fetches the document from Google's servers.
     *
     * @param string $url
     * @param array  $x   Shortcode attributes (`use_cache`, `expire_in`).
     *
     * @return string The content type, such as `text/csv`, or ''.
     */
    private function detectContentType ( $url, $x ) {
        $transient = self::getTransientName( array( 'HEAD', $url ) );
        $use_cache = ! ( false === $x['use_cache'] || 'no' === strtolower( (string) $x['use_cache'] ) );
        if ( $use_cache ) {
            $cached = self::getTransient( $transient );
            if ( false !== $cached ) {
                return $cached['content_type'];
            }
        }
        try {
            $response = self::doHttpRequest( $url, array( 'method' => 'HEAD', 'timeout' => 10 ) );
        } catch ( \Exception $e ) {
            $response = array( 'body' => '', 'content_type' => '', 'code' => 0 );
        }
        if ( $use_cache ) {
            // Failures are cached too, so a slow or broken server isn't asked on every page view.
            self::setTransient( $transient, array( 'body' => '', 'content_type' => $response['content_type'], 'code' => $response['code'] ), (int) $x['expire_in'] );
        }
        return $response['content_type'];
    }

    /**
     * Retrieves data from the transient cache if available, or via HTTP if not.
     *
     * @param string $url The URL to fetch, if not in cache.
     * @param array  $x   Values from the shortcode attributes.
     *
     * @return array With `body`, `content_type`, and `code`.
     *
     * @throws \RuntimeException
     */
    private function fetchData ( $url, $x ) {
        $http_args = self::sanitizeHttpOpts( $x['http_opts'] );
        $transient = self::getTransientName( array( $url, $http_args ) );
        $use_cache = ! ( false === $x['use_cache'] || 'no' === strtolower( (string) $x['use_cache'] ) );
        if ( $use_cache ) {
            $cached = self::getTransient( $transient );
            if ( false !== $cached ) {
                return $cached;
            }
        } else {
            delete_transient( $transient );
        }
        $response = self::doHttpRequest( $url, $http_args );
        if ( $use_cache ) {
            self::setTransient( $transient, $response, (int) $x['expire_in'] );
        }
        return $response;
    }

    /**
     * Turns the shortcode's `http_opts` JSON into safe WordPress HTTP API arguments.
     *
     * Only a few harmless options are allowed. Options such as `stream`,
     * `filename`, `sslverify`, or `reject_unsafe_urls` are ignored, because
     * they would let an author write files on the server or reach internal
     * services.
     *
     * @param string|false $opts A JSON string.
     *
     * @return array
     */
    private static function sanitizeHttpOpts ( $opts ) {
        $args = array();
        if ( ! $opts ) {
            return $args;
        }
        $decoded = json_decode( (string) $opts, true );
        if ( ! is_array( $decoded ) ) {
            return $args;
        }
        foreach ( $decoded as $k => $v ) {
            switch ( $k ) {
                case 'method':
                    $method = strtoupper( (string) $v );
                    if ( in_array( $method, array( 'GET', 'POST', 'HEAD' ), true ) ) {
                        $args['method'] = $method;
                    }
                    break;
                case 'timeout':
                    $args['timeout'] = min( 30, max( 1, (int) $v ) );
                    break;
                case 'redirection':
                    $args['redirection'] = min( 5, max( 0, (int) $v ) );
                    break;
                case 'user-agent':
                    if ( is_scalar( $v ) ) {
                        $args['user-agent'] = sanitize_text_field( (string) $v );
                    }
                    break;
                case 'headers':
                    if ( is_array( $v ) ) {
                        $args['headers'] = array();
                        foreach ( $v as $name => $value ) {
                            if ( is_string( $name ) && preg_match( '/^[A-Za-z0-9-]+$/', $name ) && is_scalar( $value ) ) {
                                $args['headers'][ $name ] = str_replace( array( "\r", "\n" ), '', (string) $value );
                            }
                        }
                    }
                    break;
                case 'body':
                    if ( is_string( $v ) || is_array( $v ) ) {
                        $args['body'] = $v;
                    }
                    break;
            }
        }
        return $args;
    }

    /**
     * Whether a URL may be fetched: HTTP(S), on a standard port, and on a
     * host that resolves only to public IP addresses.
     *
     * @param string $url
     *
     * @return bool
     */
    private static function isUrlAllowed ( $url ) {
        $p = wp_parse_url( (string) $url );
        if ( ! is_array( $p ) || empty( $p['host'] ) || empty( $p['scheme'] ) ) {
            return false;
        }
        if ( ! in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) ) {
            return false;
        }
        if ( isset( $p['user'] ) || isset( $p['pass'] ) ) {
            return false;
        }
        $allowed = true;
        $host    = trim( strtolower( $p['host'] ), '[]' );
        if ( 'localhost' === $host || '.localhost' === substr( $host, -10 ) ) {
            $allowed = false;
        } else {
            $ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );
            if ( empty( $ips ) ) {
                $allowed = false;
            }
            foreach ( $ips as $ip ) {
                if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    $allowed = false;
                }
            }
        }
        /**
         * Filters whether the plugin may fetch a URL.
         *
         * By default only public HTTP(S) addresses are allowed. Return true
         * to allow, for example, a CSV file on your intranet.
         *
         * @param bool   $allowed
         * @param string $url
         */
        return (bool) apply_filters( self::shortcode . '_url_allowed', $allowed, $url );
    }

    /**
     * Refuses redirects to URLs that may not be fetched.
     *
     * Hooked to the Requests library's `requests.before_redirect` event
     * while the plugin makes a request.
     *
     * @param string $location The redirect target.
     *
     * @throws \WpOrg\Requests\Exception
     */
    public static function validateRedirect ( &$location ) {
        if ( ! self::isUrlAllowed( $location ) ) {
            throw new \WpOrg\Requests\Exception(
                __( 'Redirect to a disallowed address.', 'inline-gdocs-viewer' ),
                'igsv_unsafe_redirect'
            );
        }
    }

    /**
     * Performs an HTTP request.
     *
     * @param string $url       The URL to request.
     * @param array  $http_args Sanitized WordPress HTTP API arguments.
     *
     * @return array With `body`, `content_type`, and `code`.
     *
     * @throws \RuntimeException
     *
     * @see https://developer.wordpress.org/reference/classes/WP_HTTP/
     */
    private static function doHttpRequest ( $url, array $http_args = array() ) {
        if ( ! self::isUrlAllowed( $url ) ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'This address cannot be used as a data source. Only public http and https addresses are allowed.', 'inline-gdocs-viewer' )
            );
        }
        // WordPress gives up after 5 seconds by default, which is too short
        // for Apps Script web apps that are starting up. http_opts can set
        // another timeout (1 to 30 seconds).
        if ( ! isset( $http_args['timeout'] ) ) {
            $http_args['timeout'] = 15;
        }
        add_action( 'requests-requests.before_redirect', array( __CLASS__, 'validateRedirect' ) );
        $resp = wp_safe_remote_request( $url, $http_args );
        remove_action( 'requests-requests.before_redirect', array( __CLASS__, 'validateRedirect' ) );
        if ( is_wp_error( $resp ) ) {
            throw new \RuntimeException( __( 'Error requesting data:', 'inline-gdocs-viewer' ) . ' ' . $resp->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $resp );
        if ( $code < 200 || $code > 299 ) {
            throw new \RuntimeException( sprintf(
                /* translators: %d: HTTP status code. */
                __( 'Error requesting data: the data source returned HTTP status %d.', 'inline-gdocs-viewer' ),
                $code
            ), $code );
        }
        return array(
            'body'         => (string) wp_remote_retrieve_body( $resp ),
            'content_type' => strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $resp, 'content-type' ) )[0] ) ),
            'code'         => $code,
        );
    }

    /**
     * Parses CSV text into rows.
     *
     * @param string $csv_str
     *
     * @return array
     */
    public static function parseCsv ( $csv_str ) {
        $csv_str = (string) $csv_str;
        if ( 0 === strpos( $csv_str, "\xEF\xBB\xBF" ) ) {
            $csv_str = substr( $csv_str, 3 ); // Strip a UTF-8 byte order mark.
        }
        $temp = fopen( 'php://memory', 'r+' );
        fwrite( $temp, $csv_str );
        rewind( $temp );
        $r = array();
        while ( false !== ( $data = fgetcsv( $temp, 0, ',', '"', '' ) ) ) {
            if ( array( null ) === $data ) {
                continue; // Blank line.
            }
            $r[] = $data;
        }
        fclose( $temp );
        return $r;
    }

    /**
     * Converts rows back into CSV text.
     *
     * @param array $rows
     *
     * @return string
     */
    private static function rowsToCsv ( array $rows ) {
        $temp = fopen( 'php://memory', 'r+' );
        foreach ( $rows as $row ) {
            fputcsv( $temp, $row, ',', '"', '' );
        }
        rewind( $temp );
        $csv = stream_get_contents( $temp );
        fclose( $temp );
        return $csv;
    }

    /**
     * Whether the author of the current post may publish unfiltered HTML.
     *
     * Content that can carry scripts (web app HTML, DataTables data
     * options) is only trusted from such authors. Outside a post, nothing
     * is trusted.
     *
     * @return bool
     */
    private static function authorCanUseUnfilteredHtml () {
        $post = get_post();
        if ( ! $post || ! $post->post_author ) {
            return false;
        }
        return user_can( (int) $post->post_author, 'unfiltered_html' );
    }

    /**
     * Prints an appropriate HTML attribute string for any HTML5 Data
     * attributes that DataTables can use.
     *
     * @param array $atts Values passed from the shortcode.
     *
     * @return string Attribute-value pairs in HTML.
     */
    private function dataTablesAttributes ( $atts ) {
        // These options make DataTables load or render arbitrary data as HTML.
        $untrusted_blocked = array( 'datatables_ajax', 'datatables_data', 'datatables_server_side' );
        $trusted           = self::authorCanUseUnfilteredHtml();
        $str               = '';
        foreach ( $atts as $k => $v ) {
            if ( 0 !== strpos( $k, 'datatables_' ) || false === $v ) {
                continue;
            }
            if ( ! $trusted && in_array( $k, $untrusted_blocked, true ) ) {
                continue;
            }
            $k = str_replace( 'datatables', 'data', str_replace( '_', '-', $k ) );
            // We urldecode() the value here because WordPress shortcodes
            // use square brackets, but so do JavaScript arrays so users
            // are advised to sometimes enter URL-encoded equivalents.
            $str .= ' ' . esc_attr( $k ) . '="' . esc_attr( urldecode( (string) $v ) ) . '"';
        }
        return $str;
    }

    /**
     * Returns a valid language tag for the `lang` attribute.
     *
     * @param string $lang
     *
     * @return string
     */
    private static function sanitizeLang ( $lang ) {
        $lang = (string) $lang;
        if ( preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $lang ) ) {
            return $lang;
        }
        return get_bloginfo( 'language' );
    }

    /**
     * Converts a two-dimensional array representing rows and cells of data
     * into an HTML representation of that data, according to any additional
     * options passed to it.
     *
     * @param array $r Multidimensional array representing table data.
     * @param array $options Values passed from the shortcode.
     * @param string $caption Passed via shortcode, should be the table caption.
     * @return string An HTML string of the complete <table> element.
     * @see displayShortcode
     */
    private function dataToHtml ( $r, $options, $caption = '' ) {
        self::enqueueTableAssets();

        if ( (int) $options['strip'] > 0 ) {
            $r = array_slice( $r, (int) $options['strip'] ); // discard
        }

        // Split into table headers and body.
        $thead = ( (int) $options['header_rows'] ) ? array_splice( $r, 0, (int) $options['header_rows'] ) : array_splice( $r, 0, 1 );
        $tfoot = ( (int) $options['footer_rows'] ) ? array_splice( $r, -(int) $options['footer_rows'] ) : array();
        $tbody = $r;

        $ir = 1; // row number counter
        $ic = 1; // column number counter

        $id = ( 0 === $this->invocations )
            ? 'igsv-' . $this->getDocId( $options['key'] )
            : "igsv-{$this->invocations}-" . $this->getDocId( $options['key'] );
        $classes = trim( (string) $options['class'] );
        $html  = '<table id="' . esc_attr( $id ) . '"';
        $html .= ' class="' . esc_attr( trim( self::$dt_class . ' ' . $classes ) ) . '"';
        $html .= ' lang="' . esc_attr( self::sanitizeLang( $options['lang'] ) ) . '"';
        $html .= ( false === $options['summary'] ) ? '' : ' summary="' . esc_attr( $options['summary'] ) . '"';
        $html .= ( false === $options['title'] ) ? '' : ' title="' . esc_attr( $options['title'] ) . '"';
        $html .= ' style="' . esc_attr( (string) $options['style'] ) . '"';
        $html .= ( in_array( 'no-datatables', preg_split( '/\s+/', $classes ), true ) )
            ? ''
            : $this->dataTablesAttributes( $options );
        $html .= '>';

        if ( ! empty( $caption ) ) {
            $html .= '<caption>' . esc_html( $caption ) . '</caption>';
        }

        $html .= "<thead>\n";
        foreach ( $thead as $v ) {
            $html .= '<tr id="' . esc_attr( $id ) . '-row-' . esc_attr( $ir ) . '"';
            $html .= ' class="row-' . esc_attr( $ir ) . ' ' . esc_attr( $this->evenOrOdd( $ir ) ) . '">';
            $ir++;
            $ic = 1; // reset column counting
            foreach ( $v as $th ) {
                $th = nl2br( esc_html( $th ) );
                $html .= '<th class="col-' . esc_attr( $ic ) . ' ' . esc_attr( $this->evenOrOdd( $ic ) ) . '">';
                $html .= "<div>$th</div>";
                $html .= '</th>';
                $ic++;
            }
            $html .= "</tr>";
        }
        $html .= "</thead>";

        if ( $tfoot ) {
            $html .= "<tfoot>\n";
            foreach ( $tfoot as $v ) {
                $html .= '<tr id="' . esc_attr( $id ) . '-row-' . esc_attr( $ir ) . '"';
                $html .= ' class="row-' . esc_attr( $ir ) . ' ' . esc_attr( $this->evenOrOdd( $ir ) ) . '">';
                $ir++;
                $ic = 1; // reset column counting
                foreach ( $v as $td ) {
                    $td = nl2br( esc_html( $td ) );
                    $el = ( $ic <= (int) $options['header_cols'] ) ? 'th' : 'td';
                    $html .= "<$el class=\"col-$ic " . $this->evenOrOdd( $ic ) . "\">$td</$el>";
                    $ic++;
                }
                $html .= "</tr>";
            }
            $html .= '</tfoot>';
        }

        $html .= "<tbody>\n";
        foreach ( $tbody as $v ) {
            $html .= '<tr id="' . esc_attr( $id ) . '-row-' . esc_attr( $ir ) . '"';
            $html .= ' class="row-' . esc_attr( $ir ) . ' ' . esc_attr( $this->evenOrOdd( $ir ) ) . '">';
            $ir++;
            $ic = 1; // reset column counting
            foreach ( $v as $td ) {
                $td = nl2br( esc_html( $td ) );
                $el = ( $ic <= (int) $options['header_cols'] ) ? 'th' : 'td';
                $html .= "<$el class=\"col-$ic " . $this->evenOrOdd( $ic ) . "\">$td</$el>";
                $ic++;
            }
            $html .= "</tr>";
        }
        $html .= '</tbody>';

        $html .= '</table>';

        $html = apply_filters( self::shortcode . '_table_html', $html );

        if ( false === $options['linkify'] || 'no' === strtolower( (string) $options['linkify'] ) ) {
            return $html;
        } else {
            return self::setLinkTargets( make_clickable( $html ), $options['link_target'] );
        }
    }

    /**
     * Sets where links in the given HTML open.
     *
     * Links that already have a `target` are left alone. Links that open in a
     * new tab get `rel="noopener noreferrer"`, so the opened page cannot
     * control or see the page that opened it.
     *
     * @param string $html
     * @param string $target `_blank` (new tab) or `_self` (same tab).
     *
     * @return string
     */
    private static function setLinkTargets ( $html, $target ) {
        $target = ( '_self' === $target ) ? '_self' : '_blank';
        $tags   = new \WP_HTML_Tag_Processor( $html );
        while ( $tags->next_tag( 'a' ) ) {
            if ( null !== $tags->get_attribute( 'target' ) ) {
                continue;
            }
            $tags->set_attribute( 'target', $target );
            if ( '_blank' === $target ) {
                $rel = preg_split( '/\s+/', trim( (string) $tags->get_attribute( 'rel' ) ), -1, PREG_SPLIT_NO_EMPTY );
                $tags->set_attribute( 'rel', implode( ' ', array_unique( array_merge( $rel, array( 'noopener', 'noreferrer' ) ) ) ) );
            }
        }
        return $tags->get_updated_html();
    }

    /**
     * Prints either `odd` or `even`.
     *
     * @param int $x
     *
     * @return string
     */
    private function evenOrOdd ( $x ) {
        return ( (int) $x % 2 ) ? 'odd' : 'even'; // cast to integer just in case
    }

    /**
     * Handles oEmbed calls.
     *
     * @return string
     */
    public static function oEmbedHandler ( $matches, $attr, $url, $rawattr ) {
        $plugin = new self();
        return $plugin->displayShortcode( array( 'key' => $url ) );
    }

    /**
     * Serves the chart data source endpoint, if this request is for it.
     *
     * @see https://developer.wordpress.org/reference/hooks/init/
     */
    public static function maybeServeDatasource () {
        // This public endpoint is protected by the HMAC signature that
        // handleDatasourceRequest() checks, not by a nonce: it is called by
        // logged-out visitors' browsers, and must work from cached pages.
        if ( ! isset( $_GET[ self::datasource_param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $response = self::handleDatasourceRequest( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        status_header( $response['status'] );
        foreach ( $response['headers'] as $name => $value ) {
            header( "$name: $value" );
        }
        echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript response, built from json_encode().
        exit();
    }

    /**
     * Answers a Google Visualization data source request for a chart.
     *
     * Only requests signed by getDatasourceUrl() are served. The URL and the
     * query come from the signed definition, never from the request. The
     * response is always a `setResponse(...)` JavaScript call.
     *
     * @param array $params The request's query parameters.
     *
     * @return array With `status`, `headers`, and `body`.
     */
    public static function handleDatasourceRequest ( array $params ) {
        $tqx_in  = isset( $params['tqx'] ) ? (string) $params['tqx'] : '';
        $tqx     = array();
        $handler = 'google.visualization.Query.setResponse';
        foreach ( explode( ';', $tqx_in ) as $pair ) {
            $kv = explode( ':', $pair, 2 );
            if ( 2 !== count( $kv ) ) {
                continue;
            }
            if ( 'reqId' === $kv[0] && preg_match( '/^\d{1,9}$/', $kv[1] ) ) {
                $tqx['reqId'] = $kv[1];
            } elseif ( 'responseHandler' === $kv[0] && preg_match( '/^[A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*$/', $kv[1] ) ) {
                $handler = $kv[1];
            }
        }
        $headers = array(
            'Content-Type'           => 'application/javascript; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=60',
        );
        $error = function ( $status, $reason, $message ) use ( $tqx, $handler, $headers ) {
            $body = array(
                'version' => '0.6',
                'reqId'   => isset( $tqx['reqId'] ) ? $tqx['reqId'] : '0',
                'status'  => 'error',
                'errors'  => array( array( 'reason' => $reason, 'message' => $message ) ),
            );
            $json = preg_replace( '/([\{,])"([A-Za-z_][A-Za-z0-9_]*)"/', '$1$2', wp_json_encode( $body ) );
            return array( 'status' => $status, 'headers' => $headers, 'body' => "$handler($json);\n" );
        };

        $src = isset( $params['igsv_src'] ) ? (string) $params['igsv_src'] : '';
        $sig = isset( $params['igsv_sig'] ) ? (string) $params['igsv_sig'] : '';
        if ( '' === $src || '' === $sig || ! hash_equals( self::signDatasource( $src ), $sig ) ) {
            return $error( 403, 'access_denied', 'Invalid or missing signature.' );
        }
        $def = json_decode( (string) self::base64UrlDecode( $src ), true );
        if ( ! is_array( $def ) || ! isset( $def['k'], $def['q'] ) ) {
            return $error( 400, 'invalid_request', 'Invalid data source.' );
        }

        try {
            $plugin   = new self();
            $response = $plugin->fetchData( $def['k'], array(
                'http_opts' => false,
                'use_cache' => true,
                'expire_in' => 10 * MINUTE_IN_SECONDS,
            ) );
        } catch ( \Exception $e ) {
            return $error( 502, 'internal_error', $e->getMessage() );
        }

        $rows = self::typeCsvColumns( self::parseCsv( $response['body'] ), true );
        $out  = self::runQuery( $rows, $def['q'], $tqx + array( 'responseHandler' => $handler ) );
        return array( 'status' => 200, 'headers' => $headers, 'body' => $out );
    }

    /**
     * Runs a Google Visualization Query Language query on CSV rows with the
     * bundled query engine, without any HTTP request.
     *
     * @param array  $rows  Rows; the first holds column names, optionally typed.
     * @param string $query
     * @param array  $tqx   Engine parameters, such as `out`.
     *
     * @return string The engine's output.
     */
    private static function runQuery ( array $rows, $query, array $tqx ) {
        require_once __DIR__ . '/lib/vistable.php';
        $pairs = array();
        foreach ( $tqx as $k => $v ) {
            $pairs[] = "$k:$v";
        }
        $tz = wp_timezone_string();
        if ( ! in_array( $tz, timezone_identifiers_list(), true ) && ! preg_match( '/^[+-]\d{2}:\d{2}$/', $tz ) ) {
            $tz = 'UTC';
        }
        $vt = new csv_vistable( implode( ';', $pairs ), (string) $query, '', $tz, get_locale(), array() );
        $vt->send_headers = false;
        $vt->setup_rows( $rows );
        return (string) $vt->execute();
    }

    /**
     * Adds type hints (" as number", " as datetime") to the header row, so
     * that the query engine and charts don't treat every column as text.
     *
     * A column gets a type only if every sampled non-empty value has it.
     *
     * @param array $rows      CSV rows; the first is the header row.
     * @param bool  $datetimes Whether to detect date/time columns too.
     *
     * @return array
     */
    private static function typeCsvColumns ( array $rows, $datetimes ) {
        if ( empty( $rows ) ) {
            return $rows;
        }
        $head  = array_shift( $rows );
        $types = array();
        foreach ( array_slice( $rows, 0, 20 ) as $row ) {
            foreach ( $row as $k => $v ) {
                $v = trim( (string) $v );
                if ( '' === $v ) {
                    continue;
                }
                if ( preg_match( '/^-?[0-9]+(?:\.[0-9]*)?$/', $v ) ) {
                    $type = 'number';
                } elseif ( $datetimes && false !== strtotime( $v ) ) {
                    $type = 'datetime';
                } else {
                    $type = 'string';
                }
                if ( ! isset( $types[ $k ] ) ) {
                    $types[ $k ] = $type;
                } elseif ( $types[ $k ] !== $type ) {
                    $types[ $k ] = 'string';
                }
            }
        }
        foreach ( $head as $k => $v ) {
            if ( isset( $types[ $k ] ) && 'string' !== $types[ $k ] && ! preg_match( '/ as [a-z]+$/', (string) $v ) ) {
                $head[ $k ] = $v . ' as ' . $types[ $k ];
            }
        }
        array_unshift( $rows, $head );
        return $rows;
    }

    /**
     * WordPress Shortcode handler.
     *
     * @param array $atts
     * @param mixed $content
     *
     * @return string
     */
    public function displayShortcode ( $atts, $content = null ) {
        $raw_atts = is_array( $atts ) ? $atts : array();
        $atts = shortcode_atts( array(
            'key'      => false,                // Google Doc URL or ID
            'title'    => false,                // Title (attribute) text or visible chart title
            'class'    => '',                   // Container element's custom class value
            // TODO: Determine if `gid` attribute is still required by code.
            'gid'      => false,                // Sheet ID for a Google Spreadsheet, if only one
            'summary'  => false,                // If spreadsheet, value for summary attribute
            'width'    => '100%',
            'height'   => false,
            'style'    => false,
            'strip'    => 0,                    // If spreadsheet, how many rows to omit from top
            'csv_headers' => 0,                 // Whether to include headers in Google Sheet CSV
            'header_cols' => 0,                 // Number of columns to write as <th> elements
            'header_rows' => 1,                 // Number of rows in <thead>
            'footer_rows' => 0,                 // Number of rows in <tfoot>
            'use_cache' => true,                // Whether to use Transients API for fetched data.
            'http_opts' => false,               // Arguments to pass to the WordPress HTTP API.
            // TODO: Make a plugin option setting for default transient expiry time.
            'expire_in' => 10*MINUTE_IN_SECONDS,// Custom time-to-live of cached transient data.
            'lang'     => get_bloginfo('language'),
            'linkify'  => true,                 // Whether to run make_clickable() on parsed data.
            'link_target' => '_blank',          // Where links made by linkify open: _blank (new tab) or _self.
            'query'    => false,                // Google Visualization Query Language querystring
            'chart'    => false,                // Type of Chart (for an interactive chart)

            // Depending on the type of chart, the following options may be available.
            'chart_aggregation_target'         => false,
            'chart_all_values_suffix'          => false,
            'chart_allow_html'                 => false,
            'chart_allow_redraw'               => false,
            'chart_animation'                  => false,
            'chart_annotations'                => false,
            'chart_annotations_width'          => false,
            'chart_area_opacity'               => false,
            'chart_avoid_overlapping_grid_lines' => false,
            'chart_axis_titles_position'       => false,
            'chart_background_color'           => false,
            'chart_bars'                       => false,
            'chart_bubble'                     => false,
            'chart_candlestick'                => false,
            'chart_chart_area'                 => false,
            'chart_color_axis'                 => false,
            'chart_colors'                     => false,
            'chart_crosshair'                  => false,
            'chart_curve_type'                 => false,
            'chart_data_opacity'               => false,
            'chart_dataless_region_color'      => false,
            'chart_date_format'                => false,
            'chart_default_color'              => false,
            'chart_dimensions'                 => false,
            'chart_display_annotations'        => false,
            'chart_display_annotations_filter' => false,
            'chart_display_date_bar_separator' => false,
            'chart_display_exact_values'       => false,
            'chart_display_legend_dots'        => false,
            'chart_display_legend_values'      => false,
            'chart_display_mode'               => false,
            'chart_display_range_selector'     => false,
            'chart_display_zoom_buttons'       => false,
            'chart_domain'                     => false,
            'chart_enable_interactivity'       => false,
            'chart_enable_region_interactivity'=> false,
            'chart_explorer'                   => false,
            'chart_fill'                       => false,
            'chart_focus_target'               => false,
            'chart_font_name'                  => false,
            'chart_font_size'                  => false,
            'chart_force_i_frame'              => false,
            'chart_green_color'                => false,
            'chart_green_from'                 => false,
            'chart_green_to'                   => false,
            'chart_h_axes'                     => false,
            'chart_h_axis'                     => false,
            'chart_height'                     => false,
            'chart_highlight_dot'              => false,
            'chart_interpolate_nulls'          => false,
            'chart_is_stacked'                 => false,
            'chart_keep_aspect_ratio'          => false,
            'chart_legend'                     => false,
            'chart_line_width'                 => false,
            'chart_magnifying_glass'           => false,
            'chart_major_ticks'                => false,
            'chart_marker_opacity'             => false,
            'chart_max'                        => false,
            'chart_min'                        => false,
            'chart_minor_ticks'                => false,
            'chart_number_formats'             => false,
            'chart_orientation'                => false,
            'chart_pie_hole'                   => false,
            'chart_pie_residue_slice_color'    => false,
            'chart_pie_residue_slice_label'    => false,
            'chart_pie_slice_border_color'     => false,
            'chart_pie_slice_text'             => false,
            'chart_pie_slice_text_style'       => false,
            'chart_pie_start_angle'            => false,
            'chart_point_shape'                => false,
            'chart_point_size'                 => false,
            'chart_red_color'                  => false,
            'chart_red_from'                   => false,
            'chart_red_to'                     => false,
            'chart_region'                     => false,
            'chart_resolution'                 => false,
            'chart_reverse_categories'         => false,
            'chart_scale_columns'              => false,
            'chart_scale_format'               => false,
            'chart_scale_type'                 => false,
            'chart_selection_mode'             => false,
            'chart_series'                     => false,
            'chart_size_axis'                  => false,
            'chart_slice_visibility_threshold' => false,
            'chart_slices'                     => false,
            'chart_table'                      => false,
            'chart_theme'                      => false,
            'chart_thickness'                  => false,
            'chart_timeline'                   => false,
            'chart_title_position'             => false,
            'chart_title_text_style'           => false,
            'chart_tooltip'                    => false,
            'chart_trendlines'                 => false,
            'chart_v_axes'                     => false,
            'chart_v_axis'                     => false,
            'chart_width'                      => false,
            'chart_wmode'                      => false,
            'chart_yellow_color'               => false,
            'chart_yellow_from'                => false,
            'chart_yellow_to'                  => false,
            'chart_zoom_end_time'              => false,
            'chart_zoom_start_time'            => false,
            // For some reason this isn't parsing?
            //'chart_is3D'                       => false,

            // DataTables's HTML5 data- attributes.
            // DataTables Features
            // @see https://www.datatables.net/reference/option/#Features
            'datatables_auto_width'    => false,
            'datatables_buttons'       => false,
            'datatables_defer_render'  => false,
            'datatables_info'          => false,
            'datatables_j_query_UI'    => false,
            'datatables_length_change' => false,
            'datatables_ordering'      => false,
            'datatables_paging'        => false,
            'datatables_processing'    => false,
            'datatables_scroll_x'      => false,
            'datatables_scroll_y'      => false,
            'datatables_searching'     => false,
            'datatables_select'        => false,
            'datatables_server_side'   => false,
            'datatables_state_save'    => false,

            // DataTables Data
            // @see https://www.datatables.net/reference/option/#Data
            'datatables_ajax' => false,
            'datatables_data' => false,

            // DataTables Options
            // @see https://www.datatables.net/reference/option/#Options
            'datatables_defer_loading'   => false,
            'datatables_destroy'         => false,
            'datatables_display_start'   => false,
            'datatables_dom'             => false,
            'datatables_length_menu'     => false,
            'datatables_order_cells_top' => false,
            'datatables_order_classes'   => false,
            'datatables_order'           => false,
            'datatables_order_fixed'     => false,
            'datatables_order_multi'     => false,
            'datatables_page_length'     => false,
            'datatables_paging_type'     => false,
            'datatables_renderer'        => false,
            'datatables_retrieve'        => false,
            'datatables_scroll_collapse' => false,
            'datatables_search_cols'     => false,
            'datatables_search_delay'    => false,
            'datatables_search'          => false,
            'datatables_state_duration'  => false,
            'datatables_stripe_classes'  => false,
            'datatables_tab_index'       => false,

            // DataTables Columns
            // @see https://www.datatables.net/reference/option/#Columnes
            'datatables_column_defs' => false,
            'datatables_columns'     => false,
        ), $atts, self::shortcode );

        $atts['key'] = self::sanitizeKey( $atts['key'] );
        $atts['query'] = apply_filters( self::shortcode . '_query', self::sanitizeQuery( $atts['query'] ), $atts );

        try {
            switch ( self::getDocTypeByKey( $atts['key'] ) ) {
                case 'wpdb':
                case 'mysql':
                    $output = $this->getSqlOutput( $atts, $content, $raw_atts );
                    break;
                default:
                    $output = $this->getHttpOutput( $atts, $content );
                    break;
            }
        } catch ( \Exception $e ) {
            $output = '<p class="igsv-error">' . esc_html( $e->getMessage() ) . '</p>';
        }
        $this->invocations++;
        return $output;
    }

    /**
     * Returns the output of an HTTP datasource.
     *
     * @param array $x The shortcode attributes.
     * @param string $content The content of the shortcode.
     *
     * @return string The HTML output as requested by the shortcode or an error message.
     *
     * @throws \RuntimeException
     */
    private function getHttpOutput ( $x, $content ) {
        $key_type = self::getDocTypeByKey( $x['key'] );

        if ( ! empty( $x['chart'] ) ) {
            switch ( $key_type ) {
                case 'spreadsheet':
                    $url = $this->getSpreadsheetUrl( $x ); // Google is the data source.
                    break;
                case 'gasapp':
                    $url = $x['key']; // The web app is the data source.
                    break;
                default:
                    $url = self::getDatasourceUrl( $x['key'], $x['query'] );
                    break;
            }
            return $this->getGVizChartOutput( $url, $x, $content );
        }

        if ( 'docsviewer' === $key_type ) {
            // Addresses that don't end in .csv may still serve CSV, such as
            // a web service's export URL. Ask the server what it serves.
            if ( ! self::isCsvContentType( $this->detectContentType( $x['key'], $x ) ) ) {
                return $this->getGDocsViewerOutput( $x );
            }
            $key_type = 'csv';
        }

        $url = ( 'spreadsheet' === $key_type ) ? $this->getSpreadsheetUrl( $x ) : $x['key'];
        try {
            $http_response = $this->fetchData( $url, $x );
        } catch ( \RuntimeException $e ) {
            // Google answers 401, 403, or 404 for sheets that aren't shared
            // publicly (or don't exist); say what to do about it.
            if ( 'spreadsheet' === $key_type && in_array( $e->getCode(), array( 401, 403, 404 ), true ) ) {
                throw new \RuntimeException( self::sheetNotSharedMessage(), $e->getCode(), $e );
            }
            throw $e;
        }
        $is_csv = self::isCsvContentType( $http_response['content_type'] );

        if ( 'gasapp' === $key_type && ! $is_csv ) {
            $html = $http_response['body'];
            if ( ! self::authorCanUseUnfilteredHtml() ) {
                $html = wp_kses_post( $html );
            }
            return apply_filters( self::shortcode . '_webapp_html', $html, $x );
        }

        if ( 'spreadsheet' === $key_type && ! $is_csv ) {
            throw new \RuntimeException( self::sheetNotSharedMessage() );
        }

        return $this->csvToDataTable( $http_response['body'], $x, $content, 'csv' === $key_type ? $x['query'] : '' );
    }

    /**
     * The error shown when Google doesn't return a sheet's data.
     *
     * @return string
     */
    private static function sheetNotSharedMessage () {
        return __( 'Error:', 'inline-gdocs-viewer' ) . ' '
            . __( 'Google did not return spreadsheet data. Check that the spreadsheet exists and is shared with "Anyone with the link".', 'inline-gdocs-viewer' );
    }

    /**
     * Returns the output of a SQL datasource.
     *
     * @param array $atts The shortcode attributes.
     * @param string $content The content of the shortcode.
     * @param array $raw_atts The shortcode attributes as written in the post.
     *
     * @return string The HTML output as requested by the shortcode or an error message.
     *
     * @throws \RuntimeException
     */
    private function getSqlOutput ( $atts, $content, $raw_atts ) {
        if ( 'mysql' === self::getDocTypeByKey( $atts['key'] ) ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'Remote MySQL databases are no longer supported. Use key="wordpress" to query this site\'s database.', 'inline-gdocs-viewer' )
            );
        }
        if ( ! $this->isSqlDbEnabled() ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'SQL datasources are disabled.', 'inline-gdocs-viewer' )
            );
        }
        $raw_key   = isset( $raw_atts['key'] ) ? $raw_atts['key'] : '';
        $raw_query = isset( $raw_atts['query'] ) ? $raw_atts['query'] : '';
        if ( ! self::isSqlShortcodeAuthorized( get_post(), $raw_key, $raw_query ) ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'The author does not have permission to perform a SQL query.', 'inline-gdocs-viewer' )
            );
        }

        $query = trim( (string) $atts['query'] );
        if ( empty( $query ) ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'Missing query.', 'inline-gdocs-viewer' )
            );
        }
        if ( ! self::isSafeSelect( $query ) ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'Unsupported query:', 'inline-gdocs-viewer' )
                . ' ' . $query
            );
        }

        global $wpdb;
        $suppress = $wpdb->suppress_errors( true );
        // Run the query as a read-only transaction, so it cannot change data
        // even if it gets past isSafeSelect(). This applies to the next
        // transaction only and does not commit one that is already open.
        $read_only = false !== $wpdb->query( 'SET SESSION TRANSACTION READ ONLY' );
        $data = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Authorized SELECT written by a privileged user; see isSqlShortcodeAuthorized() and isSafeSelect().
        $failed = '' !== $wpdb->last_error;
        if ( $read_only ) {
            $wpdb->query( 'SET SESSION TRANSACTION READ WRITE' );
        }
        $wpdb->suppress_errors( $suppress );

        if ( $failed ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'The SQL query failed.', 'inline-gdocs-viewer' )
            );
        }
        if ( empty( $data ) ) {
            throw new \RuntimeException(
                __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                . __( 'Query produced zero results:', 'inline-gdocs-viewer' )
                . ' ' . $query
            );
        }
        $header = array( array_keys( $data[0] ) );
        $rows   = array();
        foreach ( $data as $row ) {
            $rows[] = array_values( $row );
        }
        return $this->dataToHtml( array_merge( $header, $rows ), $atts, $content );
    }

    /**
     * Whether a SQL query is a single, plain SELECT statement.
     *
     * Rejects comments, multiple statements, writing to files, reading
     * files, locking, and functions that can be used to stall the server.
     *
     * @param string $query
     *
     * @return bool
     */
    private static function isSafeSelect ( $query ) {
        // Look at the query with the contents of string literals removed.
        $bare = preg_replace( "/'(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.|\"\")*\"/s", "''", (string) $query );
        if ( null === $bare || ! preg_match( '/^\s*SELECT\b/i', $bare ) ) {
            return false;
        }
        if ( preg_match( '/;|--|#|\/\*/', $bare ) ) {
            return false;
        }
        $banned = '/\b(?:INTO|OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GET_LOCK|RELEASE_LOCK|IS_FREE_LOCK|IS_USED_LOCK|PROCEDURE|FOR\s+UPDATE|LOCK\s+IN|SHARE\s+MODE|UPDATE|DELETE|INSERT|DROP|ALTER|CREATE|GRANT|REVOKE|TRUNCATE|RENAME|HANDLER|CALL|EXECUTE|PREPARE|SET)\b/i';
        return ! preg_match( $banned, $bare );
    }

    /**
     * Returns the identifier of a SQL shortcode, as stored in post meta.
     *
     * @param string $key
     * @param string $query
     *
     * @return string
     */
    private static function sqlShortcodeHash ( $key, $query ) {
        return hash( 'sha256', strtolower( trim( (string) $key ) ) . "\n" . (string) $query );
    }

    /**
     * Lists the SQL shortcodes in some content.
     *
     * @param string $content
     *
     * @return string[] Hashes from sqlShortcodeHash().
     */
    private static function findSqlShortcodes ( $content ) {
        $hashes = array();
        if ( false === strpos( (string) $content, '[' . self::shortcode ) ) {
            return $hashes;
        }
        preg_match_all( '/' . get_shortcode_regex( array( self::shortcode ) ) . '/', (string) $content, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            if ( '[' === $m[1] && ']' === $m[6] ) {
                continue; // Escaped shortcode: [[gdoc]].
            }
            $atts = shortcode_parse_atts( $m[3] );
            if ( ! is_array( $atts ) || ! isset( $atts['key'] ) ) {
                continue;
            }
            if ( in_array( self::getDocTypeByKey( self::sanitizeKey( $atts['key'] ) ), array( 'wpdb', 'mysql' ), true ) ) {
                $hashes[] = self::sqlShortcodeHash( $atts['key'], isset( $atts['query'] ) ? $atts['query'] : '' );
            }
            if ( ! empty( $m[5] ) ) {
                $hashes = array_merge( $hashes, self::findSqlShortcodes( $m[5] ) );
            }
        }
        return array_values( array_unique( $hashes ) );
    }

    /**
     * Records which SQL shortcodes in a saved post may run.
     *
     * When a user with the `gdoc_query_sql_databases` capability saves a
     * post, all of its SQL shortcodes are authorized. When anyone else saves
     * it, only shortcodes that were already authorized, and are unchanged,
     * stay authorized.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     */
    public static function authorizeSqlShortcodes ( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof \WP_Post ) {
            return;
        }
        $found = self::findSqlShortcodes( $post->post_content );
        if ( current_user_can( self::prefix . 'query_sql_databases' ) ) {
            $allowed = $found;
        } else {
            $previous = get_post_meta( $post_id, self::sql_meta_key, true );
            $allowed  = array_values( array_intersect( is_array( $previous ) ? $previous : array(), $found ) );
        }
        if ( empty( $allowed ) ) {
            delete_post_meta( $post_id, self::sql_meta_key );
        } else {
            update_post_meta( $post_id, self::sql_meta_key, $allowed );
        }
    }

    /**
     * Whether a SQL shortcode in a post was saved by an authorized user.
     *
     * @param \WP_Post|null $post
     * @param string        $key   The `key` attribute as written.
     * @param string        $query The `query` attribute as written.
     *
     * @return bool
     */
    private static function isSqlShortcodeAuthorized ( $post, $key, $query ) {
        if ( ! $post instanceof \WP_Post ) {
            return false;
        }
        $allowed = get_post_meta( $post->ID, self::sql_meta_key, true );
        return is_array( $allowed ) && in_array( self::sqlShortcodeHash( $key, $query ), $allowed, true );
    }

    /**
     * Whether or not the "Allow SQL queries in shortcodes" option is enabled.
     *
     * @return bool
     */
    private function isSqlDbEnabled () {
        $options = get_option( self::prefix . 'settings' );
        return ! empty( $options['allow_sql_db_queries'] );
    }

    /**
     * WordPress mangles some HTML in subtle ways. Clean that up.
     *
     * @param string $key The value passed to the shortcode's `key` attribute.
     * @return string The "sanitized" key value.
     */
    private static function sanitizeKey ( $key ) {
        return str_replace( '&#038;', '&', trim( (string) $key ) );
    }

    /**
     * Gets the HTML representation of CSV data according to shortcode attributes.
     *
     * @param string $csv Data in CSV format.
     * @param array $x Attributes from the shortcode.
     * @param mixed $content Any contents of the shortcode if not self-closing.
     * @param string $query A query to run on the data first, if any.
     *
     * @return string
     */
    private function csvToDataTable ( $csv, $x, $content, $query = '' ) {
        $data = self::parseCsv( $csv );
        if ( '' !== (string) $query && $data ) {
            $out  = self::runQuery( self::typeCsvColumns( $data, false ), $query, array( 'out' => 'csv' ) );
            $data = self::parseCsv( $out );
            if ( ! $data ) {
                throw new \RuntimeException(
                    __( 'Error:', 'inline-gdocs-viewer' ) . ' '
                    . __( 'The query is invalid or returned no data:', 'inline-gdocs-viewer' )
                    . ' ' . $query
                );
            }
        }
        return $this->dataToHtml( $data, $x, $content );
    }

    /**
     * Prints HTML for the Google Document Viewer.
     *
     * @param array $x Attributes from the shortcode invocation.
     *
     * @return string
     */
    private function getGDocsViewerOutput ( $x ) {
        $src     = 'https://docs.google.com/viewer?url=' . rawurlencode( esc_url_raw( $x['key'] ) ) . '&embedded=true';
        $output  = '<iframe src="' . esc_url( $src ) . '"';
        $output .= ' width="' . esc_attr( $x['width'] ) . '"';
        $output .= ( false === $x['height'] ) ? '' : ' height="' . esc_attr( $x['height'] ) . '"';
        $output .= ( false === $x['style'] ) ? '' : ' style="' . esc_attr( $x['style'] ) . '"';
        $output .= ( false === $x['title'] ) ? '' : ' title="' . esc_attr( $x['title'] ) . '"';
        $output .= '>';
        $output .= esc_html__( 'Your Web browser must support inline frames to display this content:', 'inline-gdocs-viewer' );
        $output .= ' <a href="' . esc_url( $x['key'] ) . '">' . esc_html( false === $x['title'] ? $x['key'] : $x['title'] ) . '</a>';
        $output .= '</iframe>';
        return apply_filters( self::shortcode . '_viewer_html', $output );
    }

    /**
     * Returns a supported chart type for the `chart` attribute.
     *
     * @param string $chart
     *
     * @return string
     */
    private static function normalizeChartType ( $chart ) {
        $chart = strtolower( (string) $chart );
        // Google retired the Flash-based AnnotatedTimeLine chart.
        if ( 'annotatedtimeline' === $chart ) {
            return 'Annotation';
        }
        if ( 'steppedarea' === $chart ) {
            return 'Stepped';
        }
        foreach ( self::$chart_types as $type ) {
            if ( strtolower( $type ) === $chart ) {
                return $type;
            }
        }
        return 'Column';
    }

    /**
     * Prints HTML for turning into a Google Visualization.
     *
     * @param string $url
     * @param array $x Attributes from shortcode.
     * @param string $content The content of the shortcode.
     *
     * @return string
     */
    private function getGVizChartOutput ( $url, $x, $content ) {
        self::enqueueChartAssets();
        $type     = self::normalizeChartType( $x['chart'] );
        $chart_id = 'igsv-' . $this->invocations . '-' . $type . 'chart-' . $this->getDocId( $x['key'] );
        $output  = '<div id="' . esc_attr( $chart_id ) . '" class="igsv-chart" title="' . esc_attr( (string) $x['title'] ) . '"';
        $output .= ( empty( $x['style'] ) ) ? '' : ' style="' . esc_attr( $x['style'] ) . '"';
        $output .= ' data-chart-type="' . esc_attr( $type ) . '"';
        $output .= ' data-datasource-href="' . esc_url( $url ) . '"';
        foreach ( $this->getChartOptions( $x ) as $k => $v ) {
            if ( ! empty( $v ) ) {
                // Use `urldecode()` to handle JSON's array literal (square
                // bracket) syntax, then escape the decoded value.
                $output .= ' data-' . esc_attr( str_replace( '_', '-', $k ) ) . '="' . esc_attr( urldecode( (string) $v ) ) . '"';
            }
        }
        $output .= '>' . wp_kses_post( (string) $content ) . '</div>'; // .igsv-chart
        return $output;
    }

    /**
     * Retrieves the global plugin options.
     *
     * @return array An array of data suitable for passing to wp_localize_script().
     * @see https://developer.wordpress.org/reference/functions/wp_localize_script/
     */
    private static function getLocalizedPluginVars () {
        $options = get_option( self::prefix . 'settings', array() );
        $data = array(
            'lang_dir'  => plugins_url( 'languages', __FILE__ ),
            'languages' => self::getDataTablesLanguages(),
        );
        if ( empty( $options ) || ! is_array( $options ) ) {
            $data['datatables_classes'] = '.' . self::$dt_class . ':not(.no-datatables)';
        } else {
            $dt_classes = array();
            $classes    = isset( $options['datatables_classes'] ) ? (string) $options['datatables_classes'] : '';
            foreach ( preg_split( '/\s+/', trim( $classes ) ) as $cls ) {
                $cls = sanitize_html_class( $cls );
                $cls = ( empty( $cls ) ) ? self::$dt_class : $cls;
                $dt_classes[] = ".$cls:not(.no-datatables)";
            }
            $data['datatables_classes'] = implode( ', ', array_unique( $dt_classes ) );
            $data['datatables_defaults_object'] = isset( $options['datatables_defaults_object'] ) ? $options['datatables_defaults_object'] : null;
        }
        return $data;
    }

    /**
     * Lists the DataTables translations shipped in the languages directory.
     *
     * @return string[] Language tags, such as `nl-NL`.
     */
    private static function getDataTablesLanguages () {
        $langs = array();
        foreach ( (array) glob( __DIR__ . '/languages/datatables-*.json' ) as $file ) {
            $langs[] = substr( basename( $file, '.json' ), strlen( 'datatables-' ) );
        }
        return $langs;
    }

    /**
     * Gets the shortcode options related to charts.
     *
     * @param array $atts
     *
     * @return array
     */
    private function getChartOptions ( $atts ) {
        $opts = array();
        foreach ( $atts as $k => $v ) {
            if ( 0 === strpos( $k, 'chart_' ) ) {
                $opts[ $k ] = $v;
            }
        }
        return $opts;
    }

    /**
     * Adds on-screen help.
     *
     * @see https://developer.wordpress.org/reference/hooks/admin_head/
     */
    public static function registerContextualHelp () {
        $screen = get_current_screen();
        if ( ! $screen || empty( $screen->post_type ) ) { return; }
        $html = '<p>';
        $html .= sprintf(
            /* translators: 1: post type, 2: opening <kbd> tag, 3: closing </kbd> tag, 4: opening <var> tag, 5: closing </var> tag. */
            esc_html__( 'You can insert a Google Spreadsheet in this %1$s. To do so, type %2$s[gdoc key="%4$sYOUR_SPREADSHEET_URL%5$s"]%3$s wherever you would like the spreadsheet to appear. Remember to replace %4$sYOUR_SPREADSHEET_URL%5$s with the web address of your Google Spreadsheet.', 'inline-gdocs-viewer' ),
            esc_html( $screen->post_type ),
            '<kbd>', '</kbd>',
            '<var>', '</var>'
        );
        $html .= '</p>';
        $html .= '<p>';
        $html .= esc_html__( 'Only Google Spreadsheets that have been shared using either the "Public on the web" or "anyone with the link" options will be visible on this page.', 'inline-gdocs-viewer' );
        $html .= '</p>';
        $html .= '<p>' . sprintf(
            /* translators: 1: opening <kbd> tag, 2: closing </kbd> tag, 3: opening <var> tag, 4: closing </var> tag. */
            esc_html__( 'You can also transform your data into an interactive chart by using the %1$schart%2$s attribute. Supported chart types are Annotation, Area, Bar, Bubble, Candlestick, Column, Combo, Gauge, Geo, Histogram, Line, Pie, Scatter, Stepped, and Timeline. For instance, to make a Pie chart, type %1$s[gdoc key="%3$sYOUR_SPREADSHEET_URL%4$s" chart="Pie"]%2$s. Customize your chart with your own choice of colors by supplying a space-separated list of color values with the %1$schart_colors%2$s attribute, like %1$schart_colors="red green"%2$s. Additional options depend on the chart you use.', 'inline-gdocs-viewer' ),
            '<kbd>', '</kbd>',
            '<var>', '</var>'
        ) . '</p>';
        $html .= '<p>' . sprintf(
            /* translators: 1: opening link tag to the shortcode documentation, 2: opening link tag to the Google Chart documentation, 3: closing link tag. */
            esc_html__( 'Refer to the %1$sshortcode attribute documentation%3$s for a complete list of shortcode attributes, and the %2$sGoogle Chart API documentation%3$s for more information about each option.' ,'inline-gdocs-viewer' ),
            '<a href="https://github.com/e7andy/inline-gdocs-viewer/blob/master/docs/reference.md" target="_blank" rel="noopener noreferrer">',
            '<a href="https://developers.google.com/chart/interactive/docs/gallery" target="_blank" rel="noopener noreferrer">', '</a>'
        ) . '</p>';
        ob_start();
        self::showDonationAppeal();
        $html .= ob_get_clean();
        $screen->add_help_tab( array(
            'id' => self::shortcode . '-' . $screen->base . '-help',
            'title' => __( 'Inserting a Google Spreadsheet', 'inline-gdocs-viewer' ),
            'content' => $html
        ));
    }

    /**
     * Prints HTML asking for a donation for the plugin use.
     */
    private static function showDonationAppeal () {
?>
<div class="donation-appeal">
    <p style="text-align: center; font-style: italic; margin: 1em 3em;"><?php print sprintf(
/* translators: 1: link to make a donation, 2: link to the developer's page. */
esc_html__( 'Inline Google Spreadsheet Viewer is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'inline-gdocs-viewer' ),
'<a target="_blank" rel="noopener noreferrer" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Inline%20Google%20Spreadsheet%20Viewer%20WordPress%20Plugin&amp;item_number=inline-gdocs-viewer&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">' . esc_html__( 'making a donation', 'inline-gdocs-viewer' ) . '</a>',
'<a href="http://Cyberbusking.org/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'houseless, jobless, nomadic developer', 'inline-gdocs-viewer' ) . '</a>'
);?></p>
</div>
<?php
    }

    /**
     * Validates settings.
     *
     * @param array $input
     *
     * @return array
     */
    public static function validateSettings ( $input ) {
        $previous   = get_option( self::prefix . 'settings', array() );
        $previous   = is_array( $previous ) ? $previous : array();
        $safe_input = array();
        foreach ( (array) $input as $k => $v ) {
            switch ( $k ) {
                case 'allow_sql_db_queries':
                case 'load_assets_everywhere':
                    $safe_input[ $k ] = empty( $v ) ? 0 : 1;
                    break;
                case 'datatables_classes':
                    $classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', sanitize_text_field( (string) $v ) ) ) );
                    $safe_input[ $k ] = empty( $classes ) ? self::$dt_class : implode( ' ', $classes );
                    break;
                case 'datatables_defaults_object':
                    if ( '' === trim( (string) $v ) ) {
                        $safe_input[ $k ] = self::getDefaultDataTablesOptions();
                        break;
                    }
                    $decoded = json_decode( (string) $v, true );
                    if ( is_array( $decoded ) ) {
                        $safe_input[ $k ] = $decoded;
                    } else {
                        $safe_input[ $k ] = isset( $previous[ $k ] ) ? $previous[ $k ] : self::getDefaultDataTablesOptions();
                        if ( function_exists( 'add_settings_error' ) ) {
                            add_settings_error(
                                self::prefix . 'settings',
                                'invalid_json',
                                __( 'The DataTables defaults object is not valid JSON, so it was not changed.', 'inline-gdocs-viewer' )
                            );
                        }
                    }
                    break;
            }
        }
        return $safe_input;
    }

    /**
     * Prints the options screens.
     */
    public static function renderOptionsPage () {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'inline-gdocs-viewer' ) );
        }
        $options = get_option( self::prefix . 'settings' );
        $options = is_array( $options ) ? $options : array();
        $defaults = isset( $options['datatables_defaults_object'] ) ? $options['datatables_defaults_object'] : null;
        $datatables_defaults_json = empty( $defaults ) ? '' : wp_json_encode( $defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        $classes = isset( $options['datatables_classes'] ) ? $options['datatables_classes'] : '';
?>
<h2><?php esc_html_e( 'Inline Google Spreadsheet Viewer Settings', 'inline-gdocs-viewer' );?></h2>
<form method="post" action="options.php">
<?php settings_fields( self::prefix . 'settings' );?>
<fieldset><legend><?php esc_html_e( 'DataTables defaults', 'inline-gdocs-viewer' );?></legend>
<table class="form-table">
    <tbody>
        <tr>
            <th>
                <label for="<?php echo esc_attr( self::prefix );?>datatables_classes"><?php esc_html_e('DataTables classes', 'inline-gdocs-viewer');?></label>
            </th>
            <td>
                <input class="regular-text code"
                    id="<?php echo esc_attr( self::prefix );?>datatables_classes"
                    name="<?php echo esc_attr( self::prefix );?>settings[datatables_classes]"
                    value="<?php echo esc_attr( $classes );?>" placeholder="<?php esc_attr_e('class-1 class-2', 'inline-gdocs-viewer')?>"
                />
                <p class="description">
                    <?php print sprintf(
                        /* translators: 1: opening <code> tag, 2: closing </code> tag, 3: opening link tag to DataTables, 4: closing link tag. */
                        esc_html__('A space-separated list of HTML %1$sclass%2$s values. %1$s<table>%2$s elements with these classes will automatically be enhanced with %3$sjQuery DataTables%4$s, unless the given table also has the special %1$sno-datatables%2$s class. Leave blank to use the plugin default.', 'inline-gdocs-viewer'),
                        '<code>', '</code>',
                        '<a href="https://datatables.net/" target="_blank" rel="noopener noreferrer">', '</a>'
                    );?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php echo esc_attr( self::prefix );?>datatables_defaults_object"><?php esc_html_e('DataTables defaults object', 'inline-gdocs-viewer');?></label>
            </th>
            <td>
                <textarea class="large-text code"
                    id="<?php echo esc_attr( self::prefix );?>datatables_defaults_object"
                    name="<?php echo esc_attr( self::prefix );?>settings[datatables_defaults_object]"
                    placeholder='{ "searching": false, "ordering": false }'
                    style="min-height: 200px;"
                ><?php echo esc_textarea( $datatables_defaults_json ); ?></textarea>
                <p class="description"><?php print sprintf(
                    /* translators: 1: opening link tag to json.org, 2: closing link tag, 3: opening link tag to the DataTables manual, 4: opening link tag to the plugin documentation. */
                    esc_html__('Define a DataTables defaults initialization object (in %1$sJSON%2$s syntax). This is useful if you wish to change the default DataTables enhancements for all affected tables on your site at once. All DataTables-enhanced tables will use the DataTables options configured here unless explicitly overriden in the shortcode, HTML, or JavaScript initialization for the given table, itself. To learn more, read the %3$sDataTables manual section on Setting defaults%2$s and refer to the %4$sdocumentation for shortcode attributes available via this plugin%2$s. Leave blank to use the plugin default.', 'inline-gdocs-viewer'),
                    '<a href="https://www.json.org/" target="_blank" rel="noopener noreferrer">', '</a>',
                    '<a href="https://datatables.net/manual/options#Setting-defaults" target="_blank" rel="noopener noreferrer">',
                    '<a href="https://github.com/e7andy/inline-gdocs-viewer/blob/master/docs/reference.md" target="_blank" rel="noopener noreferrer">'
                );?></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php echo esc_attr( self::prefix );?>load_assets_everywhere"><?php esc_html_e( 'Load table scripts on every page?', 'inline-gdocs-viewer' );?></label>
            </th>
            <td>
                <input type="checkbox" <?php checked( ! empty( $options['load_assets_everywhere'] ) ); ?> value="1" id="<?php echo esc_attr( self::prefix );?>load_assets_everywhere" name="<?php echo esc_attr( self::prefix );?>settings[load_assets_everywhere]" />
                <label for="<?php echo esc_attr( self::prefix );?>load_assets_everywhere"><span class="description"><?php esc_html_e( 'By default, DataTables loads only on pages that show this plugin\'s shortcode. Turn this on if you write tables with the DataTables classes by hand, so they are enhanced on every page.', 'inline-gdocs-viewer' );?></span></label>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<fieldset><legend><?php esc_html_e('Advanced options', 'inline-gdocs-viewer');?></legend>
<table class="form-table">
    <tbody>
        <tr>
            <th>
                <label for="<?php echo esc_attr( self::prefix );?>allow_sql_db_queries"><?php esc_html_e('Allow SQL queries in shortcodes?', 'inline-gdocs-viewer');?></label>
            </th>
            <td>
                <input type="checkbox" <?php checked( ! empty( $options['allow_sql_db_queries'] ) ); ?> value="1" id="<?php echo esc_attr( self::prefix );?>allow_sql_db_queries" name="<?php echo esc_attr( self::prefix );?>settings[allow_sql_db_queries]" />
                <label for="<?php echo esc_attr( self::prefix );?>allow_sql_db_queries"><span class="description"><?php
        print sprintf(
            /* translators: 1: the shortcode name, 2: the capability name. */
            esc_html__('Enabling this option permits read-only SQL SELECT queries against this site\'s database to be inserted as part of a %1$s shortcode. This is useful but can also be easily abused, so it is disabled by default. Even once enabled, a query only runs if the post was last saved by a user with the %2$s capability. (Only Administrators have this capability by default.)', 'inline-gdocs-viewer'),
            esc_html( self::shortcode ),
            '<code>' . esc_html( self::prefix ) . 'query_sql_databases</code>'
        );
            ?></span><p class="description"><?php esc_html_e('User role(s) capable of using SQL queries:', 'inline-gdocs-viewer');?></p>
            <ul class="description">
            <?php foreach ( self::getSqlCapableRoles() as $k => $v ) {
                print '<li>' . esc_html( translate_user_role( $v['name'] ) ) . '</li>';
            }?>
            </ul></label>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<?php submit_button(); ?>
</form>
<?php
        self::showDonationAppeal();
    }
}

InlineGoogleSpreadsheetViewerPlugin::register();
