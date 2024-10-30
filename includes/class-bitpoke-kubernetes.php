<?php
/**
 * Main plugin class file.
 *
 * @package Bitpoke Kubernetes/Includes
 */

namespace Bitpoke\Kubernetes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bitpoke Kubernetes main plugin class.
 *
 * @package Bitpoke Kubernetes/Includes
 */
class Bitpoke_Kubernetes {
	/**
	 * The Kubernetes directory where the pod's service account secret is mounted.
	 * https://kubernetes.io/docs/tasks/run-application/access-api-from-pod/
	 */
	const KUBERNETES_SERVICE_ACCOUNT_DIR = '/var/run/secrets/kubernetes.io/serviceaccount';

	/**
	 * The directory where the pod info data is mounted. This data is not available by default, and must be mounted
	 * using kubernetes downward API.
	 * https://kubernetes.io/docs/concepts/workloads/pods/downward-api/
	 */
	const KUBERNETES_PODINFO_DIR = '/etc/podinfo';

	/**
	 * The single instance of Bitpoke_Kubernetes.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * Get a file path within the kubernetes service secret dir
	 *
	 * @param string $file File within service account dir.
	 */
	public static function kubernetes_service_account_path( $file = '' ) {
		$dir = apply_filters( 'kubernetes_service_account_dir', self::KUBERNETES_SERVICE_ACCOUNT_DIR );
		return path_join( $dir, $file );
	}

	/**
	 * Get a file path within the pod info dir
	 *
	 * @param string $file File within pod info dir.
	 */
	public static function podinfo_path( $file = '' ) {
		$dir = apply_filters( 'kubernetes_podinfo_dir', self::KUBERNETES_PODINFO_DIR );
		return path_join( $dir, $file );
	}

	/**
	 * Get Kubernetes endpoint for a request
	 *
	 * @param string $resource Kubernetes resource endpoint.
	 */
	public static function endpoint( $resource = '' ) {
		$kubernetes_service_host = getenv( 'KUBERNETES_SERVICE_HOST', false );
		$kubernetes_service_port = getenv( 'KUBERNETES_SERVICE_PORT', false );

		$ep = "https://$kubernetes_service_host";
		if ( ! empty( $kubernetes_service_port ) && '443' !== $kubernetes_service_port ) {
			$ep .= ":$kubernetes_service_port";
		}

		return $ep . ( empty( $resource ) ? '' : '/' . ltrim( $resource, '/' ) );
	}

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'bitpoke_kubernetes';

		// Load plugin environment variables.
		$this->file = $file;
		$this->dir  = dirname( $this->file );

		register_activation_hook( $this->file, array( $this, 'install' ) );

		add_filter( 'http_request_args', array( $this, 'kubernetes_http_client_args' ), 10, 2 );

		// Load API for generic admin functions.
		if ( is_admin() ) {
			new Kubernetes_Site_Health();
		}

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	}

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_localisation() {
		load_plugin_textdomain( 'bitpoke-kubernetes', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'bitpoke-kubernetes';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	/**
	 * Filter for http_client_args, for injecting Kubernetes in-cluster credentials
	 *
	 * @access  public
	 * @param   array  $args Existing wp_remote_request args.
	 * @param   string $url The url to filter the args for.
	 * @return  array The filtered args.
	 * @since   1.0.0
	 */
	public function kubernetes_http_client_args( $args, $url ) {
		// bail out if this is not a kubernetes request.
		if ( ! str_starts_with( $url, self::endpoint() ) ) {
			return $args;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$args['headers']['authorization'] = 'Bearer ' . file_get_contents( self::kubernetes_service_account_path( 'token' ) );
		$args['sslcertificates']          = self::kubernetes_service_account_path( 'ca.crt' );

		return $args;
	}

	/**
	 * Main Bitpoke_Kubernetes Instance
	 *
	 * Ensures only one instance of Bitpoke_Kubernetes is loaded or can be loaded.
	 *
	 * @param string $file File instance.
	 * @param string $version Version parameter.
	 *
	 * @return Object Bitpoke_Kubernetes instance
	 * @see Bitpoke_Kubernetes()
	 * @since 1.0.0
	 * @static
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of Bitpoke_Kubernetes is forbidden' ) ), esc_attr( $this->_version ) );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of Bitpoke_Kubernetes is forbidden' ) ), esc_attr( $this->_version ) );
	}

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	}

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	}
}
