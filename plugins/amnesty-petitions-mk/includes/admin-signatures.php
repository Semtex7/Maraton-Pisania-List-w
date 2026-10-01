<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure the core WP_List_Table class is loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom List Table for Amnesty Signatures
 */
class Amnesty_Signatures_List_Table extends WP_List_Table {

    // Constructor to setup the table
    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Signature', 'amnesty-petitions' ),
            'plural'   => __( 'Signatures', 'amnesty-petitions' ),
            'ajax'     => false
        ) );
    }

    // Define table columns
    public function get_columns() {
        $columns = array(
            'cb'                 => '<input type="checkbox" />', // Checkbox for bulk actions
            'id'                 => __( 'ID', 'amnesty-petitions' ),
            'petition_id'        => __( 'ID Petycji', 'amnesty-petitions' ),
            'name'               => __( 'Imię i Nazwisko', 'amnesty-petitions' ),
            'email'              => __( 'E-mail', 'amnesty-petitions' ),
            'city'               => __( 'Miasto', 'amnesty-petitions' ),
            'letter_type'        => __( 'Typ listu', 'amnesty-petitions' ),
            'custom_message'     => __( 'Treść listu', 'amnesty-petitions' ),
            'consents'           => __( 'Zgody (Kamp/News)', 'amnesty-petitions' ),
            'salesforce_synced'  => __( 'Status SF', 'amnesty-petitions' ),
            'signed_at'          => __( 'Data', 'amnesty-petitions' )
        );
        return $columns;
    }

    // Define which columns are sortable
    protected function get_sortable_columns() {
        $sortable_columns = array(
            'id'          => array( 'id', false ),
            'petition_id' => array( 'petition_id', false ),
            'signed_at'   => array( 'signed_at', false )
        );
        return $sortable_columns;
    }

    // Define bulk actions (Foundation for export)
    protected function get_bulk_actions() {
        $actions = array(
            'export_csv' => __( 'Eksportuj do CSV', 'amnesty-petitions' )
        );
        return $actions;
    }

    // Render checkbox column
    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="signature_ids[]" value="%s" />',
            $item->id
        );
    }

    // Render default columns and handle undefined properties to prevent PHP Warnings
    protected function column_default( $item, $column_name ) {
        // Use null coalescing operator (??) to fallback to empty string if property is missing
        $city               = $item->city ?? '';
        $letter_type        = $item->letter_type ?? 'appeal';
        $custom_message     = $item->custom_message ?? '';
        $campaign_consent   = $item->campaign_consent ?? 0;
        $newsletter_consent = $item->newsletter_consent ?? 0;
        $salesforce_synced = $item->salesforce_synced ?? 0;

        switch ( $column_name ) {
            case 'id':
            case 'petition_id':
                return $item->$column_name;
            case 'name':
                return esc_html( $item->first_name . ' ' . $item->last_name );
            case 'email':
                return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $item->email ) );
            case 'city':
                return esc_html( $city );
            case 'letter_type':
                return $letter_type === 'solidarity' ? 'Solidarnościowy' : 'Do władz';
            case 'custom_message':
                return sprintf( '<span title="%1$s">%2$s</span>', 
                    esc_attr( $custom_message ), 
                    esc_html( wp_trim_words( $custom_message, 10, '...' ) ) 
                );
            case 'consents':
                $camp = $campaign_consent ? '✅' : '❌';
                $news = $newsletter_consent ? '✅' : '❌';
                return $camp . ' / ' . $news;
            case 'salesforce_synced': 
                return $salesforce_synced ? '<span title="Zsynchronizowano z Salesforce">✅</span>' : '<span title="Brak synchronizacji">❌</span>';
            case 'signed_at':
                return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->signed_at ) ) );
            default:
                return print_r( $item, true ); // For debugging unknown columns
        }
    }

    protected function extra_tablenav( $which ) {
        // We only want to show the filter dropdown at the top
        if ( $which !== 'top' ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'amnesty_signatures';
        
        // Fetch only unique petition IDs that actually have signatures
        $petition_ids = $wpdb->get_col( "SELECT DISTINCT petition_id FROM $table_name ORDER BY petition_id ASC" );

        if ( empty( $petition_ids ) ) {
            return;
        }

        // Check if a specific filter is currently active
        $selected_id = isset( $_REQUEST['filter_petition_id'] ) ? intval( $_REQUEST['filter_petition_id'] ) : 0;
        ?>
        <div class="alignleft actions">
            <select name="filter_petition_id">
                <option value="0"><?php esc_html_e( 'Wszystkie petycje', 'amnesty-petitions' ); ?></option>
                <?php foreach ( $petition_ids as $p_id ) : ?>
                    <option value="<?php echo esc_attr( $p_id ); ?>" <?php selected( $selected_id, $p_id ); ?>>
                        <?php printf( esc_html__( 'Petycja ID: %d', 'amnesty-petitions' ), $p_id ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button( __( 'Filtruj', 'amnesty-petitions' ), 'button', 'filter_action', false ); ?>
        </div>
        <?php
    }

    // Prepare data, handle sorting and pagination
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'amnesty_signatures';

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Basic query construction
        $query = "SELECT * FROM $table_name";
        $where_clauses = array();

        // 1. Handle filtering by petition ID
        if ( ! empty( $_REQUEST['filter_petition_id'] ) ) {
            $filter_id = intval( $_REQUEST['filter_petition_id'] );
            // Use wpdb->prepare for security against SQL injection
            $where_clauses[] = $wpdb->prepare( "petition_id = %d", $filter_id );
        }

        // Append WHERE clauses if any exist
        if ( ! empty( $where_clauses ) ) {
            $query .= " WHERE " . implode( ' AND ', $where_clauses );
        }

        // 2. Handle sorting parameters
        $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'signed_at';
        $order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC';

        $query .= " ORDER BY $orderby $order";

        // 3. Fetch data from database
        $this->items = $wpdb->get_results( $query );
    }
}

// Hook to add submenu page under our Custom Post Type
add_action( 'admin_menu', 'amnesty_signatures_admin_menu' );

function amnesty_signatures_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=amnesty_petition',
        __( 'Zebrane podpisy', 'amnesty-petitions' ),
        __( 'Podpisy', 'amnesty-petitions' ),
        'manage_options',
        'amnesty-signatures',
        'amnesty_render_signatures_page'
    );
}

// Render the admin page
function amnesty_render_signatures_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Initialize the custom List Table
    $signatures_table = new Amnesty_Signatures_List_Table();
    $signatures_table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Zebrane podpisy pod petycjami', 'amnesty-petitions' ); ?></h1>
        <hr class="wp-header-end">
        
        <form id="signatures-filter" method="post">
            <?php
            // Display the table and bulk actions
            $signatures_table->display();
            ?>
        </form>
    </div>
    <?php
}

// Hook into admin_init to process bulk actions before page headers are sent
add_action( 'admin_init', 'amnesty_process_signatures_bulk_actions' );

function amnesty_process_signatures_bulk_actions() {
    // Check if we are on the correct admin page
    if ( ! isset( $_REQUEST['page'] ) || $_REQUEST['page'] !== 'amnesty-signatures' ) {
        return;
    }

    // Capture the action from either the top or bottom bulk action dropdown
    $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
    $action2 = isset( $_REQUEST['action2'] ) ? $_REQUEST['action2'] : '';

    // Proceed only if the export action was selected
    if ( $action !== 'export_csv' && $action2 !== 'export_csv' ) {
        return;
    }

    // Verify if any checkboxes were selected in the table
    if ( empty( $_REQUEST['signature_ids'] ) || ! is_array( $_REQUEST['signature_ids'] ) ) {
        // You could add an admin notice here if needed
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'amnesty_signatures';
    
    // Sanitize selected IDs to prevent SQL injection
    $ids = array_map( 'intval', $_REQUEST['signature_ids'] );
    $ids_string = implode( ',', $ids );

    // Fetch the specific records from the database
    $results = $wpdb->get_results( "SELECT * FROM $table_name WHERE id IN ($ids_string)", ARRAY_A );

    if ( empty( $results ) ) {
        return;
    }

    // Set headers to force the browser to download a file
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=signatures-export-' . date( 'Y-m-d' ) . '.csv' );

    // Open a temporary output stream
    $output = fopen( 'php://output', 'w' );

    // Add BOM (Byte Order Mark) to ensure Excel reads UTF-8 characters correctly (like Polish letters)
    fputs( $output, $bom = ( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );

    // Define and output CSV column headers
    $csv_headers = array( 
        'ID', 
        'ID Petycji', 
        'Imię', 
        'Nazwisko', 
        'Email', 
        'Telefon', 
        'Miasto', 
        'Typ listu', 
        'Treść wiadomości', 
        'Zgoda kampanijna', 
        'Zgoda newsletter', 
        'Data podpisu' 
    );
    
    // Use a semicolon delimiter which works best with European Excel versions
    fputcsv( $output, $csv_headers, ';' ); 

    // Loop through database results and write each row to the CSV file
    foreach ( $results as $row ) {
        fputcsv( $output, $row, ';' );
    }

    // Close the stream and terminate script execution to prevent HTML rendering in the CSV
    fclose( $output );
    exit;
}