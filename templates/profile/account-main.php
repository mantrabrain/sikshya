<?php

do_action('sikshya_before_account_page');

?>
    <div class="sikshya-account-page">
        <div class="sik-row">

            <?php

            do_action('sikshya_account_page_sidebar');

            do_action('sikshya_account_page_content');

            ?>
        </div>
    </div>
<?php

do_action('sikshya_after_account_page');