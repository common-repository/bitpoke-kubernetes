<?php
/**
 * Plugin Name: Bitpoke Kubernetes
 * Version: 1.0.0
 * Plugin URI: https://www.bitpoke.io/wordpress/kubernetes
 * Description: Kubernetes integration for WordPress
 * Author: Bitpoke
 * Author URI: https://www.bitpoke.io
 * Requires at least: 5.9
 * Tested up to: 6.0
 *
 * Text Domain: bitpoke-kubernetes
 * Domain Path: /lang/
 *
 * @package Bitpoke Kubernetes
 * @author Bitpoke
 * @since 1.0.0
 */

namespace Bitpoke\Kubernetes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/class-bitpoke-kubernetes.php';
require_once 'includes/class-kubernetes-site-health.php';

/**
 * Returns the main instance of Kubernetes to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Bitpoke_Kubernetes
 */
function bitpoke_kubernetes() {
	$instance = Bitpoke_Kubernetes::instance( __FILE__, '1.0.0' );

	return $instance;
}

bitpoke_kubernetes();
