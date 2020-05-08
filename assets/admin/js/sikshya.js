// @var SikshyaAdminData

jQuery(function ($) {

    var Sikshya_Admin_Course = {
        getSectionParams: function (id) {
            let node = $('#' + id);
            return {
                attach: '#' + id,
                width: 650,
                height: 450,
                closeButton: 'title',
                animation: false,
                title: 'AJAX request',
                ajax: {
                    url: SikshyaAdminData.ajax_url,
                    data: {
                        action: node.attr('data-action'),
                        sikshya_nonce: node.attr('data-nonce'),
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
        },
        getLessonParams: function (id) {
            let node = $('#' + id);
            return {
                attach: '#' + id,
                width: 650,
                height: 450,
                closeButton: 'title',
                animation: false,
                title: 'AJAX request Lesson',
                ajax: {
                    url: SikshyaAdminData.ajax_url,
                    data: {
                        action: node.attr('data-action'),
                        sikshya_nonce: node.attr('data-nonce'),
                        section_id: node.attr('data-section-id'),
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
        },
        getQuizParams: function (id) {
            let node = $('#' + id);
            return {
                attach: '#' + id,
                width: 650,
                height: 450,
                closeButton: 'title',
                animation: false,
                title: 'AJAX request QUIZ',
                ajax: {
                    url: SikshyaAdminData.ajax_url,
                    data: {
                        action: node.attr('data-action'),
                        sikshya_nonce: node.attr('data-nonce'),
                        section_id: node.attr('data-section-id'),
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
        },
        init: function () {
            var sectionParams = this.getSectionParams('sik-add-new-section');
            this.initModal(sectionParams);
            this.initSortable();
            this.bind();


        },
        initSortable: function () {

            $(".course-section-template-inner").sortable({
                connectWith: ".course-section-template-inner",
                start: function (event, ui) {
                    //get current element being sorted
                },
                stop: function (event, ui) {
                    var tmpl = ui.item.closest('.course-section-template');
                    var section_id = tmpl.attr('data-section-id');
                    var inner = ui.item.closest('.course-section-template-inner');
                    var card_item = inner.find('.sikshya-card-item');
                    var loop_start = 0;
                    $.each(card_item, function () {
                        loop_start++;
                        var type = $(this).find('.sikshya-course-content').attr('data-type-text');
                        $(this).find('.sikshya-course-content').attr('name', 'sikshya_course_content[' + section_id + '][' + type + '][]');
                        $(this).find('.order-number').val(loop_start);
                    });
                    //get current element being sorted
                }
            }).disableSelection();
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
            // Load Form Modal


            $('body').on('click', '.sik-add-new-lesson', function (e) {
                e.preventDefault();
                if ($(this).attr('id') === undefined) {
                    var section_id = $(this).attr('data-section-id');
                    var id = 'sik-add-new-lesson-' + section_id;
                    $(this).attr('id', id);
                    let lessonParams = _that.getLessonParams(id);
                    _that.initModal(lessonParams);
                    $(this).trigger('click');
                }

            });
            $('body').on('click', '.sik-add-new-quiz', function (e) {
                e.preventDefault();
                if ($(this).attr('id') === undefined) {
                    var section_id = $(this).attr('data-section-id');
                    var id = 'sik-add-new-quiz-' + section_id;
                    $(this).attr('id', id);
                    let quizParams = _that.getQuizParams(id);
                    _that.initModal(quizParams);
                    $(this).trigger('click');
                }

            });

            // end of form modal


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