<?php

class Sikshya_Hooks
{

    public function __construct()
    {
        include_once SIKSHYA_PATH . '/includes/hooks/class-sikshya-template-hooks.php';
        include_once SIKSHYA_PATH . '/includes/hooks/class-sikshya-lesson-hooks.php';
        include_once SIKSHYA_PATH . '/includes/hooks/class-sikshya-quiz-hooks.php';
        include_once SIKSHYA_PATH . '/includes/hooks/class-sikshya-quiz-question-answer-hooks.php';
    }

}

new Sikshya_Hooks();