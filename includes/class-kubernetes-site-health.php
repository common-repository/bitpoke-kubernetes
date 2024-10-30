<?php
/**
 * Disaply Kubernetes debug data in the WordPress site health section
 *
 * @package Bitpoke Kubernetes/Includes
 */

namespace Bitpoke\Kubernetes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for providing kubernetes debug data.
 *
 * @package Bitpoke Kubernetes/Includes
 */
class Kubernetes_Site_Health {
	/**
	 * Constructor function
	 */
	public function __construct() {
		$this->add_actions();
	}

	/**
	 * Add required actions for displaying kubernetes debug infromation.
	 */
	public function add_actions() {
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/**
	 * Add kubernetes debug infromation to site health.
	 *
	 * @param array $info Debug information from debug_information hook.
	 *                    see: https://developer.wordpress.org/reference/hooks/debug_information/.
	 */
	public function debug_information( $info ) {
		if ( $this->is_kubernetes() ) {

			$version_info = $this->get_kubernetes_version();
			if ( ! empty( $version_info ) ) {
				$version = sprintf( '%s.%s (%s)', $version_info['major'], $version_info['minor'], $version_info['gitVersion'] );
			}
			$info['wp-server']['fields']['kubernetes'] = array(
				'label' => __( 'Kubernetes', 'bitpoke-kubernetes' ),
				'value' => ! empty( $version_info ) ? $version : __( 'Unknown' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'debug' => $version_info,
			);

			$info['kubernetes'] = array(
				'label'  => __( 'Kubernetes', 'bitpoke-kubernetes' ),
				'fields' => $this->kubernetes_debug_information(),
			);
		}

		return $info;
	}

	/**
	 * Kubernetes debug section from Site Health
	 */
	public function kubernetes_debug_information() {
		$info = array();

		$pod_name         = $this->pod_name();
		$info['pod_name'] = array(
			'label' => __( 'Pod Name', 'bitpoke-kubernetes' ),
			'value' => $pod_name ? $pod_name : __( 'Unknown' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'debug' => $pod_name ? $pod_name : 'unavailable',
		);

		$info['pod_namespace'] = array(
			'label' => __( 'Pod Namespace', 'bitpoke-kubernetes' ),
			'value' => $this->pod_namespace(),
			'debug' => $this->pod_namespace(),
		);

		return $info;
	}

	/**
	 * Check is the current site runs inside a kubernetes pod
	 */
	public function is_kubernetes() {
		$kubernetes_service_host = getenv( 'KUBERNETES_SERVICE_HOST', false );
		$kubernetes_service_port = getenv( 'KUBERNETES_SERVICE_PORT', false );

		return ! empty( $kubernetes_service_host ) && ! empty( $kubernetes_service_port );
	}

	/**
	 * Get Kubernetes server version
	 */
	public function get_kubernetes_version() {
		$response = wp_remote_get( Bitpoke_Kubernetes::endpoint( '/version' ) );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data;
	}

	/**
	 * Get the current pod namespace
	 */
	public function pod_namespace() {
		$namespace_file = Bitpoke_Kubernetes::podinfo_path( 'namespace' );
		if ( file_exists( $namespace_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return file_get_contents( $namespace_file );
		}

		$namespace_file = Bitpoke_Kubernetes::kubernetes_service_account_path( 'namespace' );
		if ( file_exists( $namespace_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return file_get_contents( $namespace_file );
		}

		return '';
	}

	/**
	 * Get the current pod name (from the podinfo dir)
	 */
	public function pod_name() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$pod_name = file_get_contents( Bitpoke_Kubernetes::podinfo_path( 'name' ) );
		if ( ! empty( $pod_name ) ) {
			return $pod_name;
		}

		$hostname = gethostname();
		$pod_name = empty( $hostname ) ? getenv( 'HOSTNAME', false ) : $hostname;
		$pod_name = explode( '.', $hostname, 2 )[0]; // return only the first part of an eventual FQDN.

		return $pod_name;
	}
}
