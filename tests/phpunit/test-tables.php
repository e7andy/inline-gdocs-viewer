<?php
/**
 * Data source types, table rendering, and the Google Docs Viewer.
 */

class Test_Tables extends IGSV_TestCase {

    /**
     * @dataProvider provide_doc_types
     */
    public function test_doc_type_detection( $key, $expected ) {
        $this->assertSame( $expected, $this->call_private( 'getDocTypeByKey', $key ) );
    }

    public function provide_doc_types() {
        return array(
            'sheet url'      => array( 'https://docs.google.com/spreadsheets/d/ABC/edit#gid=1', 'spreadsheet' ),
            'bare sheet id'  => array( 'ABCDEFG', 'spreadsheet' ),
            'apps script'    => array( 'https://script.google.com/macros/s/XYZ/exec', 'gasapp' ),
            'csv'            => array( 'https://example.com/data.csv', 'csv' ),
            'csv with query' => array( 'https://example.com/data.csv?x=1', 'csv' ),
            'wordpress db'   => array( 'wordpress', 'wpdb' ),
            'mysql url'      => array( 'mysql://u:p@db.example.com/x', 'mysql' ),
            'other document' => array( 'https://example.com/paper.pdf', 'docsviewer' ),
            'host only'      => array( 'https://example.com', 'docsviewer' ),
        );
    }

    public function test_doc_type_detection_tolerates_empty_key() {
        $this->assertSame( 'spreadsheet', $this->call_private( 'getDocTypeByKey', '' ) );
    }

    public function test_csv_renders_escaped_table() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $html = $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $xp   = $this->xpath( $html );

        $this->assertSame( 1, $xp->query( '//table[contains(@class,"igsv-table")]' )->length );
        $this->assertSame( 'Team', trim( $xp->query( '//thead//th' )->item( 0 )->textContent ) );
        $this->assertSame( 4, $xp->query( '//tbody/tr' )->length );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    public function test_csv_is_fetched_directly_not_through_own_site() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertCount( 1, $this->http_requests );
        $this->assertSame( 'https://example.com/data.csv', $this->http_requests[0]['url'] );
    }

    public function test_csv_query_runs_in_process() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $html = $this->render( '[gdoc key="https://example.com/data.csv" query="select A where B %3E 6"]' );
        $xp   = $this->xpath( $html );

        $teams = array();
        foreach ( $xp->query( '//tbody/tr/td[1]' ) as $td ) {
            $teams[] = trim( $td->textContent );
        }
        $this->assertSame( array( 'Ninjas', 'Pirates' ), $teams );
        $this->assertCount( 1, $this->http_requests, 'Only the CSV itself should be fetched.' );
    }

    public function test_table_layout_options() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $html = $this->render( '[gdoc key="https://example.com/data.csv" strip="1" header_rows="1" footer_rows="1" header_cols="1" class="no-datatables mine"]Caption <b>x</b>[/gdoc]' );
        $xp   = $this->xpath( $html );

        $this->assertSame( 'Aliens', trim( $xp->query( '//thead//th' )->item( 0 )->textContent ) );
        $this->assertSame( 1, $xp->query( '//tfoot/tr' )->length );
        $this->assertSame( 2, $xp->query( '//tbody/tr' )->length );
        $this->assertSame( 2, $xp->query( '//tbody/tr/th' )->length, 'First column cells are <th>.' );
        $this->assertSame( 'Caption <b>x</b>', $xp->query( '//caption' )->item( 0 )->textContent );
        $table = $xp->query( '//table' )->item( 0 );
        $this->assertSame( 'igsv-table no-datatables mine', $table->getAttribute( 'class' ) );
        $this->assertFalse( $table->hasAttribute( 'data-page-length' ) );
    }

    public function test_linkify() {
        $this->mock_http( 'https://example.com/links.csv', "Site\nhttps://wordpress.org\n" );
        $this->assertStringContainsString( '<a href="https://wordpress.org"', $this->render( '[gdoc key="https://example.com/links.csv"]' ) );
        $this->assertStringNotContainsString( '<a href=', do_shortcode( '[gdoc key="https://example.com/links.csv" linkify="no" use_cache="no"]' ) );
    }

    public function test_long_csv_lines_are_not_split() {
        $long = str_repeat( 'x', 10000 );
        $this->mock_http( 'https://example.com/long.csv', "A,B\n$long,2\n" );
        $xp = $this->xpath( $this->render( '[gdoc key="https://example.com/long.csv"]' ) );
        $this->assertSame( 1, $xp->query( '//tbody/tr' )->length );
        $this->assertSame( $long, $xp->query( '//tbody/tr/td' )->item( 0 )->textContent );
    }

    public function test_quoted_csv_fields() {
        $this->mock_http( 'https://example.com/q.csv', "A,B\n\"one, two\",\"say \"\"hi\"\"\"\n" );
        $xp = $this->xpath( $this->render( '[gdoc key="https://example.com/q.csv"]' ) );
        $this->assertSame( 'one, two', $xp->query( '//tbody//td' )->item( 0 )->textContent );
        $this->assertSame( 'say "hi"', $xp->query( '//tbody//td' )->item( 1 )->textContent );
    }

    /**
     * @dataProvider provide_spreadsheet_urls
     */
    public function test_spreadsheet_url( $atts, $expected ) {
        $atts = array_merge( array( 'query' => false, 'chart' => false, 'csv_headers' => 0, 'gid' => false ), $atts );
        $this->assertSame( $expected, $this->call_private( 'getSpreadsheetUrl', $atts ) );
    }

    public function provide_spreadsheet_urls() {
        return array(
            'edit url'      => array(
                array( 'key' => 'https://docs.google.com/spreadsheets/d/ABC/edit' ),
                'https://docs.google.com/spreadsheets/d/ABC/export?format=csv',
            ),
            'gid fragment'  => array(
                array( 'key' => 'https://docs.google.com/spreadsheets/d/ABC/edit#gid=123' ),
                'https://docs.google.com/spreadsheets/d/ABC/export?format=csv&gid=123',
            ),
            'bare id'       => array(
                array( 'key' => 'ABC' ),
                'https://docs.google.com/spreadsheets/d/ABC/export?format=csv',
            ),
            'query'         => array(
                array( 'key' => 'https://docs.google.com/spreadsheets/d/ABC/edit', 'query' => 'select A' ),
                'https://docs.google.com/spreadsheets/d/ABC/gviz/tq?tqx=out:csv&tq=select%20A&headers=0',
            ),
            'unsafe gid'    => array(
                array( 'key' => 'https://docs.google.com/spreadsheets/d/ABC/edit', 'gid' => '1&evil=2' ),
                'https://docs.google.com/spreadsheets/d/ABC/export?format=csv&gid=1',
            ),
            'forced https'  => array(
                array( 'key' => 'http://docs.google.com/spreadsheets/d/ABC/edit' ),
                'https://docs.google.com/spreadsheets/d/ABC/export?format=csv',
            ),
        );
    }

    public function test_spreadsheet_table() {
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/export?format=csv', self::CSV );
        $xp = $this->xpath( $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/ABC/edit"]' ) );
        $this->assertSame( 4, $xp->query( '//tbody/tr' )->length );
        $this->assertMatchesRegularExpression( '/^igsv-(\d+-)?ABC$/', $xp->query( '//table' )->item( 0 )->getAttribute( 'id' ) );
    }

    public function test_docs_viewer_iframe() {
        $html  = $this->render( '[gdoc key="https://example.com/paper.pdf" height="500" title="Paper"]' );
        $xp    = $this->xpath( $html );
        $frame = $xp->query( '//iframe' )->item( 0 );
        $this->assertNotNull( $frame );
        $this->assertSame( 'https://docs.google.com/viewer?url=https%3A%2F%2Fexample.com%2Fpaper.pdf&embedded=true', $frame->getAttribute( 'src' ) );
        $this->assertSame( '500', $frame->getAttribute( 'height' ) );
        $this->assertCount( 0, $this->http_requests, 'The viewer needs no server-side request.' );
    }

    public function test_lang_attribute_is_validated() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $xp = $this->xpath( $this->render( '[gdoc key="https://example.com/data.csv" lang="../../evil"]' ) );
        $this->assertSame( get_bloginfo( 'language' ), $xp->query( '//table' )->item( 0 )->getAttribute( 'lang' ) );

        $xp = $this->xpath( do_shortcode( '[gdoc key="https://example.com/data.csv" lang="nl-NL"]' ) );
        $this->assertSame( 'nl-NL', $xp->query( '//table' )->item( 0 )->getAttribute( 'lang' ) );
    }

    public function test_http_error_is_reported_not_rendered() {
        $this->mock_http( 'https://example.com/missing.csv', '<html><script>x()</script>Not found</html>', 'text/html', 404 );
        $html = $this->render( '[gdoc key="https://example.com/missing.csv"]' );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '404', $html );
    }

    public function test_oembed_handler_renders_sheet() {
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/export?format=csv', self::CSV );
        $this->make_post_by( 'administrator' );
        $html = \WP_IGSV\InlineGoogleSpreadsheetViewerPlugin::oEmbedHandler( array(), array(), 'https://docs.google.com/spreadsheets/d/ABC/edit', array() );
        $this->assertStringContainsString( 'igsv-table', $html );
    }
}
