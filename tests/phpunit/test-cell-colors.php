<?php
/**
 * cell_colors="yes": background colors from a Google Sheet's embed view.
 */

use WP_IGSV\InlineGoogleSpreadsheetViewerPlugin as Plugin;

class Test_Cell_Colors extends IGSV_TestCase {

    const SHEET  = 'https://docs.google.com/spreadsheets/d/ABC/edit#gid=5';
    const EXPORT = 'https://docs.google.com/spreadsheets/d/ABC/export?format=csv&gid=5';
    const EMBED  = 'https://docs.google.com/spreadsheets/d/ABC/htmlembed/sheet?gid=5';
    const CSV    = "Team,Goals\nAliens,5\nNinjas,12\nPirates,7\n";

    /**
     * Builds embed HTML like Google's: style classes, a thead with column
     * ids, and rows that start with a row-number header.
     *
     * @param array $rows   Sheet row index => array of '<td ...>' strings.
     * @param array $styles CSS rules.
     * @param array $cols   Column ids in the header (null for a divider).
     */
    private function embed( array $rows, array $styles, array $cols = array( 0, 1 ) ) {
        $html  = '<html><head><style>.ritz .waffle a { color: inherit; }' . implode( '', $styles ) . '</style></head><body>';
        $html .= '<table class="waffle" cellspacing="0" cellpadding="0"><thead><tr><th class="row-header"></th>';
        foreach ( $cols as $c ) {
            $html .= null === $c ? '<th class="freezebar-cell"></th>' : '<th id="0C' . $c . '"></th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ( $rows as $row => $cells ) {
            $html .= is_int( $row )
                ? '<tr><th id="0R' . $row . '" class="row-headers-background"><div>' . ( $row + 1 ) . '</div></th>' . implode( '', $cells ) . '</tr>'
                : '<tr><th class="freezebar-cell"></th>' . implode( '', $cells ) . '</tr>';
        }
        return $html . '</tbody></table></body></html>';
    }

    private function styles() {
        return array(
            '.ritz .waffle .s0{background-color:#d9d9d9;text-align:left;}',
            '.ritz .waffle .s1{background-color:#ffffff;color:#000000;}',
            '.ritz .waffle .s2{text-align:right;background-color:#D9EAD3;}',
        );
    }

    /**
     * Returns each body cell's background color from rendered table HTML.
     */
    private function colors_of( $html, $section = 'tbody' ) {
        $out = array();
        foreach ( $this->xpath( $html )->query( "//table/$section/tr" ) as $tr ) {
            $row = array();
            foreach ( $tr->childNodes as $cell ) {
                if ( $cell instanceof DOMElement ) {
                    $row[] = preg_match( '/background-color:\s*([^;"]+)/', $cell->getAttribute( 'style' ), $m ) ? $m[1] : '';
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    public function test_cells_get_their_sheet_background_colors() {
        $this->mock_http( self::EXPORT, self::CSV );
        $this->mock_http( self::EMBED, $this->embed( array(
            0 => array( '<td class="s0">Team</td>', '<td class="s0">Goals</td>' ),
            1 => array( '<td class="s1">Aliens</td>', '<td class="s2">5</td>' ),
            2 => array( '<td class="s2">Ninjas</td>', '<td class="s1">12</td>' ),
            3 => array( '<td class="s1">Pirates</td>', '<td class="s1" style="background-color:#ff0000">7</td>' ),
        ), $this->styles() ), 'text/html' );

        $html = $this->render( '[gdoc key="' . self::SHEET . '" cell_colors="yes"]' );
        $this->assertSame( array( array( '#d9d9d9', '#d9d9d9' ) ), $this->colors_of( $html, 'thead' ) );
        $this->assertSame( array(
            array( '', '#d9ead3' ),
            array( '#d9ead3', '' ),
            array( '', '#ff0000' ),
        ), $this->colors_of( $html ), 'White is left out; inline styles win.' );
    }

    public function test_colors_are_off_by_default_and_cost_no_request() {
        $this->mock_http( self::EXPORT, self::CSV );
        $html = $this->render( '[gdoc key="' . self::SHEET . '"]' );
        $this->assertStringNotContainsString( 'background-color', $html );
        $this->assertCount( 1, $this->http_requests );
    }

    public function test_colors_follow_strip_header_and_footer_rows() {
        $this->mock_http( self::EXPORT, "Title\nTeam,Goals\nAliens,5\nNinjas,12\nTotal,17\n" );
        $this->mock_http( self::EMBED, $this->embed( array(
            0 => array( '<td class="s1">Title</td>', '<td class="s1"></td>' ),
            1 => array( '<td class="s0">Team</td>', '<td class="s0">Goals</td>' ),
            2 => array( '<td class="s2">Aliens</td>', '<td class="s1">5</td>' ),
            3 => array( '<td class="s1">Ninjas</td>', '<td class="s1">12</td>' ),
            4 => array( '<td class="s0">Total</td>', '<td class="s2">17</td>' ),
        ), $this->styles() ), 'text/html' );

        $html = $this->render( '[gdoc key="' . self::SHEET . '" cell_colors="yes" strip="1" footer_rows="1"]' );
        $this->assertSame( array( array( '#d9d9d9', '#d9d9d9' ) ), $this->colors_of( $html, 'thead' ) );
        $this->assertSame( array( array( '#d9ead3', '' ), array( '', '' ) ), $this->colors_of( $html ) );
        $this->assertSame( array( array( '#d9d9d9', '#d9ead3' ) ), $this->colors_of( $html, 'tfoot' ) );
    }

    public function test_hidden_columns_and_frozen_dividers_do_not_shift_colors() {
        // Column B (index 1) is hidden, so the header jumps from 0C0 to 0C2.
        // A frozen column adds a divider cell; a frozen row adds a divider row.
        $html = $this->embed( array(
            0     => array( '<td class="s0">A1</td>', '<td class="freezebar-cell"></td>', '<td class="s2">C1</td>' ),
            'bar' => array( '<td class="freezebar-cell"></td>', '<td class="freezebar-cell"></td>', '<td class="freezebar-cell"></td>' ),
            1     => array( '<td class="s2">A2</td>', '<td class="freezebar-cell"></td>', '<td class="s0">C2</td>' ),
        ), $this->styles(), array( 0, null, 2 ) );

        $this->assertSame( array(
            0 => array( 0 => '#d9d9d9', 2 => '#d9ead3' ),
            1 => array( 0 => '#d9ead3', 2 => '#d9d9d9' ),
        ), Plugin::parseSheetCellColors( $html ) );
    }

    public function test_merged_cells_color_every_cell_they_cover() {
        $html = $this->embed( array(
            0 => array( '<td class="s0" colspan="2" rowspan="2">Merged</td>', '<td class="s2">C1</td>' ),
            1 => array( '<td class="s1">C2</td>' ),
            2 => array( '<td class="s2">A3</td>', '<td class="s1">B3</td>', '<td class="s0">C3</td>' ),
        ), $this->styles(), array( 0, 1, 2 ) );

        $this->assertSame( array(
            0 => array( 0 => '#d9d9d9', 1 => '#d9d9d9', 2 => '#d9ead3' ),
            1 => array( 0 => '#d9d9d9', 1 => '#d9d9d9' ),
            2 => array( 0 => '#d9ead3', 2 => '#d9d9d9' ),
        ), Plugin::parseSheetCellColors( $html ) );
    }

    /**
     * @dataProvider provide_unsafe_colors
     */
    public function test_unsafe_color_values_are_ignored( $value ) {
        $html = $this->embed( array( 0 => array( '<td class="s9">x</td>', '<td class="s1">y</td>' ) ),
            array( '.ritz .waffle .s9{background-color:' . $value . ';}' ) );
        $this->assertSame( array(), Plugin::parseSheetCellColors( $html ) );
    }

    public function provide_unsafe_colors() {
        return array(
            'url'        => array( 'url(https://evil.example/x.png)' ),
            'expression' => array( 'expression(alert(1))' ),
            'breakout'   => array( '#fff" onmouseover="alert(1)' ),
            'named'      => array( 'red' ),
            'bad hex'    => array( '#12345' ),
            'white'      => array( '#FFFFFF' ),
        );
    }

    public function test_rgb_colors_are_accepted() {
        $html = $this->embed( array( 0 => array( '<td class="s9">x</td>', '<td class="s1">y</td>' ) ),
            array( '.ritz .waffle .s9{background-color:rgb(217, 234, 211);}' ) );
        $this->assertSame( array( 0 => array( 0 => 'rgb(217, 234, 211)' ) ), Plugin::parseSheetCellColors( $html ) );
    }

    public function test_without_gid_the_first_tab_is_found_from_the_tab_list() {
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/export?format=csv', self::CSV );
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/htmlembed', '<a href="#gid=99">First</a><a href="#gid=5">Second</a> ...gid=99... ?gid=99&single=true', 'text/html' );
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/htmlembed/sheet?gid=99', $this->embed( array(
            1 => array( '<td class="s2">Aliens</td>', '<td class="s1">5</td>' ),
        ), $this->styles() ), 'text/html' );

        $html = $this->render( '[gdoc key="ABC" cell_colors="yes"]' );
        $this->assertSame( '#d9ead3', $this->colors_of( $html )[0][0] );
    }

    public function test_query_turns_colors_off() {
        $gviz = 'https://docs.google.com/spreadsheets/d/ABC/gviz/tq?tqx=out:csv&tq=select%20A&headers=0&gid=5';
        $this->mock_http( $gviz, "Team\nAliens\n" );
        $html = $this->render( '[gdoc key="' . self::SHEET . '" cell_colors="yes" query="select A"]' );
        $this->assertStringNotContainsString( 'background-color', $html );
        foreach ( $this->http_requests as $request ) {
            $this->assertStringNotContainsString( 'htmlembed', $request['url'] );
        }
    }

    public function test_failed_or_changed_embed_still_shows_the_table() {
        $this->mock_http( self::EXPORT, self::CSV );
        $this->mock_http( self::EMBED, 'Server error', 'text/html', 500 );
        $html = $this->render( '[gdoc key="' . self::SHEET . '" cell_colors="yes"]' );
        $this->assertSame( 3, $this->xpath( $html )->query( '//tbody/tr' )->length );
        $this->assertStringNotContainsString( 'igsv-error', $html );

        $this->mock_http( self::EMBED, '<html><body><div>A new layout</div></body></html>', 'text/html' );
        $html = do_shortcode( '[gdoc key="' . self::SHEET . '" cell_colors="yes" use_cache="no"]' );
        $this->assertSame( 3, $this->xpath( $html )->query( '//tbody/tr' )->length );
    }

    public function test_colors_are_not_used_for_other_sources() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv" cell_colors="yes"]' );
        $this->assertCount( 1, $this->http_requests );
    }

    public function test_embed_requests_ignore_the_authors_http_opts() {
        $this->mock_http( self::EXPORT, self::CSV );
        $this->mock_http( self::EMBED, $this->embed( array(), $this->styles() ), 'text/html' );
        $this->render( '[gdoc key="' . self::SHEET . '" cell_colors="yes" http_opts=\'{"method":"POST","headers":{"X-Secret":"1"}}\']' );
        $embed = array_values( array_filter( $this->http_requests, function ( $r ) {
            return false !== strpos( $r['url'], 'htmlembed' );
        } ) )[0];
        $this->assertSame( 'GET', $embed['args']['method'] );
        $this->assertArrayNotHasKey( 'X-Secret', (array) $embed['args']['headers'] );
    }
}
