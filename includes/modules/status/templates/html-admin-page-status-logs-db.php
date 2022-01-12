<?php
if (!defined('ABSPATH')) {
    exit;
}

?>
    <form method="post" id="mainform" action="">
        <?php $log_table_list->search_box(__('Search logs', 'sikshya'), 'log'); ?>
        <?php $log_table_list->display(); ?>

        <input type="hidden" name="page" value="sikshya-status"/>
        <input type="hidden" name="tab" value="logs"/>

        <?php submit_button(__('Flush all logs', 'sikshya'), 'delete', 'sikshya-flush-logs'); ?>
        <?php wp_nonce_field('sikshya-status-logs'); ?>
    </form>
<?php
sikshya_enqueue_js(
    "jQuery( '#sikshya-flush-logs' ).on( 'click', function() {
		if ( window.confirm('" . esc_js(__('Are you sure you want to clear all logs from the database?', 'sikshya')) . "') ) {
			return true;
		}
		return false;
	});"
);
