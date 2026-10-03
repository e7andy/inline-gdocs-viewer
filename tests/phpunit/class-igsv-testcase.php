<?php
/**
 * Base test case with helpers for faking HTTP responses and rendering shortcodes.
 */

use WP_IGSV\InlineGoogleSpreadsheetViewerPlugin as Plugin;

abstract class IGSV_TestCase extends WP_UnitTestCase {

    /**
     * Fake responses, keyed by URL.
     *
     * @var array
     */
    protected $http_mocks = array();

    /**
     * Every HTTP request made during the test, as array( 'url' => ..., 'args' => ... ).
     *
     * @var array
     */
    protected $http_requests = array();

    /**
     * Whether unmatched requests are allowed to reach the network.
     *
     * @var bool
     */
    protected $allow_real_http = false;

    const CSV = "Team,Goals\nAliens,5\nNinjas,12\nPirates,7\n<script>alert(1)</script>,3\n";

    public function set_up() {
        parent::set_up();
        $this->http_mocks      = array();
        $this->http_requests   = array();
        $this->allow_real_http = false;
        add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );
        Plugin::activate();
        $needed = new ReflectionProperty( Plugin::class, 'needed' );
        $needed->setAccessible( true );
        $needed->setValue( null, array( 'table' => false, 'chart' => false ) );
    }

    public function tear_down() {
        remove_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10 );
        $GLOBALS['post'] = null;
        parent::tear_down();
    }

    /**
     * Registers a fake response for a URL.
     *
     * @param string $url          Exact URL, or a URL prefix ending in `*`.
     * @param string $body         Response body.
     * @param string $content_type Content-Type header.
     * @param int    $code         HTTP status code.
     */
    protected function mock_http( $url, $body, $content_type = 'text/csv; charset=utf-8', $code = 200 ) {
        $this->http_mocks[ $url ] = array(
            'headers'  => array( 'content-type' => $content_type ),
            'body'     => $body,
            'response' => array( 'code' => $code, 'message' => get_status_header_desc( $code ) ),
            'cookies'  => array(),
            'filename' => null,
        );
    }

    public function filter_pre_http_request( $pre, $args, $url ) {
        $this->http_requests[] = array( 'url' => $url, 'args' => $args );
        foreach ( $this->http_mocks as $pattern => $response ) {
            $matches = ( '*' === substr( $pattern, -1 ) )
                ? 0 === strpos( $url, substr( $pattern, 0, -1 ) )
                : $url === $pattern;
            if ( $matches ) {
                return $response;
            }
        }
        if ( $this->allow_real_http ) {
            return $pre;
        }
        return new WP_Error( 'igsv_test_unmocked', "Unmocked HTTP request: $url" );
    }

    /**
     * Creates a post by a user with the given role and makes it the global post.
     *
     * @return WP_Post
     */
    protected function make_post_by( $role, $content = '' ) {
        $user_id = self::factory()->user->create( array( 'role' => $role ) );
        wp_set_current_user( $user_id );
        $post_id = self::factory()->post->create( array(
            'post_author'  => $user_id,
            'post_content' => $content,
            'post_status'  => 'publish',
        ) );
        $GLOBALS['post'] = get_post( $post_id );
        setup_postdata( $GLOBALS['post'] );
        return $GLOBALS['post'];
    }

    /**
     * Renders a shortcode string inside the given (or a fresh admin-authored) post.
     */
    protected function render( $shortcode, $post = null ) {
        if ( null === $post && empty( $GLOBALS['post'] ) ) {
            $this->make_post_by( 'administrator', $shortcode );
        }
        return do_shortcode( $shortcode );
    }

    /**
     * Parses HTML and returns a DOMXPath for it.
     */
    protected function xpath( $html ) {
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
        libxml_clear_errors();
        return new DOMXPath( $doc );
    }

    /**
     * Calls a private or protected static method on the plugin class.
     */
    protected function call_private( $method, ...$args ) {
        $ref = new ReflectionMethod( Plugin::class, $method );
        $ref->setAccessible( true );
        return $ref->isStatic() ? $ref->invokeArgs( null, $args ) : $ref->invokeArgs( new Plugin(), $args );
    }
}
