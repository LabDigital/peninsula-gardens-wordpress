<?php
/**
 * Copyright (C) 2014-2020 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Ai1wmse_Main_Controller extends Ai1wmve_Main_Controller {

	/**
	 * Register plugin menus
	 *
	 * @return void
	 */
	public function admin_menu() {
		// Sub-level Settings menu
		add_submenu_page(
			'ai1wm_export',
			__( 'Amazon S3 Settings', AI1WMSE_PLUGIN_NAME ),
			__( 'Amazon S3 Settings', AI1WMSE_PLUGIN_NAME ),
			'export',
			'ai1wmse_settings',
			'Ai1wmse_Settings_Controller::index'
		);
	}

	/**
	 * Enqueue scripts and styles for Export Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_export_scripts_and_styles( $hook ) {
		if ( stripos( 'toplevel_page_ai1wm_export', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmse_export',
				Ai1wm_Template::asset_link( 'css/export.min.rtl.css', 'AI1WMSE' ),
				array( 'ai1wm_export' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmse_export',
				Ai1wm_Template::asset_link( 'css/export.min.css', 'AI1WMSE' ),
				array( 'ai1wm_export' )
			);
		}

		wp_enqueue_script(
			'ai1wmse_export',
			Ai1wm_Template::asset_link( 'javascript/export.min.js', 'AI1WMSE' ),
			array( 'ai1wm_export' )
		);

		wp_localize_script(
			'ai1wmse_export',
			'ai1wmse_dependencies',
			array( 'messages' => $this->get_missing_dependencies() )
		);
	}

	/**
	 * Enqueue scripts and styles for Import Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_import_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wm_import', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmse_import',
				Ai1wm_Template::asset_link( 'css/import.min.rtl.css', 'AI1WMSE' ),
				array( 'ai1wm_import' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmse_import',
				Ai1wm_Template::asset_link( 'css/import.min.css', 'AI1WMSE' ),
				array( 'ai1wm_import' )
			);
		}

		wp_enqueue_script(
			'ai1wmse_import',
			Ai1wm_Template::asset_link( 'javascript/import.min.js', 'AI1WMSE' ),
			array( 'ai1wm_import' )
		);

		wp_localize_script(
			'ai1wmse_import',
			'ai1wmse_import',
			array(
				'ajax' => array(
					'bucket_url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wmse_s3_bucket' ) ),
				),
			)
		);

		wp_localize_script(
			'ai1wmse_import',
			'ai1wmse_dependencies',
			array( 'messages' => $this->get_missing_dependencies() )
		);
	}

	/**
	 * Enqueue scripts and styles for Settings Controller
	 *
	 * @param  string $hook Hook suffix
	 * @return void
	 */
	public function enqueue_settings_scripts_and_styles( $hook ) {
		if ( stripos( 'all-in-one-wp-migration_page_ai1wmse_settings', $hook ) === false ) {
			return;
		}

		if ( is_rtl() ) {
			wp_enqueue_style(
				'ai1wmse_settings',
				Ai1wm_Template::asset_link( 'css/settings.min.rtl.css', 'AI1WMSE' ),
				array( 'ai1wm_servmask' )
			);
		} else {
			wp_enqueue_style(
				'ai1wmse_settings',
				Ai1wm_Template::asset_link( 'css/settings.min.css', 'AI1WMSE' ),
				array( 'ai1wm_servmask' )
			);
		}

		wp_enqueue_script(
			'ai1wmse_settings',
			Ai1wm_Template::asset_link( 'javascript/settings.min.js', 'AI1WMSE' ),
			array( 'ai1wm_settings' )
		);

		wp_localize_script(
			'ai1wmse_settings',
			'ai1wm_feedback',
			array(
				'ajax'       => array(
					'url' => wp_make_link_relative( admin_url( 'admin-ajax.php?action=ai1wm_feedback' ) ),
				),
				'secret_key' => get_option( AI1WM_SECRET_KEY ),
			)
		);

		wp_localize_script(
			'ai1wmse_settings',
			'ai1wmse_dependencies',
			array( 'messages' => $this->get_missing_dependencies() )
		);
	}

	/**
	 * Register listeners for actions
	 *
	 * @return void
	 */
	protected function activate_actions() {
		add_action( 'plugins_loaded', array( $this, 'ai1wm_notification' ), 20 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_export_scripts_and_styles' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_import_scripts_and_styles' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_scripts_and_styles' ), 20 );
	}

	/**
	 * Enable notifications
	 *
	 * @return void
	 */
	public function ai1wm_notification() {
		if ( ai1wmse_is_running() ) {
			add_filter( 'ai1wm_notification_ok_toggle', 'Ai1wmse_Settings_Controller::notify_ok_toggle' );
			add_filter( 'ai1wm_notification_ok_email', 'Ai1wmse_Settings_Controller::notify_email' );
			add_filter( 'ai1wm_notification_error_toggle', 'Ai1wmse_Settings_Controller::notify_error_toggle' );
			add_filter( 'ai1wm_notification_error_subject', 'Ai1wmse_Settings_Controller::notify_error_subject' );
			add_filter( 'ai1wm_notification_error_email', 'Ai1wmse_Settings_Controller::notify_email' );
		}
	}

	/**
	 * Export and import commands
	 *
	 * @return void
	 */
	public function ai1wm_commands() {
		if ( ai1wmse_is_running() ) {
			add_filter( 'ai1wm_export', 'Ai1wmse_Export_S3::execute', 250 );
			add_filter( 'ai1wm_export', 'Ai1wmse_Export_Upload::execute', 260 );
			add_filter( 'ai1wm_export', 'Ai1wmse_Export_Retention::execute', 270 );
			add_filter( 'ai1wm_export', 'Ai1wmse_Export_Done::execute', 280 );
			add_filter( 'ai1wm_import', 'Ai1wmse_Import_S3::execute', 20 );
			add_filter( 'ai1wm_import', 'Ai1wmse_Import_Download::execute', 30 );
			add_filter( 'ai1wm_import', 'Ai1wmse_Import_Settings::execute', 290 );
			add_filter( 'ai1wm_import', 'Ai1wmse_Import_Database::execute', 310 );

			remove_filter( 'ai1wm_export', 'Ai1wm_Export_Download::execute', 250 );
			remove_filter( 'ai1wm_import', 'Ai1wm_Import_Upload::execute', 5 );
		}
	}

	public function get_missing_dependencies() {
		$extensions = array();
		if ( ! extension_loaded( 'curl' ) ) {
			$extensions[] = 'cURL';
		}

		if ( ! extension_loaded( 'libxml' ) ) {
			$extensions[] = 'libxml';
		}

		if ( ! extension_loaded( 'simplexml' ) ) {
			$extensions[] = 'SimpleXML';
		}

		$messages = array();
		if ( ! empty( $extensions ) ) {
			$messages[] = sprintf( __( 'Your PHP is missing: %s. <a href="https://help.servmask.com/knowledgebase/dependencies/" target="_blank">Technical details</a>', AI1WMSE_PLUGIN_NAME ), implode( ', ', $extensions ) );
		}

		return $messages;
	}

	/**
	 * Check whether All-in-One WP Migration has been loaded
	 *
	 * @return void
	 */
	public function ai1wm_loaded() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'admin_menu' ), 20 );
		} else {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		}

		// Amazon S3 init cron
		add_action( 'init', 'Ai1wmse_Settings_Controller::init_cron' );

		// Amazon S3 connection
		add_action( 'admin_post_ai1wmse_s3_connection', 'Ai1wmse_Settings_Controller::connection' );

		// Amazon S3 settings
		add_action( 'admin_post_ai1wmse_s3_settings', 'Ai1wmse_Settings_Controller::settings' );

		// Cron settings
		add_action( 'ai1wmse_s3_hourly_export', 'Ai1wm_Export_Controller::export' );
		add_action( 'ai1wmse_s3_daily_export', 'Ai1wm_Export_Controller::export' );
		add_action( 'ai1wmse_s3_weekly_export', 'Ai1wm_Export_Controller::export' );
		add_action( 'ai1wmse_s3_monthly_export', 'Ai1wm_Export_Controller::export' );

		// Picker
		add_action( 'ai1wm_import_left_end', 'Ai1wmse_Import_Controller::picker' );

		// Add export button
		add_filter( 'ai1wm_export_s3', 'Ai1wmse_Export_Controller::button' );

		// Add import button
		add_filter( 'ai1wm_import_s3', 'Ai1wmse_Import_Controller::button' );
	}

	/**
	 * WP CLI commands: extension
	 *
	 * @return void
	 */
	public function wp_cli_extension() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command(
				'ai1wm s3',
				'Ai1wmse_S3_WP_CLI_Command',
				array(
					'shortdesc'     => __( 'All-in-One WP Migration Command for Amazon S3', AI1WMSE_PLUGIN_NAME ),
					'before_invoke' => array( $this, 'activate_extension_commands' ),
				)
			);
		}
	}

	/**
	 * Activates extension specific commands
	 *
	 * @return void
	 */
	public function activate_extension_commands() {
		$_GET['s3'] = 1;
		$this->ai1wm_commands();
	}

	/**
	 * Display All-in-One WP Migration notice
	 *
	 * @return void
	 */
	public function ai1wm_notice() {
		?>
		<div class="error">
			<p>
				<?php
				_e(
					'Amazon S3 Extension requires <a href="https://wordpress.org/plugins/all-in-one-wp-migration/" target="_blank">All-in-One WP Migration plugin</a> to be activated. ' .
					'<a href="https://help.servmask.com/knowledgebase/install-instructions-for-amazon-s3-extension/" target="_blank">Amazon S3 Extension install instructions</a>',
					AI1WMSE_PLUGIN_NAME
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add links to plugin list page
	 *
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file === AI1WMSE_PLUGIN_BASENAME ) {
			$links[] = __( '<a href="https://help.servmask.com/knowledgebase/amazon-s3-extension-user-guide/" target="_blank">User Guide</a>', AI1WMSE_PLUGIN_NAME );
			$links[] = __( '<a href="https://servmask.com/contact-support" target="_blank">Contact Support</a>', AI1WMSE_PLUGIN_NAME );
		}

		return $links;
	}

	/**
	 * Register initial router
	 *
	 * @return void
	 */
	public function router() {
		if ( current_user_can( 'import' ) ) {
			add_action( 'wp_ajax_ai1wmse_s3_bucket', 'Ai1wmse_Import_Controller::bucket' );
		}
	}
}
