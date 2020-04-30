<?php

class Sikshya_Misc_ooks
{

    public function __construct()
    {
        add_action('init', array($this, 'add_image_sizes'));

    }

    public function add_image_sizes()
    {
        add_image_size('sikshya_course_thumbnail', 500, 340, true);
    }


}

new Sikshya_Misc_ooks();