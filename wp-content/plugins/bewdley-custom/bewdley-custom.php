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
 * Block checkout: register marketing opt-in using WooCommerce Additional Fields API.
 * Requires WooCommerce 8.9+. Adds checkbox to the Contact information section.
 */
add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	woocommerce_register_additional_checkout_field(
		array(
			'id'       => 'bewdley-custom/marketing-optin',
			'label'    => __( 'I would like to receive farm shop news and offers by email.', 'bewdley-custom' ),
			'location' => 'contact',
			'type'     => 'checkbox',
			'required' => false,
			'default'  => true,
		)
	);
} );

/**
 * Block checkout: copy the additional field value to our standard consent meta key.
 */
add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
	if ( ! $order || ! method_exists( $order, 'get_meta' ) ) {
		return;
	}

	$value      = $order->get_meta( 'bewdley-custom/marketing-optin' );
	$consent_on = ( true === $value || '1' === (string) $value || 1 === $value );

	$order->update_meta_data( BEWDLEY_CONSENT_META_KEY, $consent_on ? 'yes' : 'no' );
	$order->save_meta_data();
}, 20 );

/**
 * Classic checkout: add consent checkbox via fields filter (fallback for non-block checkout).
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
		'default'  => 1,
	);

	return $fields;
}, 20 );

/**
 * Classic checkout: persist consent value to order meta.
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
// ---------------------------------------------------------------------------
// Newsletter signup shortcode [bewdley_signup]
// ---------------------------------------------------------------------------

/**
 * Handle AJAX submission for the newsletter signup form.
 */
add_action( 'wp_ajax_bewdley_newsletter_signup', 'bewdley_handle_newsletter_signup' );
add_action( 'wp_ajax_nopriv_bewdley_newsletter_signup', 'bewdley_handle_newsletter_signup' );

function bewdley_handle_newsletter_signup() {
	check_ajax_referer( 'bewdley_newsletter_nonce', 'nonce' );

	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$consent = isset( $_POST['consent'] ) ? (bool) $_POST['consent'] : false;

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'bewdley-custom' ) ) );
	}

	if ( ! $consent ) {
		wp_send_json_error( array( 'message' => __( 'Please tick the consent box to sign up.', 'bewdley-custom' ) ) );
	}

	$subscriber_class = 'FluentCrm\App\Models\Subscriber';
	if ( ! class_exists( $subscriber_class ) ) {
		wp_send_json_error( array( 'message' => __( 'Signup is currently unavailable. Please try again later.', 'bewdley-custom' ) ) );
	}

	$settings = bewdley_get_settings();
	$list_ids = bewdley_resolve_fluentcrm_object_ids( 'list', isset( $settings['fluentcrm_list_targets'] ) ? (string) $settings['fluentcrm_list_targets'] : '' );
	$tag_ids  = bewdley_resolve_fluentcrm_object_ids( 'tag', isset( $settings['fluentcrm_tag_targets'] ) ? (string) $settings['fluentcrm_tag_targets'] : '', true );

	// Split full name into first/last.
	$name_parts = array_filter( explode( ' ', $name, 2 ) );
	$first_name = isset( $name_parts[0] ) ? $name_parts[0] : '';
	$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

	$contact_data = array(
		'email'  => $email,
		'status' => 'subscribed',
		'source' => 'Newsletter Signup Form',
	);

	if ( '' !== $first_name ) {
		$contact_data['first_name'] = $first_name;
	}
	if ( '' !== $last_name ) {
		$contact_data['last_name'] = $last_name;
	}

	$contact = $subscriber_class::where( 'email', $email )->first();

	if ( $contact ) {
		$contact->fill( $contact_data );
		$contact->save();
	} else {
		$contact = $subscriber_class::create( $contact_data );
	}

	if ( $contact && ! empty( $list_ids ) && method_exists( $contact, 'attachLists' ) ) {
		$contact->attachLists( $list_ids );
	}

	if ( $contact && ! empty( $tag_ids ) && method_exists( $contact, 'attachTags' ) ) {
		$contact->attachTags( $tag_ids );
	}

	wp_send_json_success( array( 'message' => __( 'You\'re signed up — thank you! Look out for news from the farm shop.', 'bewdley-custom' ) ) );
}

/**
 * Render the newsletter signup form.
 * Usage: [bewdley_signup]
 * Optional attributes: heading, subtext, button, image_id
 */
add_shortcode( 'bewdley_signup', function ( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading'  => 'Subscribe to our mailing list',
			'subtext'  => 'Join our mailing list to hear about farm shop events, seasonal produce and special offers.',
			'button'   => 'Yes please, sign me up',
			'image_id' => 0,
		),
		$atts,
		'bewdley_signup'
	);

	wp_enqueue_script(
		'bewdley-signup',
		plugin_dir_url( __FILE__ ) . 'assets/signup.js',
		array( 'jquery' ),
		'1.1.0',
		true
	);

	wp_localize_script(
		'bewdley-signup',
		'bewdleySignup',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'bewdley_newsletter_nonce' ),
			'consentError' => __( 'Please tick the consent box to sign up.', 'bewdley-custom' ),
			'emailError'   => __( 'Please enter a valid email address.', 'bewdley-custom' ),
			'nameError'    => __( 'Please enter your name.', 'bewdley-custom' ),
		)
	);

	// Image — use attachment ID if provided, otherwise fall back to URL search.
	$image_html = '';
	$image_id   = absint( $atts['image_id'] );
	if ( $image_id ) {
		$image_html = wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bfs-signup__image', 'alt' => '' ) );
	} else {
		// Try to find the fruit image by filename.
		global $wpdb;
		$attachment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name LIKE %s LIMIT 1",
				'%bewdley-fruit%'
			)
		);
		if ( $attachment ) {
			$image_html = wp_get_attachment_image( $attachment->ID, 'medium', false, array( 'class' => 'bfs-signup__image', 'alt' => '' ) );
		}
	}

	ob_start();
	?>
	<style>
	.bfs-signup{display:flex;align-items:center;gap:2rem;background:var(--bricks-color-primary,#1e3a2b);border-radius:12px;padding:2.5rem 2rem;max-width:900px;margin:0 auto;color:#fff;}
	.bfs-signup__img{flex:0 0 220px;text-align:center;}
	.bfs-signup__image{max-width:100%;height:auto;display:block;margin:0 auto;}
	.bfs-signup__body{flex:1;}
	.bfs-signup__body h2{font-size:1.6rem;margin:0 0 0.6rem;color:#fff;line-height:1.2;}
	.bfs-signup__body p{color:rgba(255,255,255,0.8);margin:0 0 1.4rem;font-size:0.95rem;line-height:1.5;}
	.bfs-signup__form .bfs-field{margin-bottom:0.75rem;}
	.bfs-signup__form input[type="text"],
	.bfs-signup__form input[type="email"]{width:100%;padding:0.75rem 1rem;border:none;border-radius:6px;font-size:0.95rem;color:#2c2c2c;background:#fff;box-sizing:border-box;}
	.bfs-signup__form input[type="text"]::placeholder,
	.bfs-signup__form input[type="email"]::placeholder{color:#888;}
	.bfs-signup__consent{display:flex;align-items:flex-start;gap:0.6rem;margin-bottom:1rem;}
	.bfs-signup__consent input[type="checkbox"]{margin-top:3px;flex-shrink:0;width:16px;height:16px;cursor:pointer;}
	.bfs-signup__consent label{font-size:0.82rem;color:rgba(255,255,255,0.75);line-height:1.4;cursor:pointer;}
	.bfs-signup__btn{display:inline-flex;align-items:center;gap:0.5rem;background:var(--bricks-color-accent,#e8a020);color:#fff;border:none;border-radius:6px;padding:0.8rem 1.6rem;font-size:0.95rem;font-weight:700;cursor:pointer;transition:opacity 0.2s;width:100%;justify-content:center;}
	.bfs-signup__btn:hover{opacity:0.88;}
	.bfs-signup__btn:disabled{opacity:0.6;cursor:default;}
	.bfs-signup__msg{margin-top:0.75rem;font-size:0.9rem;min-height:1.2em;}
	.bfs-signup__msg.success{color:#a5d6a7;}
	.bfs-signup__msg.error{color:#ef9a9a;}
	@media(max-width:640px){
		.bfs-signup{flex-direction:column;text-align:center;}
		.bfs-signup__img{flex:none;}
		.bfs-signup__consent{text-align:left;}
	}
	</style>
	<div class="bfs-signup">
		<?php if ( $image_html ) : ?>
		<div class="bfs-signup__img"><?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
		<div class="bfs-signup__body">
			<h2><?php echo esc_html( $atts['heading'] ); ?></h2>
			<p><?php echo esc_html( $atts['subtext'] ); ?></p>
			<form class="bfs-signup__form" id="bfs-signup-form" novalidate>
				<div class="bfs-field">
					<input type="text" name="name" placeholder="<?php esc_attr_e( 'Your Name *', 'bewdley-custom' ); ?>" required autocomplete="name" />
				</div>
				<div class="bfs-field">
					<input type="email" name="email" placeholder="<?php esc_attr_e( 'Your Email *', 'bewdley-custom' ); ?>" required autocomplete="email" />
				</div>
				<div class="bfs-signup__consent">
					<input type="checkbox" id="bfs-consent" name="consent" value="1" required />
					<label for="bfs-consent"><?php esc_html_e( 'I agree to receive emails about farm shop news, seasonal updates and special offers. I understand I can unsubscribe at any time.', 'bewdley-custom' ); ?></label>
				</div>
				<button type="submit" class="bfs-signup__btn">
					<i class="fas fa-thumbs-up" aria-hidden="true"></i>
					<span class="bfs-btn-text"><?php echo esc_html( $atts['button'] ); ?></span>
				</button>
			</form>
			<div class="bfs-signup__msg" id="bfs-signup-msg" role="status" aria-live="polite"></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );
// ---------------------------------------------------------------------------
// Fixed bottom newsletter bar shortcode [bewdley_bar]
// ---------------------------------------------------------------------------

/**
 * Renders a fixed bottom bar that slides up after 3 seconds.
 * Dismissed state is saved to sessionStorage so it won't reappear mid-session.
 * Usage: [bewdley_bar]
 */
add_shortcode( 'bewdley_bar', function () {
wp_enqueue_script(
'bewdley-signup-bar',
plugin_dir_url( __FILE__ ) . 'assets/signup-bar.js',
array( 'jquery' ),
'1.0.0',
true
);

wp_localize_script(
'bewdley-signup-bar',
'bewdleyBar',
array(
'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
'nonce'      => wp_create_nonce( 'bewdley_newsletter_nonce' ),
'successMsg' => __( "You're signed up � thank you!", 'bewdley-custom' ),
'emailError' => __( 'Please enter a valid email address.', 'bewdley-custom' ),
)
);

ob_start();
?>
<style>
#bfs-bar{position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#1e3a2b;color:#fff;padding:0.9rem 3rem 0.9rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:center;box-shadow:0 -2px 12px rgba(0,0,0,0.25);transform:translateY(100%);transition:transform 0.4s ease;}
#bfs-bar.bfs-bar--visible{transform:translateY(0);}
#bfs-bar p{margin:0;font-size:0.9rem;color:rgba(255,255,255,0.85);white-space:nowrap;}
#bfs-bar-form{display:flex;gap:0.5rem;align-items:center;}
#bfs-bar-form input[type="email"]{padding:0.55rem 0.9rem;border:none;border-radius:5px;font-size:0.9rem;color:#2c2c2c;width:220px;}
#bfs-bar-form button{padding:0.55rem 1.2rem;background:#e8a020;color:#fff;border:none;border-radius:5px;font-size:0.9rem;font-weight:700;cursor:pointer;transition:opacity 0.2s;white-space:nowrap;}
#bfs-bar-form button:hover{opacity:0.85;}
#bfs-bar-form button:disabled{opacity:0.6;cursor:default;}
#bfs-bar-msg{font-size:0.85rem;flex-basis:100%;text-align:center;min-height:1em;}
#bfs-bar-msg.success{color:#a5d6a7;}
#bfs-bar-msg.error{color:#ef9a9a;}
#bfs-bar-close{position:absolute;top:50%;right:1rem;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.6);font-size:1.3rem;line-height:1;cursor:pointer;padding:0.3rem;}
#bfs-bar-close:hover{color:#fff;}
@media(max-width:540px){
#bfs-bar{flex-direction:column;text-align:center;padding:1rem 2.5rem 1rem 1rem;}
#bfs-bar-form{flex-direction:column;width:100%;}
#bfs-bar-form input[type="email"],#bfs-bar-form button{width:100%;}
}
</style>
<div id="bfs-bar" role="complementary" aria-label="<?php esc_attr_e( 'Newsletter signup', 'bewdley-custom' ); ?>">
<p><?php esc_html_e( 'Get farm shop news &amp; offers:', 'bewdley-custom' ); ?></p>
<form id="bfs-bar-form" novalidate>
<input type="email" name="email" placeholder="<?php esc_attr_e( 'Your email address', 'bewdley-custom' ); ?>" required autocomplete="email" />
<button type="submit"><?php esc_html_e( 'Subscribe', 'bewdley-custom' ); ?></button>
</form>
<div id="bfs-bar-msg" role="status" aria-live="polite"></div>
<button id="bfs-bar-close" aria-label="<?php esc_attr_e( 'Dismiss', 'bewdley-custom' ); ?>">&#x2715;</button>
</div>
<?php
return ob_get_clean();
} );
