<?php
/**
 * Admin: Customers List Table
 *
 * Extends WP_List_Table to render a full-featured customer management table.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/includes/admin
 * @since      1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * TPM_Customers_List_Table Class.
 *
 * @since 1.0.0
 */
class TPM_Customers_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'customer',
				'plural'   => 'customers',
				'ajax'     => false,
				'screen'   => 'tpm-customers',
			)
		);
	}

	/**
	 * Define table columns.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Column definitions.
	 */
	public function get_columns(): array {
		return array(
			'cb'            => '<input type="checkbox" />',
			'name'          => __( 'Customer Name', 'tender-po-management' ),
			'company_name'  => __( 'Company', 'tender-po-management' ),
			'email'         => __( 'Email', 'tender-po-management' ),
			'phone'         => __( 'Phone', 'tender-po-management' ),
			'total_records' => __( 'Total Records', 'tender-po-management' ),
			'status'        => __( 'Status', 'tender-po-management' ),
			'created_at'    => __( 'Created Date', 'tender-po-management' ),
			'actions'       => __( 'Actions', 'tender-po-management' ),
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
			'name'          => array( 'name', false ),
			'company_name'  => array( 'company_name', false ),
			'email'         => array( 'email', false ),
			'total_records' => array( 'total_records', false ),
			'created_at'    => array( 'created_at', true ), // default sorted DESC
		);
	}

	/**
	 * Define filter views for customer status.
	 *
	 * @since 1.0.0
	 * @return array<string, string> HTML list of view links.
	 */
	protected function get_views(): array {
		$counts       = TPM_Customer_Service::get_view_counts();
		$current_view = sanitize_key( $_REQUEST['view'] ?? 'all' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base_url     = admin_url( 'admin.php?page=tpm-customers' );

		$views = array(
			'all' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $base_url ),
				( 'all' === $current_view || empty( $current_view ) ) ? ' class="current"' : '',
				esc_html__( 'All Customers', 'tender-po-management' ),
				$counts['all']
			),
			'active' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'view', 'active', $base_url ) ),
				( 'active' === $current_view ) ? ' class="current"' : '',
				esc_html__( 'Active', 'tender-po-management' ),
				$counts['active']
			),
			'inactive' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'view', 'inactive', $base_url ) ),
				( 'inactive' === $current_view ) ? ' class="current"' : '',
				esc_html__( 'Inactive', 'tender-po-management' ),
				$counts['inactive']
			),
		);

		return $views;
	}

	/**
	 * Default column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item        Customer item object.
	 * @param string $column_name Column key.
	 * @return string Rendered HTML.
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'company_name':
				return ! empty( $item->company_name ) ? esc_html( $item->company_name ) : '&mdash;';

			case 'email':
				if ( ! empty( $item->email ) ) {
					return sprintf(
						'<a href="mailto:%1$s" class="tpm-link-email"><span class="dashicons dashicons-email"></span> %1$s</a>',
						esc_attr( $item->email )
					);
				}
				return '&mdash;';

			case 'phone':
				if ( ! empty( $item->phone ) ) {
					return sprintf(
						'<a href="tel:%1$s" class="tpm-link-phone"><span class="dashicons dashicons-phone"></span> %1$s</a>',
						esc_attr( $item->phone )
					);
				}
				return '&mdash;';

			case 'total_records':
				$count = (int) ( $item->total_records ?? 0 );
				$badge_class = ( $count > 0 ) ? 'tpm-badge-active' : 'tpm-badge-draft';
				$records_url = admin_url( 'admin.php?page=tpm-records&customer_id=' . $item->id );
				return sprintf(
					'<a href="%1$s" class="tpm-badge %2$s" title="%3$s">%4$d %5$s</a>',
					esc_url( $records_url ),
					esc_attr( $badge_class ),
					esc_attr__( 'View customer records', 'tender-po-management' ),
					$count,
					esc_html( _n( 'Record', 'Records', $count, 'tender-po-management' ) )
				);

			case 'status':
				$is_active = ( 'active' === $item->status );
				$status_label = $is_active ? __( 'Active', 'tender-po-management' ) : __( 'Inactive', 'tender-po-management' );
				$badge_class  = $is_active ? 'tpm-badge-active' : 'tpm-badge-draft';
				return sprintf(
					'<span class="tpm-badge %1$s">%2$s</span>',
					esc_attr( $badge_class ),
					esc_html( $status_label )
				);

			case 'created_at':
				return ! empty( $item->created_at )
					? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) )
					: '&mdash;';

			case 'actions':
				$view_url = admin_url( 'admin.php?page=tpm-customers&action=view&id=' . $item->id );
				$edit_url = admin_url( 'admin.php?page=tpm-customers&action=edit&id=' . $item->id );
				return sprintf(
					'<div class="tpm-row-btn-group">
						<a href="%1$s" class="button button-small" title="%2$s"><span class="dashicons dashicons-visibility"></span></a>
						<a href="%3$s" class="button button-small" title="%4$s"><span class="dashicons dashicons-edit"></span></a>
					</div>',
					esc_url( $view_url ),
					esc_attr__( 'View Details', 'tender-po-management' ),
					esc_url( $edit_url ),
					esc_attr__( 'Edit Customer', 'tender-po-management' )
				);

			default:
				return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @since 1.0.0
	 * @param object $item Customer item.
	 * @return string HTML checkbox.
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="customer_ids[]" value="%d" />',
			(int) $item->id
		);
	}

	/**
	 * Customer Name column with row actions.
	 *
	 * @since 1.0.0
	 * @param object $item Customer item.
	 * @return string HTML output.
	 */
	protected function column_name( $item ): string {
		$customer_id = (int) $item->id;
		$view_url    = admin_url( 'admin.php?page=tpm-customers&action=view&id=' . $customer_id );
		$edit_url    = admin_url( 'admin.php?page=tpm-customers&action=edit&id=' . $customer_id );
		$count       = (int) ( $item->total_records ?? 0 );

		$title = sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>',
			esc_url( $view_url ),
			esc_html( $item->name )
		);

		$actions = array();
		$actions['view'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $view_url ),
			esc_html__( 'View', 'tender-po-management' )
		);

		if ( current_user_can( 'manage_tpm_customers' ) || current_user_can( 'manage_tpm_records' ) ) {
			$actions['edit'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'tender-po-management' )
			);

			// Deactivate / Activate action
			if ( 'active' === $item->status ) {
				$deact_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_deactivate_customer&id=' . $customer_id ),
					'tpm_deactivate_customer_' . $customer_id,
					'tpm_nonce'
				);
				$actions['deactivate'] = sprintf(
					'<a href="%1$s" class="tpm-action-archive">%2$s</a>',
					esc_url( $deact_url ),
					esc_html__( 'Deactivate', 'tender-po-management' )
				);
			}

			// Delete action: If records exist, link to safe deletion guard / reassignment page
			if ( $count > 0 ) {
				$delete_guard_url = admin_url( 'admin.php?page=tpm-customers&action=delete&id=' . $customer_id );
				$actions['delete'] = sprintf(
					'<a href="%1$s" class="tpm-action-delete" title="%2$s">%3$s</a>',
					esc_url( $delete_guard_url ),
					esc_attr__( 'Customer has associated records. Reassignment or deactivation required.', 'tender-po-management' ),
					esc_html__( 'Delete (Protected)', 'tender-po-management' )
				);
			} else {
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_delete_customer&id=' . $customer_id ),
					'tpm_delete_customer_' . $customer_id,
					'tpm_nonce'
				);
				$actions['delete'] = sprintf(
					'<a href="%1$s" class="tpm-action-delete" data-tpm-confirm="%2$s">%3$s</a>',
					esc_url( $delete_url ),
					esc_attr__( 'Permanently delete this customer? This action cannot be undone.', 'tender-po-management' ),
					esc_html__( 'Delete', 'tender-po-management' )
				);
			}
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Prepare customer items, execute queries, and initialize pagination.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = class_exists( 'TPM_Settings_Service' ) ? max( 1, absint( TPM_Settings_Service::get_setting( 'records_per_page', 20 ) ) ) : 20;
		$current_page = $this->get_pagenum();

		$args = array(
			'limit'  => $per_page,
			'offset' => ( $current_page - 1 ) * $per_page,
		);

		// Search term
		if ( ! empty( $_REQUEST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Status filter from quick views
		if ( ! empty( $_REQUEST['view'] ) && in_array( $_REQUEST['view'], array( 'active', 'inactive' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['status'] = sanitize_key( $_REQUEST['view'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Ordering
		if ( ! empty( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['orderby'] = sanitize_key( $_REQUEST['orderby'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_REQUEST['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['order'] = sanitize_key( $_REQUEST['order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$total_items = TPM_Customer_Service::get_customers_count( $args );
		$this->items = TPM_Customer_Service::get_customers( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			'name',
		);
	}

	/**
	 * Display the list table with responsive table-card container.
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
}
