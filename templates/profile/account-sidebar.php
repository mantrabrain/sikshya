<div class="sik-col-md-3">

    <div class="sikshya-account-sidebar">

        <ul class="sikshya-account-nav-list">

            <?php

            global $sikshya_current_account_page;

            foreach (sikshya_account_page_nav_items() as $item_key => $item_array) {

                $icon = isset($item_array['icon']) ? '<span class="' . esc_attr($item_array['icon']) . '"></span> ' : '';

                $class = $sikshya_current_account_page == $item_key ? 'active' : '';

                echo '<li class="sikshya-account-nav-' . esc_attr($item_key) . ' ' . $class . '">';

                echo '<a href="' . esc_url(sikshya()->helper->account_sidebar_nav_permalink($item_key)) . '">' . $icon . $item_array['title'] . '</a>';

                echo '</li>';
            }
            ?>
        </ul>
    </div>
</div>