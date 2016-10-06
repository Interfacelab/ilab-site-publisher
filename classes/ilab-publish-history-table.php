<?php
if(!class_exists( 'WP_List_Table' ))
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class ILabPublishHistoryTable extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct( [
                                 'singular' => 'Entry',
                                 'plural'   => 'Entries',
                                 'ajax'     => true
                             ] );

    }

    public static function get_activity( $per_page = 50, $page_number = 1 ) {

        global $wpdb;

        $sql = "select * from {$wpdb->prefix}ilab_publish_history order by date_added desc";

        $sql .= " LIMIT $per_page";

        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }

    public static function record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ilab_publish_activity";

        return $wpdb->get_var( $sql );
    }

    public function no_items() {
        return 'No activity';
    }

    public function get_columns() {
        $columns = [
            'version'=>'Version',
            'date'=>'Date',
            'user_name'=>'User',
            'status'=>'Status'
        ];

        return $columns;
    }

    public function get_sortable_columns() {
        return [];
    }

    public function get_bulk_actions() {
        return [];
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, [], []);

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'jobs_per_page', 12 );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( [
                                        'total_items' => $total_items, //WE have to calculate the total number of items
                                        'per_page'    => $per_page //WE have to determine how many items to show on a page
                                    ] );


        $this->items = self::get_activity( $per_page, $current_page );
    }

    public function column_version($item) {

        $item=(object)$item;
        return $item->version;
    }


    public function column_status($item) {

        $item=(object)$item;
        if ($item->status!='Success.')
            return "<span style='color:red'>{$item->status}</span>";
        else
            return "<span style='color:green'>{$item->status}</span>";
    }

    public function column_date($item) {
        $item=(object)$item;
        return '<span style="white-space:nowrap">'.date('n/j/Y g:i a',strtotime($item->date_added)).'</span>';
    }


    public function column_user_name($item) {
        $item=(object)$item;
        return $item->user_name;
    }
}
