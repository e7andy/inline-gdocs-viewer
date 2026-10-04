<?php
/**
 * Output that depends on whether the post author may publish unfiltered HTML:
 * Apps Script web app HTML and DataTables options that inject data.
 */

class Test_Author_Trust extends IGSV_TestCase {

    const WEBAPP = 'https://script.google.com/macros/s/XYZ/exec';
    const HTML   = '<p class="x">Hello</p><script>alert(1)</script><img src="x" onerror="alert(2)">';

    public function set_up() {
        parent::set_up();
        $this->mock_http( self::WEBAPP, self::HTML, 'text/html; charset=utf-8' );
    }

    public function test_webapp_html_is_filtered_for_contributors() {
        $post = $this->make_post_by( 'contributor' );
        $html = $this->render( '[gdoc key="' . self::WEBAPP . '"]', $post );
        $this->assertStringContainsString( '<p class="x">Hello</p>', $html );
        $this->assertStringNotContainsString( '<script', $html );
        $this->assertStringNotContainsString( 'onerror', $html );
    }

    public function test_webapp_html_is_filtered_outside_a_post() {
        $html = do_shortcode( '[gdoc key="' . self::WEBAPP . '"]' );
        $this->assertStringNotContainsString( '<script', $html );
    }

    public function test_webapp_html_is_kept_for_trusted_authors() {
        if ( is_multisite() ) {
            $this->markTestSkipped( 'Administrators lack unfiltered_html on multisite.' );
        }
        $post = $this->make_post_by( 'administrator' );
        $html = $this->render( '[gdoc key="' . self::WEBAPP . '"]', $post );
        $this->assertStringContainsString( '<script>alert(1)</script>', $html );
    }

    public function test_bare_webapp_url_embed_is_filtered_for_contributors() {
        $this->make_post_by( 'contributor' );
        $html = \WP_IGSV\InlineGoogleSpreadsheetViewerPlugin::oEmbedHandler( array(), array(), self::WEBAPP, array() );
        $this->assertStringNotContainsString( '<script', $html );
    }

    public function test_webapp_filter_still_runs() {
        $post = $this->make_post_by( 'contributor' );
        add_filter( 'gdoc_webapp_html', function ( $html ) {
            return $html . '<!-- filtered -->';
        } );
        $this->assertStringContainsString( '<!-- filtered -->', $this->render( '[gdoc key="' . self::WEBAPP . '"]', $post ) );
    }

    public function test_webapp_csv_becomes_table() {
        $this->mock_http( self::WEBAPP, self::CSV, 'text/csv' );
        $this->assertStringContainsString( 'igsv-table', $this->render( '[gdoc key="' . self::WEBAPP . '"]' ) );
    }

    public function test_datatables_data_options_dropped_for_contributors() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $post  = $this->make_post_by( 'contributor' );
        $xp    = $this->xpath( $this->render( '[gdoc key="https://example.com/data.csv" datatables_data=\'%5B%5B"<img src=x onerror=alert(1)>"%5D%5D\' datatables_ajax="https://evil.example/x.json" datatables_page_length="15"]', $post ) );
        $table = $xp->query( '//table' )->item( 0 );
        $this->assertFalse( $table->hasAttribute( 'data-data' ) );
        $this->assertFalse( $table->hasAttribute( 'data-ajax' ) );
        $this->assertSame( '15', $table->getAttribute( 'data-page-length' ) );
    }

    /**
     * DataTables renders these options as HTML, so an author who can't post
     * unfiltered HTML must not be able to set them.
     *
     * @dataProvider provide_html_rendering_datatables_options
     */
    public function test_html_rendering_datatables_options_dropped_for_contributors( $attribute, $data_attr ) {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $post  = $this->make_post_by( 'contributor' );
        $value = '%5B{"title":"%3Cimg src=x onerror=alert(1)%3E"}%5D';
        $xp    = $this->xpath( $this->render( '[gdoc key="https://example.com/data.csv" ' . $attribute . "='" . $value . "']", $post ) );
        $table = $xp->query( '//table' )->item( 0 );
        $this->assertFalse( $table->hasAttribute( $data_attr ), "$data_attr should be dropped for a contributor." );
    }

    public function provide_html_rendering_datatables_options() {
        return array(
            'columns'     => array( 'datatables_columns', 'data-columns' ),
            'column_defs' => array( 'datatables_column_defs', 'data-column-defs' ),
            'buttons'     => array( 'datatables_buttons', 'data-buttons' ),
            'dom'         => array( 'datatables_dom', 'data-dom' ),
        );
    }

    public function test_html_rendering_datatables_options_kept_for_trusted_authors() {
        if ( is_multisite() ) {
            $this->markTestSkipped( 'Administrators lack unfiltered_html on multisite.' );
        }
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $post  = $this->make_post_by( 'administrator' );
        $xp    = $this->xpath( $this->render( '[gdoc key="https://example.com/data.csv" datatables_buttons=\'%5B"copy"%5D\']', $post ) );
        $table = $xp->query( '//table' )->item( 0 );
        $this->assertSame( '["copy"]', $table->getAttribute( 'data-buttons' ) );
    }

    public function test_safe_datatables_options_kept_for_contributors() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $post  = $this->make_post_by( 'contributor' );
        $xp    = $this->xpath( $this->render( '[gdoc key="https://example.com/data.csv" datatables_paging="false" datatables_page_length="25" datatables_order=\'%5B%5B1,"desc"%5D%5D\']', $post ) );
        $table = $xp->query( '//table' )->item( 0 );
        $this->assertSame( 'false', $table->getAttribute( 'data-paging' ) );
        $this->assertSame( '25', $table->getAttribute( 'data-page-length' ) );
        $this->assertSame( '[[1,"desc"]]', $table->getAttribute( 'data-order' ) );
    }

    public function test_datatables_data_options_kept_for_trusted_authors() {
        if ( is_multisite() ) {
            $this->markTestSkipped( 'Administrators lack unfiltered_html on multisite.' );
        }
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $post  = $this->make_post_by( 'administrator' );
        $xp    = $this->xpath( $this->render( '[gdoc key="https://example.com/data.csv" datatables_ajax="https://example.com/x.json" datatables_order=\'%5B%5B 1, "desc" %5D%5D\']', $post ) );
        $table = $xp->query( '//table' )->item( 0 );
        $this->assertSame( 'https://example.com/x.json', $table->getAttribute( 'data-ajax' ) );
        $this->assertSame( '[[ 1, "desc" ]]', $table->getAttribute( 'data-order' ) );
    }
}
