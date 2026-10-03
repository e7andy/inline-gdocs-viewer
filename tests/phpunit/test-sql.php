<?php
/**
 * SQL data sources.
 */

class Test_Sql extends IGSV_TestCase {

    const SHORTCODE = '[gdoc key="wordpress" query="SELECT user_login AS Login FROM wp_users ORDER BY ID LIMIT 1"]';

    public function set_up() {
        parent::set_up();
        $options = get_option( 'gdoc_settings' );
        $options['allow_sql_db_queries'] = 1;
        update_option( 'gdoc_settings', $options );
    }

    private function table_prefix_shortcode() {
        global $wpdb;
        return str_replace( 'wp_users', $wpdb->users, self::SHORTCODE );
    }

    /**
     * Creates a post as the given user, so that the save hook runs as them.
     */
    private function save_post_as( $user_id, $content, $post_id = 0 ) {
        wp_set_current_user( $user_id );
        $data = array( 'post_content' => $content, 'post_status' => 'publish' );
        if ( $post_id ) {
            $data['ID'] = $post_id;
            $post_id    = wp_update_post( wp_slash( $data ) );
        } else {
            $data['post_author'] = $user_id;
            $post_id             = wp_insert_post( wp_slash( $data ) );
        }
        $GLOBALS['post'] = get_post( $post_id );
        return $GLOBALS['post'];
    }

    public function test_admin_saved_query_runs() {
        $admin = self::factory()->user->create( array( 'role' => 'administrator', 'user_login' => 'zzadmin' ) );
        $post  = $this->save_post_as( $admin, $this->table_prefix_shortcode() );
        $html  = do_shortcode( $post->post_content );
        $xp    = $this->xpath( $html );
        $this->assertSame( 'Login', trim( $xp->query( '//thead//th' )->item( 0 )->textContent ) );
        $this->assertSame( 1, $xp->query( '//tbody/tr' )->length );
    }

    public function test_disabled_setting_blocks_queries() {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $post  = $this->save_post_as( $admin, $this->table_prefix_shortcode() );
        update_option( 'gdoc_settings', array( 'datatables_classes' => 'igsv-table' ) );
        $this->assertStringContainsString( 'SQL datasources are disabled', do_shortcode( $post->post_content ) );
    }

    public function test_editor_cannot_add_query_to_admin_post() {
        $admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $editor = self::factory()->user->create( array( 'role' => 'editor' ) );
        $post   = $this->save_post_as( $admin, 'Hello' );

        global $wpdb;
        $evil = '[gdoc key="wordpress" query="SELECT user_pass FROM ' . $wpdb->users . '"]';
        $post = $this->save_post_as( $editor, $evil, $post->ID );

        $html = do_shortcode( $post->post_content );
        $this->assertStringNotContainsString( 'igsv-table', $html );
        $this->assertStringContainsString( 'permission', $html );
    }

    public function test_editor_changing_an_authorized_query_is_blocked() {
        global $wpdb;
        $admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $editor = self::factory()->user->create( array( 'role' => 'editor' ) );
        $post   = $this->save_post_as( $admin, $this->table_prefix_shortcode() );
        $this->assertStringContainsString( 'igsv-table', do_shortcode( $post->post_content ) );

        $changed = '[gdoc key="wordpress" query="SELECT user_pass FROM ' . $wpdb->users . '"]';
        $post    = $this->save_post_as( $editor, $this->table_prefix_shortcode() . $changed, $post->ID );

        $this->assertStringContainsString( 'igsv-table', do_shortcode( $this->table_prefix_shortcode() ), 'Unchanged query still works.' );
        $this->assertStringNotContainsString( 'igsv-table', do_shortcode( $changed ) );
    }

    public function test_unsaved_post_or_no_post_cannot_query() {
        $GLOBALS['post'] = null;
        $html = do_shortcode( $this->table_prefix_shortcode() );
        $this->assertStringNotContainsString( 'igsv-table', $html );
        $this->assertStringContainsString( 'permission', $html );
    }

    /**
     * @dataProvider provide_rejected_queries
     */
    public function test_dangerous_queries_are_rejected( $query ) {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $sc    = '[gdoc key="wordpress" query="' . $query . '"]';
        $post  = $this->save_post_as( $admin, $sc );
        $html  = do_shortcode( $sc );
        $this->assertStringNotContainsString( 'igsv-table', $html );
        $this->assertStringContainsString( 'Unsupported query', $html );
    }

    public function provide_rejected_queries() {
        return array(
            'delete'        => array( 'DELETE FROM wp_options' ),
            'into outfile'  => array( "SELECT 1 INTO OUTFILE '/tmp/x'" ),
            'into dumpfile' => array( "SELECT 1 INTO DUMPFILE '/tmp/x'" ),
            'load_file'     => array( "SELECT LOAD_FILE('/etc/passwd')" ),
            'sleep'         => array( 'SELECT SLEEP(10)' ),
            'benchmark'     => array( 'SELECT BENCHMARK(1000000,MD5(1))' ),
            'two statements'=> array( 'SELECT 1; DROP TABLE wp_options' ),
            'comment trick' => array( 'SELECT 1 /*!50000 INTO OUTFILE */' ),
            'lowercase'     => array( "select load_file('/etc/passwd')" ),
            'leading space' => array( '  UPDATE wp_options SET x=1' ),
        );
    }

    public function test_remote_mysql_is_no_longer_supported() {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $sc    = '[gdoc key="mysql://user:secret@db.example.com/inventory" query="SELECT 1"]';
        $this->save_post_as( $admin, $sc );
        $html = do_shortcode( $sc );
        $this->assertStringContainsString( 'Remote MySQL', $html );
        $this->assertStringNotContainsString( 'secret', $html );
    }

    public function test_sql_errors_are_not_printed() {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $sc    = '[gdoc key="wordpress" query="SELECT nope FROM no_such_table_igsv"]';
        $this->save_post_as( $admin, $sc );
        $html = do_shortcode( $sc );
        $this->assertStringNotContainsString( 'no_such_table_igsv', $html );
        $this->assertStringContainsString( 'Error', $html );
    }

    public function test_cell_values_are_escaped() {
        $admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $sc    = '[gdoc key="wordpress" query="SELECT CONCAT(CHAR(60), \'b\', CHAR(62), \'x\') AS v"]';
        $this->save_post_as( $admin, $sc );
        $html = do_shortcode( $sc );
        $this->assertStringContainsString( '&lt;b&gt;x', $html );
        $this->assertStringNotContainsString( '<b>x', $html );
    }
}
