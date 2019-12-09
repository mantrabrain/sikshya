<?php

class Sikshya_Role_Manager
{

    public function add_instructor_role($instructor_id = 0)
    {
        if (!$instructor_id) {
            return;
        }
        do_action('sikshya_before_approved_instructor', $instructor_id);

        update_user_meta($instructor_id, 'sikshya_instructor_approval_status', 'approved');

        update_user_meta($instructor_id, 'sikshya_instructor_approved_time', sikshya_time());

        $instructor = new \WP_User($instructor_id);

        $instructor->add_role('sikshya_instructor');

        do_action('sikshya_after_approved_instructor', $instructor_id);
    }

    public function add_student($student_id = 0)
    {
        if (!$student_id) {
            return;
        }
        $enrolled_status = get_user_meta($student_id, 'sikshya_student_enrolled_status', true);

        if ($enrolled_status == 'enrolled') {

            return;
        }

        do_action('sikshya_before_enrolled_student', $student_id);

        update_user_meta($student_id, 'sikshya_student_enrolled_status', 'enrolled');

        update_user_meta($student_id, 'sikshya_student_first_enrolled_time', sikshya_time());

        $student = new \WP_User($student_id);

        $student->add_role('sikshya_student');

        do_action('sikshya_after_enrolled_student', $student_id);
    }

    public function has_instructor($instructor_id = 0)
    {
        $instructor_id = absint($instructor_id);

        $user = get_user_by('id', $instructor_id);

        if (in_array('sikshya_instructor', (array)$user->roles)) {
            return true;
        }

        return false;
    }

    public function init_sikshya_roles()
    {
        add_role(
            'sikshya_student',
            __('Sikshya Student', 'sikshya'),

            array(
                'edit_posts' => true,
            )
        );

        add_role(
            'sikshya_instructor',
            __('Sikshya Instructor', 'sikshya'),

            array(
                'edit_posts' => true,
            )
        );
    }

    public function __construct()
    {
        $this->init_sikshya_roles();
    }
}

new Sikshya_Role_Manager();