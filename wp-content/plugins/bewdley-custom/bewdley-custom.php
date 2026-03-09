<?php
/**
 * Plugin Name: Bewdley Custom
 * Description: Site-specific utilities for Bewdley Farm Shop.
 * Version: 0.1.0
 * Author: Bewdley Farm Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEWDLEY_CUSTOM_OPTION_KEY', 'bewdley_custom_email_settings' );
define( 'BEWDLEY_CONSENT_META_KEY', '_bewdley_marketing_optin' );

/**
 * Add a read-only audit page to help identify historical newsletter consent keys.
 */
add_action( 'admin_menu', function () {
	add_management_page(
		__( 'Email Consent Audit', 'bewdley-custom' ),
		__( 'Email Consent Audit', 'bewdley-custom' ),
		'manage_options',
		'bewdley-email-consent-audit',
		'bewdley_render_email_consent_audit'
	);

	add_management_page(
		__( 'Email Sync Settings', 'bewdley-custom' ),
		__( 'Email Sync Settings', 'bewdley-custom' ),
		'manage_options',
		'bewdley-email-sync-settings',
		'bewdley_render_email_sync_settings'
	);

	add_management_page(
		__( 'Legacy Subscriber Export', 'bewdley-custom' ),
		__( 'Legacy Subscriber Export', 'bewdley-custom' ),
		'manage_options',
		'bewdley-legacy-subscriber-export',
		'bewdley_render_legacy_subscriber_export'
	);

	add_management_page(
		__( 'CRM Backfill Tools', 'bewdley-custom' ),
		__( 'CRM Backfill Tools', 'bewdley-custom' ),
		'manage_options',
		'bewdley-crm-backfill-tools',
		'bewdley_render_crm_backfill_tools'
	);
} );

/**
 * Register plugin settings.
 */
add_action( 'admin_init', function () {
	register_setting(
		'bewdley_custom_email',
		BEWDLEY_CUSTOM_OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bewdley_sanitize_email_settings',
			'default'           => bewdley_get_default_settings(),
		)
	);
} );

/**
 * Default settings.
 *
 * @return array
 */
function bewdley_get_default_settings() {
	return array(
		'enable_order_sync'            => 'no',
		'consent_meta_key'             => BEWDLEY_CONSENT_META_KEY,
		'consent_allowed_values'       => 'yes,1,true,on,checked',
		'allow_legacy_without_consent' => 'no',
		'default_source_label'         => 'Woo Checkout',
		'fluentcrm_list_targets'       => 'Customers',
		'fluentcrm_tag_targets'        => '',
	);
}

/**
 * Add a checkout consent checkbox without requiring template edits.
 *
 * This relies on standard WooCommerce checkout field rendering, which Bricks
 * checkout templates typically preserve when using Woo checkout elements.
 */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
		$fields['billing'] = array();
	}

	$fields['billing'][ BEWDLEY_CONSENT_META_KEY ] = array(
		'type'     => 'checkbox',
		'label'    => __( 'I would like to receive farm shop news and offers by email.', 'bewdley-custom' ),
		'required' => false,
		'class'    => array( 'form-row-wide' ),
		'priority' => 999,
	);

	return $fields;
}, 20 );

/**
 * Persist checkout consent value to order meta.
 */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
		return;
	}

	$consent_raw = isset( $_POST[ BEWDLEY_CONSENT_META_KEY ] ) ? wp_unslash( $_POST[ BEWDLEY_CONSENT_META_KEY ] ) : '';
	$consent_on  = '' !== $consent_raw;

	$order->update_meta_data( BEWDLEY_CONSENT_META_KEY, $consent_on ? 'yes' : 'no' );
}, 20, 2 );

/**
 * Get merged settings.
 *
 * @return array
 */
function bewdley_get_settings() {
	$saved = get_option( BEWDLEY_CUSTOM_OPTION_KEY, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, bewdley_get_default_settings() );
}

/**
 * Sanitize settings values.
 *
 * @param array $input Raw submitted values.
 * @return array
 */
function bewdley_sanitize_email_settings( $input ) {
	$defaults = bewdley_get_default_settings();
	$input    = is_array( $input ) ? $input : array();

	return array(
		'enable_order_sync'            => ( isset( $input['enable_order_sync'] ) && 'yes' === $input['enable_order_sync'] ) ? 'yes' : 'no',
		'consent_meta_key'             => isset( $input['consent_meta_key'] ) ? sanitize_text_field( $input['consent_meta_key'] ) : $defaults['consent_meta_key'],
		'consent_allowed_values'       => isset( $input['consent_allowed_values'] ) ? sanitize_text_field( $input['consent_allowed_values'] ) : $defaults['consent_allowed_values'],
		'allow_legacy_without_consent' => ( isset( $input['allow_legacy_without_consent'] ) && 'yes' === $input['allow_legacy_without_consent'] ) ? 'yes' : 'no',
		'default_source_label'         => isset( $input['default_source_label'] ) ? sanitize_text_field( $input['default_source_label'] ) : $defaults['default_source_label'],
		'fluentcrm_list_targets'       => isset( $input['fluentcrm_list_targets'] ) ? sanitize_text_field( $input['fluentcrm_list_targets'] ) : $defaults['fluentcrm_list_targets'],
		'fluentcrm_tag_targets'        => isset( $input['fluentcrm_tag_targets'] ) ? sanitize_text_field( $input['fluentcrm_tag_targets'] ) : $defaults['fluentcrm_tag_targets'],
	);
}

/**
 * Render sync settings page.
 */
function bewdley_render_email_sync_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = bewdley_get_settings();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Email Sync Settings', 'bewdley-custom' ); ?></h1>
		<p><?php echo esc_html__( 'Configure safe defaults for WooCommerce order contact sync.', 'bewdley-custom' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'bewdley_custom_email' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Enable Order Sync', 'bewdley-custom' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[enable_order_sync]" value="yes" <?php checked( 'yes', $settings['enable_order_sync'] ); ?> />
							<?php echo esc_html__( 'Sync contacts on WooCommerce order events', 'bewdley-custom' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Consent Meta Key', 'bewdley-custom' ); ?></th>
					<td>
						<input class="regular-text" type="text" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[consent_meta_key]" value="<?php echo esc_attr( $settings['consent_meta_key'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional: order meta key used to confirm newsletter opt-in.', 'bewdley-custom' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Allowed Consent Values', 'bewdley-custom' ); ?></th>
					<td>
						<input class="regular-text" type="text" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[consent_allowed_values]" value="<?php echo esc_attr( $settings['consent_allowed_values'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Comma-separated values treated as opted-in (example: yes,1,true,on).', 'bewdley-custom' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Allow Legacy Without Consent Key', 'bewdley-custom' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[allow_legacy_without_consent]" value="yes" <?php checked( 'yes', $settings['allow_legacy_without_consent'] ); ?> />
							<?php echo esc_html__( 'Allow sync when consent key is blank/missing (legacy decision mode)', 'bewdley-custom' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Default Source Label', 'bewdley-custom' ); ?></th>
					<td>
						<input class="regular-text" type="text" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[default_source_label]" value="<?php echo esc_attr( $settings['default_source_label'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'FluentCRM List Targets', 'bewdley-custom' ); ?></th>
					<td>
						<input class="regular-text" type="text" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[fluentcrm_list_targets]" value="<?php echo esc_attr( $settings['fluentcrm_list_targets'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Comma-separated FluentCRM list names, slugs, or IDs (example: Customers).', 'bewdley-custom' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'FluentCRM Tag Targets', 'bewdley-custom' ); ?></th>
					<td>
						<input class="regular-text" type="text" name="<?php echo esc_attr( BEWDLEY_CUSTOM_OPTION_KEY ); ?>[fluentcrm_tag_targets]" value="<?php echo esc_attr( $settings['fluentcrm_tag_targets'] ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional comma-separated FluentCRM tag names, slugs, or IDs.', 'bewdley-custom' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'bewdley-custom' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Render legacy export page.
 */
function bewdley_render_legacy_subscriber_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Legacy Subscriber Export', 'bewdley-custom' ); ?></h1>
		<p><?php echo esc_html__( 'Generate a CSV from WooCommerce orders for import/cleaning.', 'bewdley-custom' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bewdley_export_legacy_subscribers' ); ?>
			<input type="hidden" name="action" value="bewdley_export_legacy_subscribers" />
			<?php submit_button( __( 'Download CSV', 'bewdley-custom' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Render CRM backfill tools page.
 */
function bewdley_render_crm_backfill_tools() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = bewdley_get_settings();
	$list_raw = isset( $settings['fluentcrm_list_targets'] ) ? (string) $settings['fluentcrm_list_targets'] : '';
	$tag_raw  = isset( $settings['fluentcrm_tag_targets'] ) ? (string) $settings['fluentcrm_tag_targets'] : '';

	$updated_count = isset( $_GET['updated_count'] ) ? absint( $_GET['updated_count'] ) : null;
	$error_code    = isset( $_GET['bewdley_error'] ) ? sanitize_text_field( wp_unslash( $_GET['bewdley_error'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'CRM Backfill Tools', 'bewdley-custom' ); ?></h1>
		<p><?php echo esc_html__( 'Run one-time maintenance actions for existing FluentCRM contacts.', 'bewdley-custom' ); ?></p>

		<?php if ( null !== $updated_count ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( sprintf( __( 'Backfill complete. Processed %d subscribed contact(s).', 'bewdley-custom' ), $updated_count ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( 'missing_tags' === $error_code ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html__( 'Provide at least one tag target below, or set FluentCRM Tag Targets in Email Sync Settings.', 'bewdley-custom' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( 'missing_fluentcrm' === $error_code ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html__( 'FluentCRM classes were not available. Confirm FluentCRM is active and try again.', 'bewdley-custom' ); ?></p>
			</div>
		<?php endif; ?>

		<h2><?php echo esc_html__( 'Apply Configured Tags To Existing Contacts', 'bewdley-custom' ); ?></h2>
		<p><?php echo esc_html__( 'This action applies configured tag targets to subscribed contacts. If list targets are configured, only contacts in those lists are processed.', 'bewdley-custom' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Current List Targets', 'bewdley-custom' ); ?></th>
				<td><code><?php echo esc_html( '' !== trim( $list_raw ) ? $list_raw : __( '(none)', 'bewdley-custom' ) ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Current Tag Targets', 'bewdley-custom' ); ?></th>
				<td><code><?php echo esc_html( '' !== trim( $tag_raw ) ? $tag_raw : __( '(none)', 'bewdley-custom' ) ); ?></code></td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bewdley_backfill_contact_tags' ); ?>
			<input type="hidden" name="action" value="bewdley_backfill_contact_tags" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Tag Targets For This Run', 'bewdley-custom' ); ?></th>
					<td>
						<input class="regular-text" type="text" name="backfill_tag_targets" value="<?php echo esc_attr( $tag_raw ); ?>" />
						<p class="description"><?php echo esc_html__( 'Comma-separated tag names, slugs, or IDs. Missing tag names will be created for this backfill.', 'bewdley-custom' ); ?></p>
						<label>
							<input type="checkbox" name="save_backfill_tag_targets" value="yes" />
							<?php echo esc_html__( 'Save these values to Email Sync Settings', 'bewdley-custom' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Run Tag Backfill', 'bewdley-custom' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Export legacy subscribers from WooCommerce orders.
 */
add_action( 'admin_post_bewdley_export_legacy_subscribers', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'bewdley-custom' ) );
	}

	check_admin_referer( 'bewdley_export_legacy_subscribers' );

	if ( ! function_exists( 'wc_get_orders' ) ) {
		wp_die( esc_html__( 'WooCommerce is not active.', 'bewdley-custom' ) );
	}

	$settings      = bewdley_get_settings();
	$consent_key   = trim( (string) $settings['consent_meta_key'] );
	$allow_legacy  = 'yes' === $settings['allow_legacy_without_consent'];
	$allowed_raw   = array_filter( array_map( 'trim', explode( ',', (string) $settings['consent_allowed_values'] ) ) );
	$allowed_map   = array_fill_keys( array_map( 'strtolower', $allowed_raw ), true );
	$known_records = array();

	$paged = 1;

	while ( true ) {
		$orders = wc_get_orders(
			array(
				'limit'   => 100,
				'page'    => $paged,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
				'type'    => 'shop_order',
				'status'  => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			)
		);

		if ( empty( $orders ) ) {
			break;
		}

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) {
				continue;
			}

			$email = strtolower( trim( (string) $order->get_billing_email() ) );

			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}

			$consent_value = '';
			$include       = false;

			if ( '' !== $consent_key ) {
				$consent_value = (string) $order->get_meta( $consent_key, true );
				$include       = isset( $allowed_map[ strtolower( trim( $consent_value ) ) ] );
			} else {
				$include = $allow_legacy;
			}

			if ( ! $include ) {
				continue;
			}

			if ( ! isset( $known_records[ $email ] ) ) {
				$known_records[ $email ] = array(
					'email'            => $email,
					'first_name'       => (string) $order->get_billing_first_name(),
					'last_name'        => (string) $order->get_billing_last_name(),
					'source'           => (string) $settings['default_source_label'],
					'consent_meta_key' => $consent_key,
					'consent_value'    => $consent_value,
					'order_id'         => (string) $order->get_id(),
					'order_date'       => (string) $order->get_date_created(),
				);
			}
		}

		++$paged;
	}

	$filename = 'bewdley-legacy-subscribers-' . gmdate( 'Ymd-His' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'email', 'first_name', 'last_name', 'source', 'consent_meta_key', 'consent_value', 'order_id', 'order_date' ) );

	foreach ( $known_records as $record ) {
		fputcsv( $output, $record );
	}

	fclose( $output );
	exit;
} );

/**
 * Apply configured FluentCRM tags to existing subscribed contacts.
 */
add_action( 'admin_post_bewdley_backfill_contact_tags', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'bewdley-custom' ) );
	}

	check_admin_referer( 'bewdley_backfill_contact_tags' );

	$redirect_url = admin_url( 'tools.php?page=bewdley-crm-backfill-tools' );
	$settings = bewdley_get_settings();
	$tag_raw  = isset( $settings['fluentcrm_tag_targets'] ) ? (string) $settings['fluentcrm_tag_targets'] : '';

	if ( isset( $_POST['backfill_tag_targets'] ) ) {
		$posted_tag_raw = sanitize_text_field( wp_unslash( $_POST['backfill_tag_targets'] ) );

		if ( '' !== trim( $posted_tag_raw ) ) {
			$tag_raw = $posted_tag_raw;
		}

		if ( isset( $_POST['save_backfill_tag_targets'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['save_backfill_tag_targets'] ) ) ) {
			$settings['fluentcrm_tag_targets'] = $tag_raw;
			update_option( BEWDLEY_CUSTOM_OPTION_KEY, wp_parse_args( $settings, bewdley_get_default_settings() ) );
		}
	}

	$tag_ids = bewdley_resolve_fluentcrm_object_ids( 'tag', $tag_raw, true );

	if ( empty( $tag_ids ) ) {
		wp_safe_redirect( add_query_arg( 'bewdley_error', 'missing_tags', $redirect_url ) );
		exit;
	}

	if ( ! class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
		wp_safe_redirect( add_query_arg( 'bewdley_error', 'missing_fluentcrm', $redirect_url ) );
		exit;
	}

	global $wpdb;

	$subscribers_table = $wpdb->prefix . 'fc_subscribers';
	$pivot_table       = $wpdb->prefix . 'fc_subscriber_pivot';
	$list_type         = 'FluentCrm\\App\\Models\\Lists';
	$list_ids          = bewdley_resolve_fluentcrm_object_ids( 'list', isset( $settings['fluentcrm_list_targets'] ) ? (string) $settings['fluentcrm_list_targets'] : '' );
	$subscriber_ids    = array();

	if ( ! empty( $list_ids ) ) {
		$list_placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		$sql               = "
			SELECT DISTINCT s.id
			FROM {$subscribers_table} s
			INNER JOIN {$pivot_table} p ON p.subscriber_id = s.id
			WHERE s.status = %s
			AND p.object_type = %s
			AND p.object_id IN ({$list_placeholders})
		";

		$query_args    = array_merge( array( 'subscribed', $list_type ), array_map( 'absint', $list_ids ) );
		$prepared_sql  = $wpdb->prepare( $sql, $query_args );
		$subscriber_ids = array_map( 'absint', (array) $wpdb->get_col( $prepared_sql ) );
	} else {
		$sql            = "SELECT id FROM {$subscribers_table} WHERE status = %s";
		$prepared_sql   = $wpdb->prepare( $sql, 'subscribed' );
		$subscriber_ids = array_map( 'absint', (array) $wpdb->get_col( $prepared_sql ) );
	}

	$subscriber_ids = array_values( array_unique( array_filter( $subscriber_ids ) ) );
	$updated_count  = 0;

	if ( ! empty( $subscriber_ids ) ) {
		$subscriber_class = '\\FluentCrm\\App\\Models\\Subscriber';
		$chunked_ids      = array_chunk( $subscriber_ids, 100 );

		foreach ( $chunked_ids as $id_chunk ) {
			$models = $subscriber_class::whereIn( 'id', $id_chunk )->get();

			foreach ( $models as $model ) {
				if ( method_exists( $model, 'attachTags' ) ) {
					$model->attachTags( $tag_ids );
					++$updated_count;
				}
			}
		}
	}

	wp_safe_redirect( add_query_arg( 'updated_count', $updated_count, $redirect_url ) );
	exit;
} );

/**
 * Trigger sync on order lifecycle events.
 */
add_action( 'woocommerce_order_status_processing', 'bewdley_maybe_sync_order_contact', 20 );
add_action( 'woocommerce_order_status_completed', 'bewdley_maybe_sync_order_contact', 20 );

/**
 * Sync contact from order into FluentCRM when conditions pass.
 *
 * @param int $order_id WooCommerce order ID.
 */
function bewdley_maybe_sync_order_contact( $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$settings = bewdley_get_settings();

	if ( 'yes' !== $settings['enable_order_sync'] ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		return;
	}

	$email = strtolower( trim( (string) $order->get_billing_email() ) );

	if ( '' === $email || ! is_email( $email ) ) {
		return;
	}

	$consent_key  = trim( (string) $settings['consent_meta_key'] );
	$allow_legacy = 'yes' === $settings['allow_legacy_without_consent'];
	$can_sync     = false;

	if ( '' !== $consent_key ) {
		$consent_value = strtolower( trim( (string) $order->get_meta( $consent_key, true ) ) );
		$allowed_raw   = array_filter( array_map( 'trim', explode( ',', (string) $settings['consent_allowed_values'] ) ) );
		$allowed_map   = array_fill_keys( array_map( 'strtolower', $allowed_raw ), true );
		$can_sync      = isset( $allowed_map[ $consent_value ] );
	} else {
		$can_sync = $allow_legacy;
	}

	if ( ! $can_sync ) {
		return;
	}

	$payload = array(
		'email'      => $email,
		'first_name' => (string) $order->get_billing_first_name(),
		'last_name'  => (string) $order->get_billing_last_name(),
		'source'     => (string) $settings['default_source_label'],
		'order_id'   => (int) $order->get_id(),
	);

	// Extension point for project-specific list/tag mapping.
	do_action( 'bewdley_email_sync_contact', $payload, $order, $settings );

	bewdley_try_sync_fluentcrm( $payload, $settings );
}

/**
 * Best-effort FluentCRM sync with graceful fallback.
 *
 * @param array $payload  Contact payload.
 * @param array $settings Plugin settings.
 */
function bewdley_try_sync_fluentcrm( $payload, $settings ) {
	if ( ! class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
		return;
	}

	$subscriber_class = '\\FluentCrm\\App\\Models\\Subscriber';

	if ( ! method_exists( $subscriber_class, 'updateOrCreate' ) ) {
		return;
	}

	try {
		$subscriber_instance = new $subscriber_class();
		$subscriber_instance->updateOrCreate(
			array(
				'email'      => $payload['email'],
				'first_name' => $payload['first_name'],
				'last_name'  => $payload['last_name'],
				'status'     => 'subscribed',
			)
		);

		$subscriber = $subscriber_class::where( 'email', $payload['email'] )->first();

		if ( ! $subscriber ) {
			return;
		}

		$list_ids = bewdley_resolve_fluentcrm_object_ids( 'list', isset( $settings['fluentcrm_list_targets'] ) ? (string) $settings['fluentcrm_list_targets'] : '' );
		$tag_ids  = bewdley_resolve_fluentcrm_object_ids( 'tag', isset( $settings['fluentcrm_tag_targets'] ) ? (string) $settings['fluentcrm_tag_targets'] : '' );

		if ( $list_ids && method_exists( $subscriber, 'attachLists' ) ) {
			$subscriber->attachLists( $list_ids );
		}

		if ( $tag_ids && method_exists( $subscriber, 'attachTags' ) ) {
			$subscriber->attachTags( $tag_ids );
		}
	} catch ( Exception $e ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->warning( 'FluentCRM sync failed: ' . $e->getMessage(), array( 'source' => 'bewdley-custom' ) );
		}
	}
}

/**
 * Resolve FluentCRM list/tag targets by ID, title, or slug.
 *
 * @param string $type           Either "list" or "tag".
 * @param string $targets        Comma-separated targets.
 * @param bool   $create_missing Create missing tags when type is "tag".
 * @return int[]
 */
function bewdley_resolve_fluentcrm_object_ids( $type, $targets, $create_missing = false ) {
	$targets = array_filter( array_map( 'trim', explode( ',', (string) $targets ) ) );

	if ( empty( $targets ) ) {
		return array();
	}

	if ( 'list' === $type ) {
		$model_class = '\\FluentCrm\\App\\Models\\Lists';
	} elseif ( 'tag' === $type ) {
		$model_class = '\\FluentCrm\\App\\Models\\Tag';
	} else {
		return array();
	}

	if ( ! class_exists( $model_class ) ) {
		return array();
	}

	$ids = array();

	foreach ( $targets as $target ) {
		if ( is_numeric( $target ) ) {
			$ids[] = (int) $target;
			continue;
		}

		$slug  = sanitize_title( $target );
		$model = $model_class::where( 'title', $target )
			->orWhere( 'slug', $slug )
			->first();

		if ( $model && isset( $model->id ) ) {
			$ids[] = (int) $model->id;
			continue;
		}

		if ( 'tag' === $type && $create_missing && method_exists( $model_class, 'create' ) ) {
			$created = $model_class::create(
				array(
					'title' => $target,
					'slug'  => $slug,
				)
			);

			if ( $created && isset( $created->id ) ) {
				$ids[] = (int) $created->id;
			}
		}
	}

	$ids = array_values( array_unique( array_filter( $ids ) ) );

	return $ids;
}

/**
 * Render the audit report.
 */
function bewdley_render_email_consent_audit() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;

	$patterns = array( '%newsletter%', '%subscribe%', '%consent%', '%mailchimp%', '%marketing%', '%optin%', '%opt_in%' );
	$like_sql = implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_key LIKE %s' ) );

	$order_meta_rows = array();
	$user_meta_rows  = array();
	$hpos_rows       = array();

	// Classic order meta path (wp_posts/wp_postmeta).
	$order_meta_sql = "
		SELECT pm.meta_key, COUNT(*) AS key_count, MAX(CASE WHEN pm.meta_value <> '' THEN pm.meta_value ELSE NULL END) AS sample_value
		FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE p.post_type IN ('shop_order', 'shop_order_refund') AND ({$like_sql})
		GROUP BY pm.meta_key
		ORDER BY key_count DESC, pm.meta_key ASC
		LIMIT 200
	";
	$order_meta_rows = $wpdb->get_results( $wpdb->prepare( $order_meta_sql, $patterns ), ARRAY_A );

	// User meta path.
	$user_meta_sql = "
		SELECT um.meta_key, COUNT(*) AS key_count, MAX(CASE WHEN um.meta_value <> '' THEN um.meta_value ELSE NULL END) AS sample_value
		FROM {$wpdb->usermeta} um
		WHERE {$like_sql}
		GROUP BY um.meta_key
		ORDER BY key_count DESC, um.meta_key ASC
		LIMIT 200
	";
	$user_meta_rows = $wpdb->get_results( $wpdb->prepare( $user_meta_sql, $patterns ), ARRAY_A );

	// HPOS order meta path, if available.
	$hpos_table = $wpdb->prefix . 'wc_orders_meta';
	$hpos_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) );

	if ( $hpos_exist ) {
		$hpos_sql = "
			SELECT wom.meta_key, COUNT(*) AS key_count, MAX(CASE WHEN wom.meta_value <> '' THEN wom.meta_value ELSE NULL END) AS sample_value
			FROM {$hpos_table} wom
			WHERE {$like_sql}
			GROUP BY wom.meta_key
			ORDER BY key_count DESC, wom.meta_key ASC
			LIMIT 200
		";
		$hpos_rows = $wpdb->get_results( $wpdb->prepare( $hpos_sql, $patterns ), ARRAY_A );
	}

	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Email Consent Audit', 'bewdley-custom' ); ?></h1>
		<p>
			<?php echo esc_html__( 'Read-only report to identify possible newsletter/consent keys before import or sync work.', 'bewdley-custom' ); ?>
		</p>

		<?php bewdley_render_audit_table( __( 'Order Meta (Classic)', 'bewdley-custom' ), $order_meta_rows ); ?>
		<?php bewdley_render_audit_table( __( 'User Meta', 'bewdley-custom' ), $user_meta_rows ); ?>

		<?php if ( $hpos_exist ) : ?>
			<?php bewdley_render_audit_table( __( 'Order Meta (HPOS)', 'bewdley-custom' ), $hpos_rows ); ?>
		<?php else : ?>
			<h2><?php echo esc_html__( 'Order Meta (HPOS)', 'bewdley-custom' ); ?></h2>
			<p><?php echo esc_html__( 'HPOS table not found on this environment.', 'bewdley-custom' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render generic table section.
 *
 * @param string $title Section title.
 * @param array  $rows  Query rows.
 */
function bewdley_render_audit_table( $title, $rows ) {
	?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<?php if ( empty( $rows ) ) : ?>
		<p><?php echo esc_html__( 'No matching keys found.', 'bewdley-custom' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<table class="widefat striped" style="max-width: 1200px;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Meta Key', 'bewdley-custom' ); ?></th>
				<th><?php echo esc_html__( 'Count', 'bewdley-custom' ); ?></th>
				<th><?php echo esc_html__( 'Sample Value', 'bewdley-custom' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( (string) $row['meta_key'] ); ?></code></td>
					<td><?php echo esc_html( (string) $row['key_count'] ); ?></td>
					<td><code><?php echo esc_html( substr( (string) $row['sample_value'], 0, 160 ) ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
