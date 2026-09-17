<?php
/**
 * Records List Table Class.
 *
 * Implements native WordPress WP_List_Table for unified, high-performance
 * management of Tenders and Purchase Orders.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/admin
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load core WP_List_Table if not already present.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Records List Table.
 *
 * Handles server-side pagination, multi-criteria filtering, multi-field search,
 * column sorting, capability-gated bulk actions, and attachment indicators.
 *
 * @since 1.0.0
 */
class TPM_Records_List_Table extends WP_List_Table {

	/**
	 * Preset query filters (e.g. For dedicated Tenders, POs, or Archived screens).
	 *
	 * @var array<string, mixed>
	 */
	protected array $preset_filters = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $args Configuration options and preset filters.
	 */
	public function __construct( array $args = array() ) {
		parent::__construct(
			array(
				'singular' => 'record',
				'plural'   => 'records',
				'ajax'     => false,
				'screen'   => $args['screen'] ?? 'tpm-records',
			)
		);

		$this->preset_filters = $args['preset_filters'] ?? array();
	}

	/**
	 * Define table columns.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Column slugs and labels.
	 */
	public function get_columns(): array {
		return array(
			'cb'                 => '<input type="checkbox" />',
			'tender_number'      => __( 'Record Number', 'tender-po-management' ),
			'record_type'        => __( 'Type', 'tender-po-management' ),
			'status'             => __( 'Status', 'tender-po-management' ),
			'customer'           => __( 'Customer', 'tender-po-management' ),
			'item_description'   => __( 'Item Description', 'tender-po-management' ),
			'customer_po_number' => __( 'Customer PO #', 'tender-po-management' ),
			'purchase_price'     => __( 'Purchase Price', 'tender-po-management' ),
			'sell_price'         => __( 'Sell Price', 'tender-po-management' ),
			'net_profit'         => __( 'Net Profit', 'tender-po-management' ),
			'due_date'           => __( 'Due Date', 'tender-po-management' ),
			'attachments_count'  => __( 'Attachments', 'tender-po-management' ),
			'created_at'         => __( 'Created Date', 'tender-po-management' ),
			'actions'            => __( 'Actions', 'tender-po-management' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @since 1.0.0
	 * @return array<string, array{0: string, 1: bool}> Sortable column mapping.
	 */
	protected function get_sortable_columns(): array {
		return array(
			'tender_number'      => array( 'tender_number', false ),
			'record_type'        => array( 'record_type', false ),
			'customer'           => array( 'customer', false ),
			'status'             => array( 'status', false ),
			'customer_po_number' => array( 'customer_po_number', false ),
			'purchase_price'     => array( 'purchase_price', false ),
			'sell_price'         => array( 'sell_price', false ),
			'net_profit'         => array( 'net_profit', false ),
			'due_date'           => array( 'due_date', false ),
			'created_at'         => array( 'created_at', true ), // default sorted DESC
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Available bulk actions based on capabilities.
	 */
	protected function get_bulk_actions(): array {
		$actions = array();

		if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
			$current_view = sanitize_key( $_REQUEST['view'] ?? ( $this->preset_filters['view'] ?? 'all' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'archived' === $current_view ) {
				$actions['restore'] = __( 'Restore from Archive', 'tender-po-management' );
			} else {
				$actions['archive'] = __( 'Archive', 'tender-po-management' );
			}
		}

		if ( current_user_can( 'delete_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
			$actions['delete'] = __( 'Delete Permanently', 'tender-po-management' );
		}

		return $actions;
	}

	/**
	 * Generate Quick Filter Views navigation.
	 *
	 * @since 1.0.0
	 * @return array<string, string> View tabs with item counts.
	 */
	protected function get_views(): array {
		$counts       = TPM_Record_Service::get_view_counts();
		$current_view = sanitize_key( $_REQUEST['view'] ?? ( $this->preset_filters['view'] ?? 'all' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base_url     = remove_query_arg( array( 'paged', 's', 'status', 'customer_id', 'partner_company_id', 'date_field', 'date_from', 'date_to' ) );

		$record_type_preset = $this->preset_filters['record_type'] ?? '';

		if ( 'tender' === $record_type_preset ) {
			$view_definitions = array(
				'all'       => array(
					'label' => __( 'All Tenders', 'tender-po-management' ),
					'count' => $counts['tenders'] ?? 0,
				),
				'submitted' => array(
					'label' => __( 'Submitted', 'tender-po-management' ),
					'count' => $counts['submitted'] ?? 0,
				),
				'complete'  => array(
					'label' => __( 'Complete', 'tender-po-management' ),
					'count' => $counts['complete'] ?? 0,
				),
				'pending'   => array(
					'label' => __( 'Pending', 'tender-po-management' ),
					'count' => $counts['pending'] ?? 0,
				),
			);
		} elseif ( 'po' === $record_type_preset ) {
			$view_definitions = array(
				'all'       => array(
					'label' => __( 'All Purchase Orders', 'tender-po-management' ),
					'count' => $counts['pos'] ?? 0,
				),
				'submitted' => array(
					'label' => __( 'Submitted', 'tender-po-management' ),
					'count' => $counts['submitted'] ?? 0,
				),
				'complete'  => array(
					'label' => __( 'Complete', 'tender-po-management' ),
					'count' => $counts['complete'] ?? 0,
				),
				'pending'   => array(
					'label' => __( 'Pending', 'tender-po-management' ),
					'count' => $counts['pending'] ?? 0,
				),
			);
		} else {
			$view_definitions = array(
				'all'       => array(
					'label' => __( 'All Records', 'tender-po-management' ),
					'count' => $counts['all'] ?? 0,
				),
				'tenders'   => array(
					'label' => __( 'All Tenders', 'tender-po-management' ),
					'count' => $counts['tenders'] ?? 0,
				),
				'pos'       => array(
					'label' => __( 'All Purchase Orders', 'tender-po-management' ),
					'count' => $counts['pos'] ?? 0,
				),
				'archived'  => array(
					'label' => __( 'Archived', 'tender-po-management' ),
					'count' => $counts['archived'] ?? 0,
				),
				'submitted' => array(
					'label' => __( 'Submitted', 'tender-po-management' ),
					'count' => $counts['submitted'] ?? 0,
				),
				'complete'  => array(
					'label' => __( 'Complete', 'tender-po-management' ),
					'count' => $counts['complete'] ?? 0,
				),
				'pending'   => array(
					'label' => __( 'Pending', 'tender-po-management' ),
					'count' => $counts['pending'] ?? 0,
				),
			);
		}

		$views = array();
		foreach ( $view_definitions as $slug => $def ) {
			$count = $def['count'];
			$label = $def['label'];
			$url   = add_query_arg( 'view', $slug, $base_url );
			$class = ( $current_view === $slug ) ? 'current' : '';

			$views[ $slug ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label ),
				(int) $count
			);
		}

		return $views;
	}

	/**
	 * Extra table navigation controls (Status, Type, Customer, Date filters).
	 *
	 * @since 1.0.0
	 * @param string $which Table nav position ('top' or 'bottom').
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		global $wpdb;

		// Fetch statuses for dropdown filter
		$statuses = $wpdb->get_results(
			"SELECT status_slug, status_name FROM {$wpdb->prefix}tpm_statuses WHERE is_active = 1 ORDER BY sort_order ASC"
		);

		// Fetch customers for dropdown filter
		$customers = $wpdb->get_results(
			"SELECT id, name, company_name FROM {$wpdb->prefix}tpm_customers WHERE status = 'active' ORDER BY name ASC LIMIT 100"
		);

		// Fetch partners for dropdown filter
		$partners = $wpdb->get_results(
			"SELECT id, company_name FROM {$wpdb->prefix}tpm_partner_companies WHERE status = 'active' ORDER BY company_name ASC LIMIT 100"
		);

		$sel_status     = sanitize_text_field( $_REQUEST['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sel_type       = sanitize_key( $_REQUEST['record_type'] ?? ( $this->preset_filters['record_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sel_customer   = absint( $_REQUEST['customer_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sel_partner    = absint( $_REQUEST['partner_company_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sel_date_field = sanitize_key( $_REQUEST['date_field'] ?? 'tender_due_date' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sel_date_from  = sanitize_text_field( $_REQUEST['date_from'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sel_date_to    = sanitize_text_field( $_REQUEST['date_to'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions tpm-filter-toolbar">

			<!-- Status Filter -->
			<select name="status" id="filter-by-status" class="tpm-select tpm-select-sm">
				<option value=""><?php esc_html_e( 'All Statuses', 'tender-po-management' ); ?></option>
				<?php if ( ! empty( $statuses ) ) : ?>
					<?php foreach ( $statuses as $st ) : ?>
						<option value="<?php echo esc_attr( $st->status_slug ); ?>" <?php selected( $sel_status, $st->status_slug ); ?>>
							<?php echo esc_html( $st->status_name ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<!-- Record Type Filter (Hidden if pre-filtered) -->
			<?php if ( empty( $this->preset_filters['record_type'] ) ) : ?>
				<select name="record_type" id="filter-by-type" class="tpm-select tpm-select-sm">
					<option value=""><?php esc_html_e( 'All Types', 'tender-po-management' ); ?></option>
					<option value="tender" <?php selected( $sel_type, 'tender' ); ?>><?php esc_html_e( 'Tender', 'tender-po-management' ); ?></option>
					<option value="po" <?php selected( $sel_type, 'po' ); ?>><?php esc_html_e( 'Purchase Order', 'tender-po-management' ); ?></option>
				</select>
			<?php endif; ?>

			<!-- Customer Filter -->
			<select name="customer_id" id="filter-by-customer" class="tpm-select tpm-select-sm">
				<option value="0"><?php esc_html_e( 'All Customers', 'tender-po-management' ); ?></option>
				<?php if ( ! empty( $customers ) ) : ?>
					<?php foreach ( $customers as $cust ) : ?>
						<option value="<?php echo esc_attr( $cust->id ); ?>" <?php selected( $sel_customer, $cust->id ); ?>>
							<?php echo esc_html( ! empty( $cust->company_name ) ? $cust->company_name : $cust->name ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<!-- Partner Company Filter -->
			<select name="partner_company_id" id="filter-by-partner" class="tpm-select tpm-select-sm">
				<option value="0"><?php esc_html_e( 'All Partners', 'tender-po-management' ); ?></option>
				<?php if ( ! empty( $partners ) ) : ?>
					<?php foreach ( $partners as $part ) : ?>
						<option value="<?php echo esc_attr( $part->id ); ?>" <?php selected( $sel_partner, $part->id ); ?>>
							<?php echo esc_html( $part->company_name ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<!-- Date Field Picker -->
			<select name="date_field" id="filter-by-date-field" class="tpm-select tpm-select-sm">
				<option value="tender_due_date" <?php selected( $sel_date_field, 'tender_due_date' ); ?>><?php esc_html_e( 'Tender Due Date', 'tender-po-management' ); ?></option>
				<option value="tender_submission_date" <?php selected( $sel_date_field, 'tender_submission_date' ); ?>><?php esc_html_e( 'Submission Date', 'tender-po-management' ); ?></option>
				<option value="quote_expiration_date" <?php selected( $sel_date_field, 'quote_expiration_date' ); ?>><?php esc_html_e( 'Quote Expiration', 'tender-po-management' ); ?></option>
				<option value="created_at" <?php selected( $sel_date_field, 'created_at' ); ?>><?php esc_html_e( 'Created Date', 'tender-po-management' ); ?></option>
			</select>

			<!-- Date Range Inputs -->
			<input type="date" name="date_from" value="<?php echo esc_attr( $sel_date_from ); ?>" class="tpm-input tpm-input-sm" title="<?php esc_attr_e( 'From Date', 'tender-po-management' ); ?>" placeholder="YYYY-MM-DD">
			<span class="tpm-filter-sep">&ndash;</span>
			<input type="date" name="date_to" value="<?php echo esc_attr( $sel_date_to ); ?>" class="tpm-input tpm-input-sm" title="<?php esc_attr_e( 'To Date', 'tender-po-management' ); ?>" placeholder="YYYY-MM-DD">

			<button type="submit" class="button" id="post-query-submit"><?php esc_html_e( 'Filter', 'tender-po-management' ); ?></button>

			<?php if ( ! empty( $sel_status ) || ! empty( $sel_type ) || ! empty( $sel_customer ) || ! empty( $sel_partner ) || ! empty( $sel_date_from ) || ! empty( $sel_date_to ) || ! empty( $_REQUEST['s'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'status', 'record_type', 'customer_id', 'partner_company_id', 'date_field', 'date_from', 'date_to', 's', 'paged' ) ) ); ?>" class="button tpm-btn-reset" title="<?php esc_attr_e( 'Reset all filters', 'tender-po-management' ); ?>">
					<?php esc_html_e( 'Reset', 'tender-po-management' ); ?>
				</a>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Prepare items for list table display with server-side pagination.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items(): void {
		// Initialize column headers
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination configuration
		$per_page     = class_exists( 'TPM_Settings_Service' ) ? max( 1, absint( TPM_Settings_Service::get_setting( 'records_per_page', 20 ) ) ) : 20;
		$current_page = $this->get_pagenum();

		// Parse search and filter parameters
		$query_args = array(
			'search'             => sanitize_text_field( $_REQUEST['s'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'             => sanitize_text_field( $_REQUEST['status'] ?? ( $this->preset_filters['status'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'record_type'        => sanitize_key( $_REQUEST['record_type'] ?? ( $this->preset_filters['record_type'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'customer_id'        => absint( $_REQUEST['customer_id'] ?? ( $this->preset_filters['customer_id'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'partner_company_id' => absint( $_REQUEST['partner_company_id'] ?? ( $this->preset_filters['partner_company_id'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'view'               => sanitize_key( $_REQUEST['view'] ?? ( $this->preset_filters['view'] ?? 'all' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_field'         => sanitize_key( $_REQUEST['date_field'] ?? 'tender_due_date' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_from'          => sanitize_text_field( $_REQUEST['date_from'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_to'            => sanitize_text_field( $_REQUEST['date_to'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'orderby'            => sanitize_key( $_REQUEST['orderby'] ?? 'created_at' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'              => sanitize_key( $_REQUEST['order'] ?? 'desc' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'per_page'           => $per_page,
			'paged'              => $current_page,
		);

		// Handle explicit archived preset
		if ( isset( $this->preset_filters['is_archived'] ) ) {
			$query_args['is_archived'] = (int) $this->preset_filters['is_archived'];
		}

		// Query total items and dataset slice
		$total_items = TPM_Record_Service::get_records_count( $query_args );
		$this->items = TPM_Record_Service::get_records( $query_args );

		// Set pagination args for WP_List_Table
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Display the list table with responsive table-card container.
	 *
	 * Keeps top navigation/filters and bottom pagination outside the scroll container,
	 * wrapping only the table itself so it spans full width and scrolls smoothly on narrow screens.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display() {
		$singular = $this->_args['singular'];

		$this->display_tablenav( 'top' );

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
		<div class="tpm-table-card">
			<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
				<thead>
				<tr>
					<?php $this->print_column_headers(); ?>
				</tr>
				</thead>

				<tbody id="the-list"<?php echo $singular ? ' data-wp-lists="list:' . esc_attr( $singular ) . '"' : ''; ?>>
					<?php $this->display_rows_or_placeholder(); ?>
				</tbody>

				<tfoot>
				<tr>
					<?php $this->print_column_headers( false ); ?>
				</tr>
				</tfoot>
			</table>
		</div>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Checkbox column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="record_ids[]" value="%d" />',
			(int) $item->id
		);
	}

	/**
	 * Primary Record Number column renderer with action links.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Column HTML.
	 */
	protected function column_tender_number( $item ): string {
		$record_id    = (int) $item->id;
		$number       = ! empty( $item->tender_number ) ? $item->tender_number : ( ! empty( $item->customer_po_number ) ? $item->customer_po_number : sprintf( __( 'Record #%d', 'tender-po-management' ), $record_id ) );
		$details_url  = admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id );
		$is_archived  = ! empty( $item->is_archived );

		// Row actions
		$actions = array();

		// View Action (Everyone with view_tpm_records capability)
		$actions['view'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $details_url ),
			esc_html__( 'View', 'tender-po-management' )
		);

		// Edit Action (Requires edit_tpm_records)
		if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=tpm-add-record&action=edit&id=' . $record_id ) ),
				esc_html__( 'Edit', 'tender-po-management' )
			);
		}

		// Archive / Restore Action (Requires edit_tpm_records)
		if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
			if ( $is_archived ) {
				$restore_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_quick_restore&record_id=' . $record_id ),
					'tpm_quick_restore_' . $record_id
				);
				$actions['restore'] = sprintf(
					'<a href="%s" class="tpm-action-restore">%s</a>',
					esc_url( $restore_url ),
					esc_html__( 'Restore', 'tender-po-management' )
				);
			} else {
				$archive_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_quick_archive&record_id=' . $record_id ),
					'tpm_quick_archive_' . $record_id
				);
				$actions['archive'] = sprintf(
					'<a href="%s" class="tpm-action-archive">%s</a>',
					esc_url( $archive_url ),
					esc_html__( 'Archive', 'tender-po-management' )
				);
			}
		}

		// Delete Action (Requires delete_tpm_records)
		if ( current_user_can( 'delete_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
			if ( $is_archived ) {
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_permanent_delete&record_id=' . $record_id ),
					'tpm_permanent_delete_' . $record_id,
					'tpm_nonce'
				);
				$actions['delete'] = sprintf(
					'<a href="%s" class="tpm-action-delete" style="color: #dc2626;" data-tpm-confirm="%s">%s</a>',
					esc_url( $delete_url ),
					esc_attr__( 'Are you sure you want to permanently delete this record? This action cannot be undone and will delete all related payments and attachments.', 'tender-po-management' ),
					esc_html__( 'Delete Permanently', 'tender-po-management' )
				);
			} else {
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_quick_delete&record_id=' . $record_id ),
					'tpm_quick_delete_' . $record_id
				);
				$actions['delete'] = sprintf(
					'<a href="%s" class="tpm-action-delete" data-tpm-confirm="%s">%s</a>',
					esc_url( $delete_url ),
					esc_attr__( 'Are you sure you want to permanently delete this record? This action cannot be undone.', 'tender-po-management' ),
					esc_html__( 'Delete', 'tender-po-management' )
				);
			}
		}

		$title_html = sprintf(
			'<strong><a href="%s" class="row-title">%s</a></strong>',
			esc_url( $details_url ),
			esc_html( $number )
		);

		return $title_html . $this->row_actions( $actions );
	}

	/**
	 * Record Type column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string HTML badge.
	 */
	protected function column_record_type( $item ): string {
		if ( 'po' === $item->record_type ) {
			return '<span class="tpm-badge tpm-badge-type-po">' . esc_html__( 'Purchase Order', 'tender-po-management' ) . '</span>';
		}
		return '<span class="tpm-badge tpm-badge-type-tender">' . esc_html__( 'Tender', 'tender-po-management' ) . '</span>';
	}

	/**
	 * Status column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string HTML status badge.
	 */
	protected function column_status( $item ): string {
		$label = ! empty( $item->status_name ) ? $item->status_name : ucfirst( str_replace( array( 't-', 'po-', '-' ), array( '', '', ' ' ), $item->status ) );
		$color = ! empty( $item->status_color ) ? $item->status_color : '#64748b';

		return sprintf(
			'<span class="tpm-badge" style="background-color: %1$s20; color: %1$s; border: 1px solid %1$s60;">%2$s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/**
	 * Customer column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Customer name.
	 */
	protected function column_customer( $item ): string {
		if ( ! empty( $item->customer_name ) ) {
			return '<strong>' . esc_html( $item->customer_name ) . '</strong>';
		}
		return '<span class="tpm-text-muted">&mdash;</span>';
	}

	/**
	 * Item Description column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Truncated preview with tooltip.
	 */
	protected function column_item_description( $item ): string {
		if ( empty( $item->item_description ) ) {
			return '<span class="tpm-text-muted">&mdash;</span>';
		}
		$raw_desc  = wp_strip_all_tags( $item->item_description );
		$truncated = wp_trim_words( $raw_desc, 7, '...' );
		return sprintf(
			'<span class="tpm-desc-preview" title="%s">%s</span>',
			esc_attr( $raw_desc ),
			esc_html( $truncated )
		);
	}

	/**
	 * Customer PO Number column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string PO number or em-dash.
	 */
	protected function column_customer_po_number( $item ): string {
		if ( ! empty( $item->customer_po_number ) ) {
			return '<code>' . esc_html( $item->customer_po_number ) . '</code>';
		}
		return '<span class="tpm-text-muted">&mdash;</span>';
	}

	/**
	 * Purchase Price column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Formatted amount.
	 */
	protected function column_purchase_price( $item ): string {
		$val    = (float) ( $item->purchase_price ?? 0.00 );
		$symbol = TPM_Settings_Service::get_currency_symbol();
		return $symbol . number_format( $val, 2 );
	}

	/**
	 * Sell Price column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Formatted amount.
	 */
	protected function column_sell_price( $item ): string {
		$val    = (float) ( $item->sell_price ?? 0.00 );
		$symbol = TPM_Settings_Service::get_currency_symbol();
		return '<strong>' . $symbol . number_format( $val, 2 ) . '</strong>';
	}

	/**
	 * Net Profit column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Formatted profit with dynamic positive/negative style.
	 */
	protected function column_net_profit( $item ): string {
		$sell     = (float) ( $item->sell_price ?? 0.00 );
		$purchase = (float) ( $item->purchase_price ?? 0.00 );
		$expenses = (float) ( $item->expenses ?? 0.00 );
		$profit   = ( $sell - $purchase ) - $expenses;
		$symbol   = TPM_Settings_Service::get_currency_symbol();

		$class = ( $profit >= 0 ) ? 'tpm-profit-positive' : 'tpm-profit-negative';
		$sign  = ( $profit > 0 ) ? '+' : '';

		return sprintf(
			'<span class="%s"><strong>%s%s%s</strong></span>',
			esc_attr( $class ),
			esc_html( $sign ),
			esc_html( $symbol ),
			esc_html( number_format( $profit, 2 ) )
		);
	}

	/**
	 * Due Date column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Formatted date or em-dash.
	 */
	protected function column_due_date( $item ): string {
		$date = ! empty( $item->tender_due_date ) ? $item->tender_due_date : ( ! empty( $item->payment_due_date ) ? $item->payment_due_date : null );
		if ( $date ) {
			return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
		}
		return '<span class="tpm-text-muted">&mdash;</span>';
	}

	/**
	 * Attachments Count column renderer.
	 *
	 * Displays paperclip indicator linking directly to the record's attachments tab.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Indicator HTML.
	 */
	protected function column_attachments_count( $item ): string {
		$count = (int) ( $item->attachments_count ?? 0 );
		$url   = admin_url( 'admin.php?page=tpm-record-details&record_id=' . (int) $item->id . '#attachments' );

		if ( $count > 0 ) {
			return sprintf(
				'<a href="%s" class="tpm-att-link" title="%s"><span class="tpm-att-clip">&#128206;</span> <span class="tpm-att-counter-badge">%d</span></a>',
				esc_url( $url ),
				esc_attr( sprintf( _n( '%d attachment', '%d attachments', $count, 'tender-po-management' ), $count ) ),
				$count
			);
		}

		return '<span class="tpm-text-muted">&mdash;</span>';
	}

	/**
	 * Created Date column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string Formatted created date.
	 */
	protected function column_created_at( $item ): string {
		if ( ! empty( $item->created_at ) ) {
			return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) );
		}
		return '<span class="tpm-text-muted">&mdash;</span>';
	}

	/**
	 * Quick Actions column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Record row object.
	 * @return string HTML button group.
	 */
	protected function column_actions( $item ): string {
		$record_id   = (int) $item->id;
		$details_url = admin_url( 'admin.php?page=tpm-record-details&record_id=' . $record_id );
		$is_archived = ! empty( $item->is_archived );

		$buttons = array();

		// 1. View button (always present)
		$buttons[] = sprintf(
			'<a href="%s" class="button button-small" title="%s">%s</a>',
			esc_url( $details_url ),
			esc_attr__( 'View details and documents', 'tender-po-management' ),
			esc_html__( 'View', 'tender-po-management' )
		);

		// 2. If archived, provide Restore & Delete Permanently quick action buttons
		if ( $is_archived ) {
			if ( current_user_can( 'edit_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
				$restore_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_quick_restore&record_id=' . $record_id ),
					'tpm_quick_restore_' . $record_id
				);
				$buttons[] = sprintf(
					'<a href="%s" class="button button-small tpm-btn-restore" title="%s">%s</a>',
					esc_url( $restore_url ),
					esc_attr__( 'Restore to active records', 'tender-po-management' ),
					esc_html__( 'Restore', 'tender-po-management' )
				);
			}

			if ( current_user_can( 'delete_tpm_records' ) || current_user_can( 'manage_tpm_records' ) ) {
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_permanent_delete&record_id=' . $record_id ),
					'tpm_permanent_delete_' . $record_id,
					'tpm_nonce'
				);
				$buttons[] = sprintf(
					'<a href="%s" class="button button-small tpm-btn-danger" style="color: #dc2626;" data-tpm-confirm="%s" title="%s">%s</a>',
					esc_url( $delete_url ),
					esc_attr__( 'Are you sure you want to permanently delete this record? This action cannot be undone and will delete all related payments and attachments.', 'tender-po-management' ),
					esc_attr__( 'Permanently delete record', 'tender-po-management' ),
					esc_html__( 'Delete Permanently', 'tender-po-management' )
				);
			}
		}

		return '<div class="tpm-row-btn-group">' . implode( ' ', $buttons ) . '</div>';
	}

	/**
	 * Default column fallback.
	 *
	 * @since 1.0.0
	 * @param object $item        Record row object.
	 * @param string $column_name Name of the column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
	}
}
