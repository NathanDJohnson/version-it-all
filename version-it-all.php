<?php
/*
 * Plugin Name: Version it All
 * Description: Display the versions of the LAMP stack
 * Version: 0.1.0
 * Author: Nathan Johnson
 * Author URI: http://atmoz.org/
*/

if( ! DEFINED( 'ABSPATH' ) )
	exit;

class version_it_all {
	public function __construct(){
		// null, minimum, default, recommended, required
		$this->requirements = array( 
			'php' => array( 'minimum' => '5.2.4', 
				'default' => '5.6', 
				'recommended' => '7.0' ),
			'mysql' => array( 'required' => '5.0', 
				'recommended', '5.6'),
			'mariadb' => array( 'minimum' => '10.0',
				'recommended' => '10.1' ),
			'http' => array( 'minimum' => '1.0', 
				'recommended' => '2.0' ),
			'ssl' => array( 'recommended' => TRUE ),
			'tls' => array( 'minimum' => '1.0', 
				'recommeneded' => '1.2' ),
			'apache' => array( 'minimum' => '2.2', 
				'recommended' => '2.4' ),
			'nginx' => array( 'recommended' => '1.9.5' ),
			'os' => array( 'null' => NULL ),
		);
		
		$this->vulnerabilities = array(
			'apache' => array( '2.4.10',
				'2.4.12', '2.4.10', '2.4.9', '2.4.8',
				'2.4.7', '2.4.6', '2.4.5', '2.4.4',
				'2.4.3', '2.4.2', '2.4.1',
				'2.2.29', '2.2.27', '2.2.26', '2.2.25',
				'2.2.24', '2.2.23', '2.2.22', '2.2.21',
				'2.2.20', '2.2.19', '2.2.18', '2.2.17',
				'2.2.16', '2.2.15', '2.2.14', '2.2.13',
				'2.2.12', '2.2.11', '2.2.10', '2.2.9',
				'2.2.8', '2.2.6', '2.2.5', '2.2.4',
				'2.2.3', '2.2.2', '2.2.0',
			),
		);
		
		$this->stable = array();
		$this->eol = array();
		
		add_action( 'current_screen', array( $this, 'init' ), 0 );
		add_action( 'wp_dashboard_setup', array( $this, 'wp_dashboard_setup') );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}
	
	function admin_notice(){
		$class = 'notice notice-error is-dismissible';
		
		if( version_compare( $this->version->php->version, $this->requirements['php']['minimum'], '<' ) ){
			$message = __( "Irks! An error has occured. You are currently running PHP version {$this->version->php->version}.", 'version-it-all' );
			
			printf( '<div class ="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}
	
	private function _objectify_array( &$array ){
		if( ! is_array( (array)$array ) )
			return;
		return json_decode( json_encode( (array)$array, JSON_FORCE_OBJECT ) );
	}
	
	private function _trim( $string, $trim_at_char = '', $before_or_after = '' ){
		
		if( $trim_at_char =='' || ! $trim_position = stripos( (string)$string, (string)$trim_at_char ) ){ return trim( (string)$string ); }

		switch( strtolower( (string)$before_or_after ) ) {
			case 'before':
				return trim( substr( (string)$string, $trim_position + 1 ) );
				break;
			case 'after':
				return trim( substr( (string)$string, 0 , $trim_position ) );
				break;
			default:
				return trim( (string)$string );
				break;
		}
	}
	
	public function init() {
		$current_screen = get_current_screen();
		if( 'dashboard' != $current_screen->id ){ return; }

		$this->php();
		$this->sql();
		$this->http();
		$this->ssl();
		$this->server();
		$this->os();
		
		$this->version = $this->_objectify_array( $this->version );

	}
	
	public function wp_dashboard_setup() {
		wp_add_dashboard_widget( 
			'version_it_all_widget', 
			'Version it All', 
			array( $this, 'widget' ) 
		);
	}
	
	public static function widget() {
		$v = $this->version;
		?>
		<div>OS: <?php echo $v->os->software; ?></div>
		<div>PHP: <?php echo $v->php->version; ?></div>
		<div><?php echo $v->sql->software; ?>: <?php echo $v->sql->version; ?></div>
		<div><?php echo $v->server->software; ?>: <?php echo $v->server->version; ?></div>
		<div>HTTP: <?php echo $v->http->version; ?></div>
		<div>SSL: <?php echo ( $v->ssl->software ? 'Yes' : 'No' ); ?></div>
		<?php
	}
	
	private function php() {
		$this->version['php'] = array( 
			'version' => $this->_trim( phpversion(), '-', 'after' ) 
		);
	}
	
	private function sql() {
		global $wpdb;
		$sql_version_string = $wpdb->dbh->server_info;
		
		$sql_software = $this->sql_software( $sql_version_string );
		
		$sql_version = $this->_trim( $sql_version_string, '-', 'before' );
		$sql_version = $this->_trim( $sql_version, '-', 'after' );
		
		$this->version['sql'] = array( 
			'software' => $sql_software, 
			'version' => $sql_version 
		);
	}
	
	private function sql_software( $sql_version_string ) {
		$sql_software = 'Unknown';
		if( stripos( $sql_version_string, 'mariadb' ) !== FALSE ){
			$sql_software = 'MariaDB';
		}
		return $sql_software;
	}
	
	private function http() {
		$this->version['http'] = array( 
			'version' => $this->_trim( $_SERVER['SERVER_PROTOCOL'], '/', 'before' ) );
	}
	
	private function ssl() {
		$this->version['ssl'] = array( 'software' => ( is_ssl() ? TRUE : FALSE ) );
	}
	
	private function server() {
		$server = $_SERVER['SERVER_SOFTWARE'];
		$this->version['server'] = array( 
			'software' => $this->server_software( $server ),
			'version' => $this->server_version( $server ),
		 );
	}
	
	private function server_software( $server ) {
		return $this->_trim( (string)$server, '/', 'after' );
	}
	
	private function server_version( $server ) {
		$server_software = $this->_trim( (string)$server, '/', 'before' );
		return $this->_trim( $server_software, ' ', 'after' );
	}
	
	private function os() {
		$this->version['os'] = array( 'software' => $this->_trim( php_uname( 's' ) ) );
	}

}
$version = new version_it_all();
