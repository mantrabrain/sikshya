// @var SikshyaAdminData

jQuery(function ($) {

    var Sikshya_Admin_Course = {
        init: function () {
            let add_section = $('#sik-add-new-section');
            let add_lesson = $('.sik-add-new-lesson');
            let add_quiz = $('.sik-add-new-quiz');
            var sectionParams = {
                attach: '#sik-add-new-section',
                width: 650,
                height: 450,
                closeButton: 'title',
                animation: false,
                title: 'AJAX request',
                ajax: {
                    url: SikshyaAdminData.ajax_url,
                    data: {
                        action: add_section.attr('data-action'),
                        sikshya_nonce: add_section.attr('data-nonce'),
                    },
                    method: 'post',
                    reload: 'strict',
                    setContent: false,
                    beforeSend: function () {
                        this.setContent('');
                        this.setTitle('<div class="ajax-sending">Sending AJAX request...</div>');
                    },
                    complete: function () {
                        this.setTitle('<div class="ajax-complete">AJAX request complete</div>');
                    },
                    success: function (response) {
                        this.setContent(response);
                    },
                    error: function () {
                        this.setContent('<div class="ajax-error">Oops, something went wrong</div>');
                    }
                }
            };
            var lessonParams = {
                attach: '.sik-add-new-lesson',
                width: 650,
                height: 450,
                closeButton: 'title',
                animation: false,
                title: 'AJAX request Lesson',
                ajax: {
                    url: SikshyaAdminData.ajax_url,
                    data: {
                        action: add_lesson.attr('data-action'),
                        sikshya_nonce: add_lesson.attr('data-nonce'),
                        section_id: add_lesson.attr('data-section-id'),
                    },
                    method: 'post',
                    reload: 'strict',
                    setContent: false,
                    beforeSend: function () {
                        this.setContent('');
                        this.setTitle('<div class="ajax-sending">Sending AJAX request LESSON...</div>');
                    },
                    complete: function () {
                        this.setTitle('<div class="ajax-complete">AJAX request complete LESSON</div>');
                    },
                    success: function (response) {
                        this.setContent(response);
                    },
                    error: function () {
                        this.setContent('<div class="ajax-error">Oops, something went wrong</div>');
                    }
                }
            };
            var quizParams = {
                attach: '.sik-add-new-quiz',
                width: 650,
                height: 450,
                closeButton: 'title',
                animation: false,
                title: 'AJAX request QUIZ',
                ajax: {
                    url: SikshyaAdminData.ajax_url,
                    data: {
                        action: add_quiz.attr('data-action'),
                        sikshya_nonce: add_quiz.attr('data-nonce'),
                        section_id: add_quiz.attr('data-section-id'),
                    },
                    method: 'post',
                    reload: 'strict',
                    setContent: false,
                    beforeSend: function () {
                        this.setContent('');
                        this.setTitle('<div class="ajax-sending">Sending AJAX request QUIZ...</div>');
                    },
                    complete: function () {
                        this.setTitle('<div class="ajax-complete">AJAX request complete QUIZ</div>');
                    },
                    success: function (response) {
                        this.setContent(response);
                    },
                    error: function () {
                        this.setContent('<div class="ajax-error">Oops, something went wrong</div>');
                    }
                }
            };

            this.initModal(sectionParams);
            this.initModal(lessonParams);
            this.initModal(quizParams);
            this.bind();


        },

        initModal: function (params) {
            new jBox('Modal', {
                attach: params.attach,
                width: params.width,
                height: params.height,
                closeButton: params.closeButton,
                animation: params.animation,
                title: params.title,
                ajax: params.ajax
            });

        },
        bind: function () {

            var _that = this;
            $('body').on('submit', 'form.section-form', function (e) {
                e.preventDefault();
                _that.bindSectionForm($(this));

            });

            $('body').on('submit', 'form.lesson-form', function (e) {
                e.preventDefault();
                _that.bindLessonForm($(this));

            });

            $('body').on('submit', 'form.quiz-form', function (e) {
                e.preventDefault();
                _that.bindQuizForm($(this));

            });
        },
        bindSectionForm: function ($this) {
            var form = $this;
            var url = $this.attr('action');

            $.ajax({
                type: "POST",
                url: url,
                data: form.serialize(), // serializes the form's elements.
                success: function (data) {

                    $('.jBox-closeButton').trigger('click');
                    if (typeof  data.success == "undefined") {
                        $('.sikshya-course-meta-curriculum-tab').prepend(data);
                    }
                }
            });
        },
        bindLessonForm: function ($this) {
            var form = $this;
            var url = $this.attr('action');
            var section_id = $this.attr('data-section-id');

            $.ajax({
                type: "POST",
                url: url,
                data: form.serialize(), // serializes the form's elements.
                success: function (data) {

                    $('.jBox-closeButton').trigger('click');

                    if (typeof  data.success == "undefined") {

                        $('.sikshya-course-meta-curriculum-tab #course-section-template-' + section_id + ' .course-section-template-inner').prepend(data);
                    }
                }
            });
        },
        bindQuizForm: function ($this) {
            var form = $this;
            var url = $this.attr('action');
            var section_id = $this.attr('data-section-id');

            $.ajax({
                type: "POST",
                url: url,
                data: form.serialize(), // serializes the form's elements.
                success: function (data) {

                    $('.jBox-closeButton').trigger('click');
                    if (typeof  data.success == "undefined") {
                        $('.sikshya-course-meta-curriculum-tab #course-section-template-' + section_id + ' .course-section-template-inner').prepend(data);
                    }
                }
            });
        },


    };
    Sikshya_Admin_Course.init();

});