<?php

class Sikshya_Core_Order
{

    public function course_item_id($course_id, $user_id)
    {

        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT user_item_id from " . SIKSHYA_DB_PREFIX . 'user_items ui
            INNER JOIN ' . $wpdb->prefix . 'posts p ON p.ID=ui.reference_id   
            
            WHERE ui.user_id=%d and ui.parent_id=0 and  ui.reference_type=%s and ui.status=%s and ui.item_id=%d and p.post_type=%s',
            $user_id,
            SIKSHYA_ORDERS_CUSTOM_POST_TYPE,
            'enrolled',
            $course_id,
            SIKSHYA_ORDERS_CUSTOM_POST_TYPE


        );

        $results = $wpdb->get_col($sql);


        if ($results[0]) {
            return $results[0];
        }
        return 0;

    }

}