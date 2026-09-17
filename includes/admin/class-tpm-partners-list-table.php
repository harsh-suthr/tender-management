<?php
/**
 * Admin: Partner Companies List Table
 *
 * Extends WP_List_Table to render a full-featured partner company directory.
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
 * TPM_Partners_List_Table Class.
 *
 * @since 1.0.0
 */
class TPM_Partners_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'partner',
				'plural'   => 'partners',
				'ajax'     => false,
				'screen'   => 'tpm-partner-companies',
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
			'cb'             => '<input type="checkbox" />',
			'company_name'   => __( 'Company', 'tender-po-management' ),
			'contact_person' => __( 'Contact Person', 'tender-po-management' ),
			'email'          => __( 'Email', 'tender-po-management' ),
			'phone'          => __( 'Phone', 'tender-po-management' ),
			'total_records'  => __( 'Associated Records', 'tender-po-management' ),
			'status'         => __( 'Status', 'tender-po-management' ),
			'actions'        => __( 'Actions', 'tender-po-management' ),
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
			'company_name'   => array( 'company_name', false ),
			'contact_person' => array( 'contact_person', false ),
			'email'          => array( 'email', false ),
			'total_records'  => array( 'total_records', false ),
			'created_at'     => array( 'created_at', true ),
		);
	}

	/**
	 * Define filter views for partner status.
	 *
	 * @since 1.0.0
	 * @return array<string, string> HTML list of view links.
	 */
	protected function get_views(): array {
		$counts       = TPM_Partner_Service::get_view_counts();
		$current_view = sanitize_key( $_REQUEST['view'] ?? 'all' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base_url     = admin_url( 'admin.php?page=tpm-partner-companies' );

		$views = array(
			'all' => sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $base_url ),
				( 'all' === $current_view || empty( $current_view ) ) ? ' class="current"' : '',
				esc_html__( 'All Partners', 'tender-po-management' ),
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
	 * @param object $item        Partner item object.
	 * @param string $column_name Column key.
	 * @return string Rendered HTML.
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'contact_person':
				return ! empty( $item->contact_person ) ? esc_html( $item->contact_person ) : '&mdash;';

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
				if ( $count > 0 ) {
					$url = admin_url( 'admin.php?page=tpm-records&partner_id=' . absint( $item->id ) );
					return sprintf(
						'<a href="%1$s" class="tpm-badge tpm-badge-count-link" title="%2$s"><span class="dashicons dashicons-clipboard"></span> %3$d %4$s</a>',
						esc_url( $url ),
						esc_attr__( 'View associated records', 'tender-po-management' ),
						$count,
						_n( 'Record', 'Records', $count, 'tender-po-management' )
					);
				}
				return '<span class="tpm-badge tpm-badge-count-zero">0 ' . esc_html__( 'Records', 'tender-po-management' ) . '</span>';

			case 'status':
				$is_active = ( 'active' === $item->status );
				$cls       = $is_active ? 'tpm-badge-active' : 'tpm-badge-draft';
				$label     = $is_active ? __( 'Active', 'tender-po-management' ) : __( 'Inactive', 'tender-po-management' );
				return sprintf( '<span class="tpm-badge %1$s">%2$s</span>', esc_attr( $cls ), esc_html( $label ) );

			case 'actions':
				$view_url = admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . absint( $item->id ) );
				$edit_url = admin_url( 'admin.php?page=tpm-partner-companies&action=edit&id=' . absint( $item->id ) );

				$out  = '<div class="tpm-row-btn-group">';
				$out .= sprintf( '<a href="%1$s" class="button button-small" title="%2$s"><span class="dashicons dashicons-visibility"></span> %3$s</a>', esc_url( $view_url ), esc_attr__( 'View Details', 'tender-po-management' ), esc_html__( 'View', 'tender-po-management' ) );
				if ( current_user_can( 'manage_tpm_partners' ) || current_user_can( 'manage_tpm_records' ) ) {
					$out .= sprintf( ' <a href="%1$s" class="button button-small" title="%2$s"><span class="dashicons dashicons-edit"></span> %3$s</a>', esc_url( $edit_url ), esc_attr__( 'Edit Partner', 'tender-po-management' ), esc_html__( 'Edit', 'tender-po-management' ) );
				}
				$out .= '</div>';
				return $out;

			default:
				return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '&mdash;';
		}
	}

	/**
	 * Render the primary column (company_name) with row actions.
	 *
	 * @since 1.0.0
	 * @param object $item Partner item.
	 * @return string Rendered HTML.
	 */
	protected function column_company_name( $item ): string {
		$view_url = admin_url( 'admin.php?page=tpm-partner-companies&action=view&id=' . absint( $item->id ) );
		$edit_url = admin_url( 'admin.php?page=tpm-partner-companies&action=edit&id=' . absint( $item->id ) );

		$title = sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>',
			esc_url( $view_url ),
			esc_html( $item->company_name )
		);

		$actions = array(
			'view' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $view_url ), esc_html__( 'View', 'tender-po-management' ) ),
		);

		if ( current_user_can( 'manage_tpm_partners' ) || current_user_can( 'manage_tpm_records' ) ) {
			$actions['edit'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'tender-po-management' ) );

			if ( 'active' === $item->status ) {
				$deact_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=tpm_deactivate_partner&id=' . absint( $item->id ) ),
					'tpm_deactivate_partner_' . absint( $item->id ),
					'tpm_nonce'
				);
				$actions['deactivate'] = sprintf(
					'<a href="%1$s" data-tpm-confirm="%2$s">%3$s</a>',
					esc_url( $deact_url ),
					esc_attr__( 'Are you sure you want to deactivate this partner company?', 'tender-po-management' ),
					esc_html__( 'Deactivate', 'tender-po-management' )
				);
			}

			$del_url = admin_url( 'admin.php?page=tpm-partner-companies&action=delete&id=' . absint( $item->id ) );
			$actions['delete'] = sprintf(
				'<a href="%1$s" class="tpm-link-delete" style="color: #dc2626;">%2$s</a>',
				esc_url( $del_url ),
				esc_html__( 'Delete', 'tender-po-management' )
			);
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Checkbox column renderer.
	 *
	 * @since 1.0.0
	 * @param object $item Partner item.
	 * @return string Rendered HTML.
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="partner_ids[]" value="%1$d" />',
			absint( $item->id )
		);
	}

	/**
	 * Prepares query, sorting, and pagination for partner listing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = class_exists( 'TPM_Settings_Service' ) ? max( 1, absint( TPM_Settings_Service::get_setting( 'records_per_page', 20 ) ) ) : 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$view    = isset( $_REQUEST['view'] ) ? sanitize_key( $_REQUEST['view'] ) : '';
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'created_at';
		$order   = ( isset( $_REQUEST['order'] ) && 'ASC' === strtoupper( (string) $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable

		$status_filter = '';
		if ( in_array( $view, array( 'active', 'inactive' ), true ) ) {
			$status_filter = $view;
		}

		$query_args = array(
			'search'  => $search,
			'status'  => $status_filter,
			'orderby' => $orderby,
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => $offset,
		);

		$total_items = TPM_Partner_Service::get_partners_count( $query_args );
		$this->items = TPM_Partner_Service::get_partners( $query_args );

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
			'company_name',
		);
	}

	/**
	 * Message displayed when no partner companies exist.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No partner companies found. Click "Add Partner Company" to create one.', 'tender-po-management' );
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
