<?php
/**
 * Smush installer (update/upgrade procedures): Installer class
 *
 * @package Smush\Core
 * @since 2.8.0
 *
 * @author Anton Vanyukov <anton@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

namespace Smush\Core;

use WP_Smush;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Installer for handling updates and upgrades of the plugin.
 *
 * @since 2.8.0
 */
class Installer {

	/**
	 * Triggered on Smush deactivation.
	 *
	 * @since 3.1.0
	 */
	public static function smush_deactivated() {
		WP_Smush::get_instance()->core()->mod->cdn->unschedule_cron();
	}

	/**
	 * Check if a existing install or new.
	 *
	 * @since 2.8.0  Moved to this class from wp-smush.php file.
	 */
	public static function smush_activated() {
		if ( ! defined( 'WP_SMUSH_ACTIVATING' ) ) {
			define( 'WP_SMUSH_ACTIVATING', true );
		}

		$version = get_site_option( WP_SMUSH_PREFIX . 'version' );

		// If the version is not saved or if the version is not same as the current version,.
		if ( ! $version || WP_SMUSH_VERSION !== $version ) {
			global $wpdb;
			// Check if there are any existing smush stats.
			$results = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT 1",
					'wp-smpro-smush-data'
				)
			); // db call ok; no-cache ok.

			if ( $results ) {
				update_site_option( 'wp-smush-install-type', 'existing' );
			} else {
				update_site_option( 'wp-smush-install-type', 'new' );
			}

			// Create directory smush table.
			self::directory_smush_table();

			// Store the plugin version in db.
			update_site_option( WP_SMUSH_PREFIX . 'version', WP_SMUSH_VERSION );
		}
	}

	/**
	 * Handle plugin upgrades.
	 *
	 * @since 2.8.0
	 */
	public static function upgrade_settings() {
		// Avoid to execute this over an over in same thread.
		if ( defined( 'WP_SMUSH_ACTIVATING' ) || ( defined( 'WP_SMUSH_UPGRADING' ) && WP_SMUSH_UPGRADING ) ) {
			return;
		}

		$version = get_site_option( WP_SMUSH_PREFIX . 'version' );

		if ( false === $version ) {
			self::smush_activated();
		}

		if ( false !== $version && WP_SMUSH_VERSION !== $version ) {

			if ( ! defined( 'WP_SMUSH_UPGRADING' ) ) {
				define( 'WP_SMUSH_UPGRADING', true );
			}

			if ( version_compare( $version, '3.6.2', '<' ) ) {
				self::upgrade_3_6_2();
			}

			if ( version_compare( $version, '3.7.0', '<' ) ) {
				self::upgrade_3_7_0();
			}

			if ( version_compare( $version, '3.7.2', '<' ) ) {
				// Add the flag to display the release highlights modal.
				add_site_option( WP_SMUSH_PREFIX . 'show_upgrade_modal', true );
				// And show the upgrade notice.
				delete_site_option( WP_SMUSH_PREFIX . 'hide_smush_welcome' );
			}

			// Create/upgrade directory smush table.
			self::directory_smush_table();

			// Store the latest plugin version in db.
			update_site_option( WP_SMUSH_PREFIX . 'version', WP_SMUSH_VERSION );
		}
	}

	/**
	 * Create or upgrade custom table for directory Smush.
	 *
	 * After creating or upgrading the custom table, update the path_hash
	 * column value and structure if upgrading from old version.
	 *
	 * @since 2.9.0
	 */
	public static function directory_smush_table() {
		// Create a class object, if doesn't exists.
		if ( ! is_object( WP_Smush::get_instance()->core()->mod->dir ) ) {
			WP_Smush::get_instance()->core()->mod->dir = new Modules\Dir();
		}

		// No need to continue on sub sites.
		if ( ! Modules\Dir::should_continue() ) {
			return;
		}

		// Create/upgrade directory smush table.
		WP_Smush::get_instance()->core()->mod->dir->create_table();
	}

	/**
	 * Upgrade to 3.6.2
	 *
	 * @since 3.6.2
	 * @deprecated
	 */
	private static function upgrade_3_6_2() {
		delete_site_option( WP_SMUSH_PREFIX . 'run_recheck' );
	}

	/**
	 * Upgrade to 3.7.0
	 *
	 * @since 3.7.0
	 */
	private static function upgrade_3_7_0() {
		// Fix the "None" animation in lazy-load options.
		$lazy = Settings::get_instance()->get_setting( WP_SMUSH_PREFIX . 'lazy_load' );

		if ( ! $lazy || ! isset( $lazy['animation'] ) || ! isset( $lazy['animation']['selected'] ) ) {
			return;
		}

		if ( '0' === $lazy['animation']['selected'] ) {
			$lazy['animation']['selected'] = 'none';
			Settings::get_instance()->set_setting( WP_SMUSH_PREFIX . 'lazy_load', $lazy );
		}
	}

}
