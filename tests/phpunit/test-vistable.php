<?php
/**
 * The bundled query engine in lib/ (Apache-2.0, Mark Williams), as modified for this fork.
 */

class Test_Vistable extends IGSV_TestCase {

    public function set_up() {
        parent::set_up();
        require_once dirname( __DIR__, 2 ) . '/lib/vistable.php';
    }

    private function run_query( $tq, $tqx, array $rows ) {
        $vt = new \WP_IGSV\csv_vistable( $tqx, $tq, '', 'UTC', 'en_US', array() );
        $vt->send_headers = false;
        $vt->setup_rows( $rows );
        return $vt;
    }

    public function test_execute_returns_output_without_sending_headers() {
        $vt  = $this->run_query( 'select A', 'out:csv', array( array( 'A', 'B' ), array( 'x', '1' ) ) );
        $out = $vt->execute();
        $this->assertStringContainsString( 'x', $out );
        $this->assertSame( 'text/plain; charset="UTF-8"', $vt->get_content_type() );
    }

    public function test_where_and_order() {
        $rows = array( array( 'Team', 'Goals as number' ), array( 'Aliens', '5' ), array( 'Ninjas', '12' ), array( 'Pirates', '7' ) );
        $out  = $this->run_query( 'select Team where Goals > 6 order by Goals desc', 'out:csv', $rows )->execute();
        $this->assertSame( "Team\nNinjas\nPirates\n", $out );
    }

    public function test_dates_format_without_deprecated_functions() {
        $rows = array( array( 'When as date' ), array( '2026-10-03' ) );
        $out  = $this->run_query( "select When format When 'EEE d MMMM yyyy, D, w, W, F'", 'out:csv', $rows )->execute();
        $this->assertSame( "When\n\"Sat 03 October 2026, 276, 40, 0, 1\"\n", $out );
    }

    public function test_time_formats() {
        $rows = array( array( 'At as datetime' ), array( '2026-10-03 00:05:09' ) );
        $out  = $this->run_query( "select At format At 'k K h H:mm:ss a'", 'out:csv', $rows )->execute();
        $this->assertSame( "At\n24 0 12 00:05:09 AM\n", $out );
    }

    public function test_invalid_timezone_falls_back_to_utc() {
        $vt = new \WP_IGSV\csv_vistable( 'out:csv', 'select A', '', 'Not/AZone', 'en_US', array() );
        $vt->send_headers = false;
        $vt->setup_rows( array( array( 'A' ), array( 'x' ) ) );
        $this->assertStringContainsString( 'x', $vt->execute() );
    }

    public function test_html_error_output_is_escaped() {
        $vt  = $this->run_query( "select <img src=x onerror=alert(1)>", 'out:html', array( array( 'A' ), array( 'x' ) ) );
        $out = $vt->execute();
        $this->assertStringNotContainsString( '<img', $out );
    }

    public function test_json_output_is_a_setresponse_call() {
        $out = $this->run_query( 'select A', 'reqId:3', array( array( 'A' ), array( 'x' ) ) )->execute();
        $this->assertStringStartsWith( 'google.visualization.Query.setResponse({', $out );
        $this->assertStringContainsString( 'reqId:"3"', $out );
    }
}
