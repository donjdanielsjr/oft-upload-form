<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OFT_Plugin_Updater {
	const DEFAULT_METADATA_BASE = 'https://onefeaturetrap.com/plugin-updates/';

	protected $plugin_file;
	protected $plugin_basename;
	protected $plugin_slug;
	protected $plugin_name;
	protected $metadata_url;
	protected $channels;
	protected $default_channel;
	protected $track_label;
	protected $cache_key_base;
	protected $cache_ttl;
	protected $installed_version;
	protected $channel_option_key;
	protected $installed_channel_option_key;
	protected $switch_option_key;
	protected $debug_page_slug;

	public function __construct( $config ) {
		$defaults = array(
			'plugin_file'                  => '',
			'plugin_slug'                  => '',
			'plugin_name'                  => '',
			'metadata_url'                 => '',
			'cache_key'                    => '',
			'cache_ttl'                    => 6 * HOUR_IN_SECONDS,
			'channels'                     => array(),
			'default_channel'              => 'stable',
			'track_label'                  => __( 'Update Track', 'oft-upload-form' ),
			'channel_option_key'           => '',
			'installed_channel_option_key' => '',
			'switch_option_key'            => '',
		);
		$config = wp_parse_args( is_array( $config ) ? $config : array(), $defaults );

		$this->plugin_file                 = isset( $config['plugin_file'] ) ? (string) $config['plugin_file'] : '';
		$this->plugin_slug                 = sanitize_key( $config['plugin_slug'] );
		$this->plugin_name                 = isset( $config['plugin_name'] ) ? sanitize_text_field( $config['plugin_name'] ) : '';
		$this->plugin_basename             = $this->plugin_file ? plugin_basename( $this->plugin_file ) : '';
		$this->channels                    = $this->normalize_channels( $config['channels'] );
		$this->default_channel             = $this->sanitize_channel( $config['default_channel'] );
		$this->track_label                 = sanitize_text_field( $config['track_label'] );
		$this->cache_key_base              = ! empty( $config['cache_key'] ) ? sanitize_key( $config['cache_key'] ) : 'oft_updater_' . $this->plugin_slug;
		$this->cache_ttl                   = max( MINUTE_IN_SECONDS, absint( $config['cache_ttl'] ) );
		$this->installed_version           = $this->detect_installed_version();
		$this->channel_option_key          = ! empty( $config['channel_option_key'] ) ? sanitize_key( $config['channel_option_key'] ) : 'oft_updater_channel_' . $this->plugin_slug;
		$this->installed_channel_option_key = ! empty( $config['installed_channel_option_key'] ) ? sanitize_key( $config['installed_channel_option_key'] ) : 'oft_updater_installed_channel_' . $this->plugin_slug;
		$this->switch_option_key           = ! empty( $config['switch_option_key'] ) ? sanitize_key( $config['switch_option_key'] ) : 'oft_updater_switch_' . $this->plugin_slug;
		$this->debug_page_slug             = 'oft-plugin-updater-' . $this->plugin_slug;
		$this->metadata_url                = ! empty( $config['metadata_url'] ) ? esc_url_raw( $config['metadata_url'] ) : $this->build_metadata_url( $this->plugin_slug );

		if ( $this->has_channels() ) {
			if ( empty( $this->default_channel ) || ! isset( $this->channels[ $this->default_channel ] ) ) {
				$keys = array_keys( $this->channels );
				$this->default_channel = reset( $keys );
			}
			if ( empty( $this->metadata_url ) ) {
				$this->metadata_url = $this->get_metadata_url_for_channel( $this->default_channel );
			}
		}

		if ( empty( $this->plugin_file ) || empty( $this->plugin_slug ) || empty( $this->plugin_name ) || empty( $this->plugin_basename ) || empty( $this->installed_version ) ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 20, 3 );
		add_action( 'admin_menu', array( $this, 'register_debug_page' ) );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_check_updates_link' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_update_check' ) );
		add_action( 'admin_init', array( $this, 'handle_channel_switch' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_update_checked_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_channel_notice' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache_after_update' ), 10, 2 );
	}

	public function has_channels() {
		return ! empty( $this->channels );
	}

	public function get_current_channel() {
		if ( ! $this->has_channels() ) {
			return '';
		}
		$selected = $this->sanitize_channel( get_option( $this->channel_option_key, $this->default_channel ) );
		return isset( $this->channels[ $selected ] ) ? $selected : $this->default_channel;
	}

	public function get_channel_label( $channel = '' ) {
		if ( ! $this->has_channels() ) {
			return '';
		}
		$channel = $channel ? $this->sanitize_channel( $channel ) : $this->get_current_channel();
		return isset( $this->channels[ $channel ]['label'] ) ? $this->channels[ $channel ]['label'] : '';
	}

	public function render_channel_settings_card( $args = array() ) {
		if ( ! $this->has_channels() ) {
			return;
		}
		$args = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'action_url'  => add_query_arg( array( 'page' => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ), admin_url( 'admin.php' ) ),
				'heading'     => __( 'Updates', 'oft-upload-form' ),
				'help_text'   => __( 'Choose which release stream this plugin should follow. Saving this setting switches the plugin to the latest package on the selected track.', 'oft-upload-form' ),
				'button_text' => __( 'Save and Switch', 'oft-upload-form' ),
			)
		);
		$current_channel = $this->get_current_channel();
		$installed_label = $this->get_installed_channel() ? $this->get_channel_label( $this->get_installed_channel() ) : $this->get_channel_label( $current_channel );
		?>
		<div class="card">
			<h2><?php echo esc_html( $args['heading'] ); ?></h2>
			<p><?php echo esc_html( $args['help_text'] ); ?></p>
			<form method="post" action="<?php echo esc_url( $args['action_url'] ); ?>">
				<?php wp_nonce_field( 'oft_updater_channel_' . $this->plugin_slug ); ?>
				<input type="hidden" name="oft_updater_save_channel" value="1">
				<input type="hidden" name="oft_updater_slug" value="<?php echo esc_attr( $this->plugin_slug ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ); ?>">
				<p>
					<label for="<?php echo esc_attr( $this->plugin_slug . '-update-track' ); ?>"><strong><?php echo esc_html( $this->track_label ); ?></strong></label><br>
					<select id="<?php echo esc_attr( $this->plugin_slug . '-update-track' ); ?>" name="oft_updater_channel">
						<?php foreach ( $this->channels as $channel_key => $channel_config ) : ?>
							<option value="<?php echo esc_attr( $channel_key ); ?>" <?php selected( $current_channel, $channel_key ); ?>><?php echo esc_html( $channel_config['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p><?php echo esc_html( sprintf( __( 'Current track: %1$s. Installed package track: %2$s.', 'oft-upload-form' ), $this->get_channel_label( $current_channel ), $installed_label ) ); ?></p>
				<?php submit_button( $args['button_text'], 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	protected function build_metadata_url( $plugin_slug ) {
		if ( empty( $plugin_slug ) ) {
			return '';
		}
		return trailingslashit( self::DEFAULT_METADATA_BASE . rawurlencode( $plugin_slug ) ) . 'info.json';
	}

	protected function normalize_channels( $channels ) {
		$normalized = array();
		if ( ! is_array( $channels ) ) {
			return $normalized;
		}
		foreach ( $channels as $channel_key => $channel_config ) {
			$channel_key = $this->sanitize_channel( $channel_key );
			if ( empty( $channel_key ) || ! is_array( $channel_config ) || empty( $channel_config['metadata_url'] ) ) {
				continue;
			}
			$normalized[ $channel_key ] = array(
				'label'        => ! empty( $channel_config['label'] ) ? sanitize_text_field( $channel_config['label'] ) : ucfirst( $channel_key ),
				'metadata_url' => esc_url_raw( $channel_config['metadata_url'] ),
			);
		}
		return $normalized;
	}

	protected function sanitize_channel( $channel ) {
		return sanitize_key( (string) $channel );
	}

	protected function detect_installed_version() {
		if ( empty( $this->plugin_file ) || ! file_exists( $this->plugin_file ) ) {
			return '';
		}
		$headers = get_file_data( $this->plugin_file, array( 'Version' => 'Version' ), 'plugin' );
		return ! empty( $headers['Version'] ) ? (string) $headers['Version'] : '';
	}

	protected function get_metadata_url_for_channel( $channel = '' ) {
		if ( ! $this->has_channels() ) {
			return $this->metadata_url;
		}
		$channel = $channel ? $this->sanitize_channel( $channel ) : $this->get_current_channel();
		return isset( $this->channels[ $channel ]['metadata_url'] ) ? $this->channels[ $channel ]['metadata_url'] : '';
	}

	protected function get_active_metadata_url() {
		return $this->get_metadata_url_for_channel();
	}

	protected function get_cache_key( $channel = '' ) {
		if ( ! $this->has_channels() ) {
			return $this->cache_key_base;
		}
		$channel = $channel ? $this->sanitize_channel( $channel ) : $this->get_current_channel();
		return sanitize_key( $this->cache_key_base . '_' . $channel );
	}

	protected function clear_all_cached_metadata() {
		if ( ! $this->has_channels() ) {
			delete_site_transient( $this->cache_key_base );
			return;
		}
		foreach ( array_keys( $this->channels ) as $channel ) {
			delete_site_transient( $this->get_cache_key( $channel ) );
		}
	}

	protected function get_installed_channel() {
		if ( ! $this->has_channels() ) {
			return '';
		}
		$channel = $this->sanitize_channel( get_option( $this->installed_channel_option_key, '' ) );
		if ( isset( $this->channels[ $channel ] ) ) {
			return $channel;
		}
		return $this->infer_channel_from_version( $this->installed_version );
	}

	protected function get_pending_switch_channel() {
		if ( ! $this->has_channels() ) {
			return '';
		}
		$channel = $this->sanitize_channel( get_option( $this->switch_option_key, '' ) );
		return isset( $this->channels[ $channel ] ) ? $channel : '';
	}

	protected function should_force_offer_update( $metadata ) {
		if ( ! $this->has_channels() ) {
			return false;
		}
		$current_channel   = $this->get_current_channel();
		$installed_channel = $this->get_installed_channel();
		$pending_channel   = $this->get_pending_switch_channel();
		if ( $pending_channel && $pending_channel === $current_channel ) {
			return true;
		}
		if ( $installed_channel && $installed_channel !== $current_channel ) {
			return true;
		}
		return ! empty( $installed_channel ) && ! empty( $metadata['channel'] ) && $metadata['channel'] === $current_channel && $metadata['channel'] !== $installed_channel;
	}

	protected function infer_channel_from_version( $version ) {
		if ( ! $this->has_channels() ) {
			return '';
		}
		$version = strtolower( (string) $version );
		if ( isset( $this->channels['beta'] ) && false !== strpos( $version, 'beta' ) ) {
			return 'beta';
		}
		if ( isset( $this->channels['stable'] ) ) {
			return 'stable';
		}
		return '';
	}

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$metadata = $this->get_metadata();
		if ( ! $metadata ) {
			return $transient;
		}
		$plugin_update = $this->build_update_payload( $metadata );
		if ( ! $plugin_update ) {
			return $transient;
		}
		$has_version_update = version_compare( $metadata['version'], $this->installed_version, '>' );
		if ( $has_version_update || $this->should_force_offer_update( $metadata ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ $this->plugin_basename ] = $plugin_update;
			return $transient;
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}
		$transient->no_update[ $this->plugin_basename ] = $plugin_update;
		return $transient;
	}

	public function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $result;
		}
		$metadata = $this->get_metadata();
		return $metadata ? $this->build_plugin_information( $metadata ) : $result;
	}

	public function register_debug_page() {
		add_management_page( sprintf( '%s Update Debug', $this->plugin_name ), sprintf( '%s Update Debug', $this->plugin_name ), 'manage_options', $this->debug_page_slug, array( $this, 'render_debug_page' ) );
	}

	public function add_check_updates_link( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}
		$check_url = wp_nonce_url( add_query_arg( array( 'oft_check_updates' => 1, 'plugin' => rawurlencode( $this->plugin_basename ) ), admin_url( 'plugins.php' ) ), 'oft_check_updates_' . $this->plugin_slug );
		array_unshift( $links, sprintf( '<a href="%1$s">%2$s</a>', esc_url( $check_url ), esc_html__( 'Check for updates', 'oft-upload-form' ) ) );
		return $links;
	}

	public function handle_manual_update_check() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_GET['oft_check_updates'] ) || empty( $_GET['plugin'] ) ) {
			return;
		}
		if ( $this->plugin_basename !== sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'oft_check_updates_' . $this->plugin_slug ) ) {
			return;
		}
		$this->clear_all_cached_metadata();
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
		wp_safe_redirect( add_query_arg( 'oft_update_checked', 1, admin_url( 'plugins.php' ) ) );
		exit;
	}

	public function handle_channel_switch() {
		if ( ! $this->has_channels() || ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_POST['oft_updater_save_channel'] ) || empty( $_POST['oft_updater_slug'] ) ) {
			return;
		}
		if ( $this->plugin_slug !== sanitize_key( wp_unslash( $_POST['oft_updater_slug'] ) ) ) {
			return;
		}
		check_admin_referer( 'oft_updater_channel_' . $this->plugin_slug );
		$selected_channel = isset( $_POST['oft_updater_channel'] ) ? $this->sanitize_channel( wp_unslash( $_POST['oft_updater_channel'] ) ) : '';
		if ( empty( $selected_channel ) || ! isset( $this->channels[ $selected_channel ] ) ) {
			$this->redirect_after_channel_change( 'error', $this->get_current_channel() );
		}

		$previous_channel  = $this->get_current_channel();
		$installed_channel = $this->get_installed_channel();
		update_option( $this->channel_option_key, $selected_channel );

		if ( $selected_channel === $previous_channel && ( empty( $installed_channel ) || $installed_channel === $selected_channel ) ) {
			$this->redirect_after_channel_change( 'saved', $selected_channel );
		}

		update_option( $this->switch_option_key, $selected_channel );
		$this->clear_all_cached_metadata();
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$result = $this->perform_channel_upgrade();
		if ( is_wp_error( $result ) || false === $result ) {
			$this->redirect_after_channel_change( 'error', $selected_channel );
		}

		update_option( $this->installed_channel_option_key, $selected_channel );
		delete_option( $this->switch_option_key );
		$this->clear_all_cached_metadata();
		delete_site_transient( 'update_plugins' );

		$this->redirect_after_channel_change( 'switched', $selected_channel );
	}

	protected function perform_channel_upgrade() {
		$was_active = function_exists( 'is_plugin_active' ) && is_plugin_active( $this->plugin_basename );

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		$skin = new Automatic_Upgrader_Skin();
		$result = ( new Plugin_Upgrader( $skin ) )->upgrade( $this->plugin_basename );

		if ( $was_active && true === $result && function_exists( 'is_plugin_active' ) && ! is_plugin_active( $this->plugin_basename ) ) {
			$activation_result = activate_plugin( $this->plugin_basename );

			if ( is_wp_error( $activation_result ) ) {
				return $activation_result;
			}
		}

		if ( true === $result ) {
			$this->installed_version = $this->detect_installed_version();
		}

		return $result;
	}

	protected function redirect_after_channel_change( $status, $channel ) {
		$page = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : '';
		$redirect_url = admin_url( 'admin.php' );
		if ( ! empty( $page ) ) {
			$redirect_url = add_query_arg( 'page', $page, $redirect_url );
		}
		$redirect_url = add_query_arg( array( 'oft_updater_channel_status' => sanitize_key( $status ), 'oft_updater_channel' => $this->sanitize_channel( $channel ) ), $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function maybe_render_update_checked_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_GET['oft_update_checked'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $this->plugin_name . ' update data refreshed. If a newer version exists, WordPress will show the normal update link on the Plugins screen.' ) . '</p></div>';
	}

	public function maybe_render_channel_notice() {
		if ( ! $this->has_channels() || ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_GET['oft_updater_channel_status'] ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_GET['oft_updater_channel_status'] ) );
		$label  = $this->get_channel_label( isset( $_GET['oft_updater_channel'] ) ? wp_unslash( $_GET['oft_updater_channel'] ) : '' );
		if ( 'switched' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%1$s switched to the %2$s track.', 'oft-upload-form' ), $this->plugin_name, $label ) ) . '</p></div>';
		} elseif ( 'saved' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%1$s is already following the %2$s track.', 'oft-upload-form' ), $this->plugin_name, $label ) ) . '</p></div>';
		} elseif ( 'error' === $status ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The selected update track could not be installed. The saved track was updated, so the next check will continue following that track.', 'oft-upload-form' ) . '</p></div>';
		}
	}

	public function purge_cache_after_update( $upgrader_object, $options ) {
		if ( empty( $options['action'] ) || empty( $options['type'] ) || 'update' !== $options['action'] || 'plugin' !== $options['type'] || empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}
		if ( in_array( $this->plugin_basename, $options['plugins'], true ) ) {
			$this->clear_all_cached_metadata();
			delete_site_transient( 'update_plugins' );
			if ( $this->has_channels() ) {
				update_option( $this->installed_channel_option_key, $this->get_current_channel() );
				delete_option( $this->switch_option_key );
			}
		}
	}

	public function render_debug_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['oft_refresh'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'oft_updater_refresh_' . $this->plugin_slug ) ) {
			$this->clear_all_cached_metadata();
		}
		$metadata = $this->get_metadata();
		$cache_record = $this->get_cache_record();
		$has_update = $metadata && ( version_compare( $metadata['version'], $this->installed_version, '>' ) || $this->should_force_offer_update( $metadata ) );
		$refresh_url = wp_nonce_url( add_query_arg( array( 'page' => $this->debug_page_slug, 'oft_refresh' => 1 ), admin_url( 'tools.php' ) ), 'oft_updater_refresh_' . $this->plugin_slug );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->plugin_name . ' Update Debug' ); ?></h1>
			<p><a class="button" href="<?php echo esc_url( $refresh_url ); ?>">Refresh metadata</a></p>
			<table class="widefat striped" style="max-width: 960px">
				<tbody>
					<tr><th scope="row">Plugin slug</th><td><?php echo esc_html( $this->plugin_slug ); ?></td></tr>
					<?php if ( $this->has_channels() ) : ?>
						<tr><th scope="row"><?php echo esc_html( $this->track_label ); ?></th><td><?php echo esc_html( $this->get_channel_label() ); ?></td></tr>
					<?php endif; ?>
					<tr><th scope="row">Installed version</th><td><?php echo esc_html( $this->installed_version ? $this->installed_version : 'Unknown' ); ?></td></tr>
					<tr><th scope="row">Metadata URL</th><td><code><?php echo esc_html( $this->get_active_metadata_url() ); ?></code></td></tr>
					<tr><th scope="row">Update available</th><td><?php echo esc_html( $has_update ? 'Yes' : 'No' ); ?></td></tr>
					<tr>
						<th scope="row">Last fetched metadata</th>
						<td>
							<?php if ( ! empty( $cache_record['fetched_at'] ) ) : ?>
								<p><strong>Fetched:</strong> <?php echo esc_html( gmdate( 'Y-m-d H:i:s', (int) $cache_record['fetched_at'] ) . ' UTC' ); ?></p>
							<?php endif; ?>
							<pre style="white-space: pre-wrap; max-width: 900px; overflow: auto;"><?php echo esc_html( wp_json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ?: 'No cached metadata available.' ); ?></pre>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	protected function get_metadata() {
		$cache_record = $this->get_cache_record();
		if ( ! empty( $cache_record['data'] ) && is_array( $cache_record['data'] ) ) {
			return $cache_record['data'];
		}
		$metadata = $this->fetch_remote_metadata();
		if ( ! $metadata ) {
			return false;
		}
		$this->store_cache_record( array( 'fetched_at' => time(), 'data' => $metadata ) );
		return $metadata;
	}

	protected function get_cache_record() {
		$cache_record = get_site_transient( $this->get_cache_key() );
		return is_array( $cache_record ) ? $cache_record : array();
	}

	protected function store_cache_record( $cache_record ) {
		set_site_transient( $this->get_cache_key(), $cache_record, $this->cache_ttl );
	}

	protected function fetch_remote_metadata() {
		$metadata_url = $this->get_active_metadata_url();
		if ( empty( $metadata_url ) ) {
			return false;
		}
		$response = wp_remote_get(
			$metadata_url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		$metadata = json_decode( $body, true );
		return is_array( $metadata ) ? $this->normalize_metadata( $metadata, $metadata_url ) : false;
	}

	protected function normalize_metadata( $metadata, $metadata_url ) {
		foreach ( array( 'name', 'slug', 'version', 'download_url' ) as $field ) {
			if ( empty( $metadata[ $field ] ) || ! is_string( $metadata[ $field ] ) ) {
				return false;
			}
		}
		$normalized = array(
			'name'         => sanitize_text_field( $metadata['name'] ),
			'slug'         => sanitize_key( $metadata['slug'] ),
			'version'      => sanitize_text_field( $metadata['version'] ),
			'channel'      => isset( $metadata['channel'] ) ? $this->sanitize_channel( $metadata['channel'] ) : $this->get_current_channel(),
			'requires'     => isset( $metadata['requires'] ) ? sanitize_text_field( $metadata['requires'] ) : '',
			'tested'       => isset( $metadata['tested'] ) ? sanitize_text_field( $metadata['tested'] ) : '',
			'requires_php' => isset( $metadata['requires_php'] ) ? sanitize_text_field( $metadata['requires_php'] ) : '',
			'last_updated' => isset( $metadata['last_updated'] ) ? sanitize_text_field( $metadata['last_updated'] ) : '',
			'homepage'     => isset( $metadata['homepage'] ) ? esc_url_raw( $metadata['homepage'] ) : '',
			'download_url' => esc_url_raw( $metadata['download_url'] ),
			'sections'     => isset( $metadata['sections'] ) && is_array( $metadata['sections'] ) ? $metadata['sections'] : array(),
			'banners'      => isset( $metadata['banners'] ) && is_array( $metadata['banners'] ) ? $metadata['banners'] : array(),
			'icons'        => isset( $metadata['icons'] ) && is_array( $metadata['icons'] ) ? $metadata['icons'] : array(),
		);
		if ( $this->plugin_slug !== $normalized['slug'] || ! $this->is_trusted_package_url( $normalized['download_url'], $metadata_url ) ) {
			return false;
		}
		return $normalized;
	}

	protected function is_trusted_package_url( $package_url, $metadata_url ) {
		$package_parts  = wp_parse_url( $package_url );
		$metadata_parts = wp_parse_url( $metadata_url );
		if ( empty( $package_parts['scheme'] ) || empty( $package_parts['host'] ) || 'https' !== strtolower( $package_parts['scheme'] ) || empty( $metadata_parts['host'] ) ) {
			return false;
		}
		return strtolower( $package_parts['host'] ) === strtolower( $metadata_parts['host'] );
	}

	protected function build_update_payload( $metadata ) {
		if ( empty( $metadata['download_url'] ) || empty( $metadata['version'] ) ) {
			return false;
		}
		$payload = new stdClass();
		$payload->id = $this->plugin_basename;
		$payload->slug = $this->plugin_slug;
		$payload->plugin = $this->plugin_basename;
		$payload->new_version = $metadata['version'];
		$payload->url = ! empty( $metadata['homepage'] ) ? $metadata['homepage'] : '';
		$payload->package = $metadata['download_url'];
		$payload->tested = $metadata['tested'];
		$payload->requires = $metadata['requires'];
		$payload->requires_php = $metadata['requires_php'];
		$payload->icons = $metadata['icons'];
		$payload->banners = $metadata['banners'];
		return $payload;
	}

	protected function build_plugin_information( $metadata ) {
		$info = new stdClass();
		$info->name = $metadata['name'];
		$info->slug = $metadata['slug'];
		$info->version = $metadata['version'];
		$info->author = ! empty( $metadata['homepage'] ) ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $metadata['homepage'] ), esc_html( $metadata['name'] ) ) : $metadata['name'];
		$info->author_profile = ! empty( $metadata['homepage'] ) ? $metadata['homepage'] : '';
		$info->homepage = $metadata['homepage'];
		$info->requires = $metadata['requires'];
		$info->tested = $metadata['tested'];
		$info->requires_php = $metadata['requires_php'];
		$info->last_updated = $metadata['last_updated'];
		$info->download_link = $metadata['download_url'];
		$info->sections = $metadata['sections'];
		$info->banners = $metadata['banners'];
		$info->icons = $metadata['icons'];
		return $info;
	}
}
