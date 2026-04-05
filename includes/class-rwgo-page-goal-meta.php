<?php
/**
 * Page-level Geo Optimise destination goal (post meta + meta box).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers meta, meta box UI, and stable IDs on save.
 */
class RWGO_Page_Goal_Meta {

	const NONCE_ACTION = 'rwgo_save_dest_goal';
	const NONCE_NAME   = '_rwgo_dest_goal_nonce';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta_keys' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_document_panel' ) );
		add_action( 'updated_post_meta', array( __CLASS__, 'sync_destination_ids_on_meta_change' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'sync_destination_ids_on_meta_change' ), 10, 4 );
	}

	/**
	 * Post types that have destination meta registered (for Gutenberg panel).
	 *
	 * @return list<string>
	 */
	public static function get_supported_post_types() {
		$types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);
		if ( ! is_array( $types ) ) {
			$types = array( 'post', 'page' );
		}
		return array_values( array_map( 'sanitize_key', $types ) );
	}

	/**
	 * When meta is saved via REST (block editor), fill stable goal/handler IDs.
	 *
	 * @param int    $meta_id Meta row ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function sync_destination_ids_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( RWGO_Defined_Goal_Service::META_DEST_ENABLED !== $meta_key ) {
			return;
		}
		if ( '1' !== (string) $meta_value && 'yes' !== (string) $meta_value ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		RWGO_Defined_Goal_Service::maybe_fill_destination_ids( $post_id );
	}

	/**
	 * Gutenberg: document sidebar panel for destination goals (same keys as meta box).
	 *
	 * @return void
	 */
	public static function enqueue_block_document_panel() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_script(
			'rwgo-page-goal-document',
			RWGO_URL . 'admin/js/rwgo-page-goal-document.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-core-data',
				'wp-i18n',
			),
			RWGO_VERSION,
			true
		);
		wp_set_script_translations( 'rwgo-page-goal-document', 'reactwoo-geo-optimise' );
		$help    = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::help_url( 'rwgo-help-builder-goals' ) : admin_url( 'admin.php?page=rwgo-help' );
		$support = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::developer_url( 'support' ) : admin_url( 'admin.php?page=rwgo-developer&rwgo_tab=support' );
		wp_localize_script(
			'rwgo-page-goal-document',
			'rwgoPageGoalDocument',
			array(
				'supportedPostTypes' => self::get_supported_post_types(),
				'helpUrl'            => $help,
				'supportUrl'         => $support,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_meta_keys() {
		$types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);
		if ( ! is_array( $types ) ) {
			$types = array( 'post', 'page' );
		}
		$keys = array(
			RWGO_Defined_Goal_Service::META_DEST_ENABLED    => array( 'type' => 'string' ),
			RWGO_Defined_Goal_Service::META_DEST_LABEL      => array( 'type' => 'string' ),
			RWGO_Defined_Goal_Service::META_DEST_TYPE       => array( 'type' => 'string' ),
			RWGO_Defined_Goal_Service::META_DEST_GOAL_ID    => array( 'type' => 'string' ),
			RWGO_Defined_Goal_Service::META_DEST_HANDLER_ID => array( 'type' => 'string' ),
		);
		foreach ( $types as $pt ) {
			$pt = sanitize_key( (string) $pt );
			if ( '' === $pt ) {
				continue;
			}
			foreach ( $keys as $meta_key => $schema ) {
				register_post_meta(
					$pt,
					$meta_key,
					array(
						'type'              => $schema['type'],
						'single'            => true,
						'show_in_rest'      => true,
						'auth_callback'     => static function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => static function ( $v ) {
							return is_string( $v ) ? sanitize_text_field( $v ) : '';
						},
					)
				);
			}
		}
	}

	/**
	 * @param string $post_type Post type.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function add_meta_box( $post_type, $post ) {
		unset( $post );
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$pto = get_post_type_object( $post_type );
		if ( ! $pto || ! isset( $pto->public ) || ! $pto->public ) {
			return;
		}
		add_meta_box(
			'rwgo_page_destination_goal',
			__( 'Geo Optimise — destination goal', 'reactwoo-geo-optimise' ),
			array( __CLASS__, 'render_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$en    = get_post_meta( $post->ID, RWGO_Defined_Goal_Service::META_DEST_ENABLED, true );
		$label = (string) get_post_meta( $post->ID, RWGO_Defined_Goal_Service::META_DEST_LABEL, true );
		$type  = (string) get_post_meta( $post->ID, RWGO_Defined_Goal_Service::META_DEST_TYPE, true );
		if ( '' === $type ) {
			$type = 'page_visit';
		}
		$enabled = ( '1' === (string) $en || 'yes' === (string) $en );
		?>
		<p>
			<label>
				<input type="checkbox" name="rwgo_dest_goal_enabled" value="1" <?php checked( $enabled ); ?> />
				<?php esc_html_e( 'Use this page as a Geo Optimise goal destination', 'reactwoo-geo-optimise' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Turn this on if visiting this page should count as a conversion in Geo Optimise tests.', 'reactwoo-geo-optimise' ); ?></p>
		<p>
			<label for="rwgo_dest_goal_label"><?php esc_html_e( 'Goal label', 'reactwoo-geo-optimise' ); ?></label>
			<input type="text" class="widefat" id="rwgo_dest_goal_label" name="rwgo_dest_goal_label" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'e.g. Thank you page', 'reactwoo-geo-optimise' ); ?>" />
		</p>
		<p>
			<label for="rwgo_dest_goal_type"><?php esc_html_e( 'Goal type', 'reactwoo-geo-optimise' ); ?></label>
			<select class="widefat" id="rwgo_dest_goal_type" name="rwgo_dest_goal_type">
				<option value="page_visit" <?php selected( $type, 'page_visit' ); ?>><?php esc_html_e( 'Page visit', 'reactwoo-geo-optimise' ); ?></option>
				<option value="thank_you" <?php selected( $type, 'thank_you' ); ?>><?php esc_html_e( 'Thank-you page', 'reactwoo-geo-optimise' ); ?></option>
				<option value="lead_confirmation" <?php selected( $type, 'lead_confirmation' ); ?>><?php esc_html_e( 'Lead confirmation', 'reactwoo-geo-optimise' ); ?></option>
				<option value="checkout_success" <?php selected( $type, 'checkout_success' ); ?>><?php esc_html_e( 'Checkout success', 'reactwoo-geo-optimise' ); ?></option>
				<option value="custom_destination" <?php selected( $type, 'custom_destination' ); ?>><?php esc_html_e( 'Custom destination', 'reactwoo-geo-optimise' ); ?></option>
			</select>
		</p>
		<?php
		if ( $enabled ) {
			echo '<p class="rwgo-goal-status" style="color:#00a32a;font-size:12px;">' . esc_html__( 'Destination goal set', 'reactwoo-geo-optimise' ) . '</p>';
		}
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$en = isset( $_POST['rwgo_dest_goal_enabled'] ) && '1' === (string) wp_unslash( $_POST['rwgo_dest_goal_enabled'] );
		update_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_ENABLED, $en ? '1' : '0' );
		$label = isset( $_POST['rwgo_dest_goal_label'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_dest_goal_label'] ) ) : '';
		update_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_LABEL, $label );
		$type = isset( $_POST['rwgo_dest_goal_type'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_dest_goal_type'] ) ) : 'page_visit';
		$ok   = array( 'page_visit', 'thank_you', 'lead_confirmation', 'checkout_success', 'custom_destination' );
		if ( ! in_array( $type, $ok, true ) ) {
			$type = 'page_visit';
		}
		update_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_TYPE, $type );
		if ( $en ) {
			RWGO_Defined_Goal_Service::maybe_fill_destination_ids( $post_id );
		}
	}
}
