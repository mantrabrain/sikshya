<div class="sikshya-single-lesson-wrap ">
    <div class="sikshya-lesson-sidebar">
        <div class="sikshya-sidebar-tabs-wrap">
            <div class="sikshya-tabs-btn-group">
                <a href="#sikshya-lesson-sidebar-tab-content" class="active"> <i class="sikshya-icon-education"></i>
                    <span><?php echo __('Lesson List', 'sikshya') ?></span></a>
                <!--<a href="#sikshya-lesson-sidebar-qa-tab-content" class=""> <i class="sikshya-icon-question-1"></i>
                    <span>Browse Q&amp;A</span></a>-->
            </div>

            <div class="sikshya-sidebar-tabs-content">
                <?php
                do_action('sikshya_lesson_sidebar_area');
                ?>
            </div>

        </div>

    </div>
    <div id="sikshya-single-entry-content"
         class="sikshya-lesson-content sikshya-single-entry-content sikshya-single-entry-content-227">


        <div class="sikshya-single-page-top-bar">

            <?php
            do_action('sikshya_lesson_content_before_top_bar');

            do_action('sikshya_lesson_content_top_bar');

            do_action('sikshya_lesson_content_after_top_bar');

            ?>

        </div>


        <div class="sikshya-lesson-content-area">
            <?php

            do_action('sikshya_lesson_content_area');

            ?>
        </div>

        <div class="sikshya-lesson-navigation-area">
            <?php do_action('sikshya_lesson_navigation_area'); ?>
        </div>

    </div>
</div>