<?php
/**
 * Plugin Name: Unwanted Plugins Remover
 * Plugin URI: http://wordpress.org/extend/plugins/unwanted-plugins-remover/
 * Text Domain: unwanted-plugins-remover
 * Domain Path: /lang
 * Description: With this plugin you can remove unwanted plugins on a WordPress core upgrade process. Unwanted plugins are for example <a href="http://wordpress.org/extend/plugins/akismet/">Akismet</a> or <a href="http://wordpress.org/extend/plugins/hello-dolly/">Hello Dolly</a>. Both plugins are default values and will be removed on each core upgrade process. You can extend the list with a filter named <code>unwanted_plugins_list</code>.
 * Author: Dominik Schilling
 * Author URI: http://dominikschilling.de/
 * Version: 0.1
 * License: GPLv2 or later
 *
 */

/**
 * Unwanted_Plugins_Remover class.
 */
class Unwanted_Plugins_Remover {
	var $maintenance_file;
	var $unwanted_plugins;
	var $domain;

	/**
	 * __construct function.
	 * Gogogogo.
	 *
	 * @uses apply_filters, add_filter, add_action, ABSPATH
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->maintenance_file = ABSPATH . '.maintenance';
		$this->unwanted_plugins = apply_filters( 'unwanted_plugins_list', array( 'akismet/akismet.php', 'hello.php' ) );
		$this->domain = 'unwanted-plugins-remover';

		add_filter( 'update_feedback', array( $this, 'add_feedback' ) );
		add_action( 'init', array( $this, 'localize_plugin' ) );
	}

	/**
	 * Unwanted_Plugins_Remover function.
	 * Helper for PHP4.
	 *
	 * @access public
	 * @return void
	 */
	public function Unwanted_Plugins_Remover() {
		$this->__construct();
	}

	/**
	 * localize_plugin function.
	 *
	 * @uses load_plugin_textdomain, plugin_basename
	 * @access public
	 * @return void
	 */
	public function localize_plugin() {
		 load_plugin_textdomain( $this->domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	}

	/**
	 * add_feedback function.
	 * Extend the existing feedback with new messages.
	 *
	 * @access public
	 * @param string $feedback Current feedback message.
	 * @return string Old message and with new messages on success. HTML.
	 */
	public function add_feedback( $feedback ) {
		if ( ! $this->is_maintenance() )
			return $feedback;

		if ( ! $psc = $this->plugin_sanity_check() )
			return $feedback;
		else {
			$feedback .= '</p>' . $psc; // @see show_message()
			if ( ! $rp = $this->remove_plugins() )
				return $feedback;
			else
				$feedback .= $rp;
		}

		return $feedback;
	}

	/**
	 * is_maintenance function.
	 * Check if maintenance file exists.
	 *
	 * @access public
	 * @return bool True on success, otherwise false.
	 */
	private function is_maintenance() {
		if ( ! file_exists( $this->maintenance_file ) )
			return false;

		return true;
	}

	/**
	 * is_plugin_inactive function.
	 * Helper for WordPress version < 3.1
	 *
	 * @uses is_plugin_inactive, is_plugin_active
	 * @access private
	 * @param string $plugin Base plugin path from plugins directory.
	 * @return bool True if inactive. False if active.
	 */
	private function is_plugin_inactive( $plugin ) {
		if ( function_exists( 'is_plugin_inactive' ) )
			return is_plugin_inactive( $plugin );
		else
			return ! is_plugin_active( $plugin );
	}

	/**
	 * plugin_sanity_check function.
	 * Validate the plugins, existence and status (only inactive plugins are allowed).
	 *
	 * @uses get_plugin_data, esc_html, get_plugins, WP_PLUGIN_DIR
	 * @access private
	 * @return string|bool String with feedback or false if no valid plugins available.
	 */
	private function plugin_sanity_check() {
		foreach ( $this->unwanted_plugins as $i => $plugin ) {
			$plugin = trim( $plugin, '/' );
			if ( ! file_exists( WP_PLUGIN_DIR . "/$plugin" ) ) {
				// Plugin doesn't exists
				unset( $this->unwanted_plugins[$i] );
			} else if ( file_exists( WP_PLUGIN_DIR . "/$plugin" ) && is_dir( WP_PLUGIN_DIR . "/$plugin" ) ) {
				// $plugin is a dir, check if we have a valid plugin in it
				unset( $this->unwanted_plugins[$i] );
				$folder_plugins = array_keys( get_plugins( '/' . $plugin ) );
				foreach ( $folder_plugins as $folder_plugin )
					$this->unwanted_plugins[] = $plugin . '/' . $folder_plugin;
			}
		}

		$this->unwanted_plugins = array_filter( $this->unwanted_plugins, array( $this, 'is_plugin_inactive' ) );

		if ( empty( $this->unwanted_plugins ) )
			return false;

		$feedback = '';
		foreach ( $this->unwanted_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$feedback .= '<p>' . sprintf( __( 'Removing %s...', $this->domain ), '<span class="code">' . esc_html( $plugin_data['Name'] ) . '</span>' ) . '</p>';
		}

		return $feedback;
	}

	/**
	 * remove_plugins function.
	 * Remove the unwanted plugins.
	 *
	 * @uses delete_plugins, is_wp_error, esc_html, get_error_message
	 * @access private
	 * @return string Feedback on success and on failure.
	 */
	private function remove_plugins() {
		$delete_result = delete_plugins( $this->unwanted_plugins );
		$count = count( $this->unwanted_plugins );

		if ( is_wp_error( $delete_result ) )
			$feedback = '<p>' . sprintf( __( 'There is an error: %s', $this->domain ), esc_html( $delete_result->get_error_message() ) );
		else
			$feedback = '<p>' . sprintf( _n( 'Plugin removed successfully', '%s plugins removed successfully', $count, $this->domain  ), number_format_i18n( $count ) );

		return $feedback;
	}

}

$upr = new Unwanted_Plugins_Remover();
