<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<?php if ($logs) : ?>
    <div id="sikshya-log-viewer-select">
        <div class="alignleft">
            <h2>
                <?php echo esc_html($viewed_log); ?>
                <?php if (!empty($viewed_log)) : ?>
                    <a class="page-title-action"
                       href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('handle' => sanitize_title($viewed_log)), admin_url('admin.php?page=sikshya-status&tab=logs')), 'remove_log')); ?>"
                       class="button"><?php esc_html_e('Delete log', 'sikshya'); ?></a>
                <?php endif; ?>
            </h2>
        </div>
        <div class="alignright">
            <form action="<?php echo esc_url(admin_url('admin.php?page=sikshya-status&tab=logs')); ?>" method="post">
                <select name="log_file">
                    <?php foreach ($logs as $log_key => $log_file) : ?>
                        <?php
                        $timestamp = filemtime(sikshya()->get_log_dir() . $log_file);
                        $date = sprintf(
                        /* translators: 1: last access date 2: last access time 3: last access timezone abbreviation */
                            __('%1$s at %2$s %3$s', 'sikshya'),
                            wp_date(sikshya_date_format(), $timestamp),
                            wp_date(sikshya_time_format(), $timestamp),
                            wp_date('T', $timestamp)
                        );
                        ?>
                        <option value="<?php echo esc_attr($log_key); ?>" <?php selected(sanitize_title($viewed_log), $log_key); ?>><?php echo esc_html($log_file); ?>
                            (<?php echo esc_html($date); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"
                        value="<?php esc_attr_e('View', 'sikshya'); ?>"><?php esc_html_e('View', 'sikshya'); ?></button>
            </form>
        </div>
        <div class="clear"></div>
    </div>
    <div id="sikshya-log-viewer">
        <pre><?php echo esc_html(file_get_contents(sikshya()->get_log_dir() . $viewed_log)); ?></pre>
    </div>
<?php else : ?>
    <div class="updated sikshya-message inline">
        <p><?php esc_html_e('There are currently no logs to view.', 'sikshya'); ?></p></div>
<?php endif; ?>
