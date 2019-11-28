// @var sikshya

jQuery(function ($) {
    var app = {

        _accm: null,
        _accl: null,
        _accq: null,
        _accqq: null,

        run: function () {
            this._initApp();
        }

        , refresh: function () {
            var _this = this;

            this._initAccordions();

            //init all editors
            $('.js-sikshya__editor:visible').each(function () {
                _this._initEditor($(this).attr('id'));
            });
        }

        , _initApp: function () {
            var _this = this;

            this._initEvents();
            this._initSocial();
            this._initPaymentSystems();

            this.refresh();

            return this;
        }

        , _initTabs: function ($element) {
            var _this = this;
            $element.tabs({
                activate: function (event, ui) {
                    ui.oldTab.removeClass('wp-tab-active');
                    ui.newTab.addClass('wp-tab-active');

                    ui.newPanel.find('.js-sikshya__editor:visible').each(function () {
                        _this._initEditor($(this).attr('id'));
                    });
                },
                beforeActivate: function (event, ui) {
                    ui.oldPanel.find('.js-sikshya__editor').each(function () {
                        _this._removeEditor($(this).attr('id'));
                    });
                },
            });
        }

        , _initEvents: function () {
            var _this = this;

            $(document).on('postbox-toggled', function (e, p) {
                $(p).find('.js-sikshya__editor').each(function () {
                    _this._removeEditor($(this).attr('id'));
                });
                $(p).find('.js-sikshya__editor:visible').each(function () {
                    _this._initEditor($(this).attr('id'));
                });
            });

            $(window).on('initTabs', function (e, param) {
                _this._initTabs(param.$element);
            });

            /*add section*/
            $(document).on('click', '.js-sikshya__sections-add', function (e) {
                e.preventDefault();
                var editor_id = _this._uniqid();
                var section_id = _this._uniqid();
                var lesson_id = _this._uniqid();
                var editor_name = 'sikshya_lesson[' + section_id + '][' + lesson_id + '][lessons_content]';

                var lesson_editor = _this._getEditorHtml(editor_id, editor_name);
                var lesson_template = _this._getLessonHtml(section_id, lesson_id, lesson_editor, '');
                var section_el = $(_this._getModuleHtml(section_id, lesson_template));

                _this._accm.append(section_el);

                _this._initAccordion(_this._accm, true);
                _this._initAccordion(section_el.find('.js-sikshya__sections-item_wrapper-lessons'), true);

                _this._accm.accordion({
                    active: -1
                });
            });
            var SikshyaAdmin = {

                init: function () {

                    this.allEvents();
                },
                allEvents: function () {
                    var $this = this;
                    // Remove section from course
                    $(document).on('click', '.js-sikshya__remove-section', function (e) {
                        e.preventDefault();
                        var section_id = $(this).closest('.group.js-sikshya__sections-item').attr('data-section-id');
                        var params = {
                            title: 'Are you sure?',
                            text: 'It will be removed from course!',
                            button_text: 'Yes, remove it!',
                            ajax_options: {
                                ajax_url: sikshya.ajax_url,
                                data: {
                                    "id": section_id,
                                    'action': 'sikshya_remove_section_from_course',
                                    'sikshya_nonce': sikshya.remove_section_from_course_nonce,
                                    'course_id': sikshya.course_id
                                }

                            }
                        };
                        $this.swalFire(params);

                    });

                    /*Remove lesson from course & section*/
                    $(document).on('click', '.js-sikshya__remove-lesson', function (e) {
                        e.preventDefault();
                        var section_id = $(this).closest('.group.js-sikshya__sections-item').attr('data-section-id');
                        var lesson_id = $(this).closest('.group.js-sikshya__lesson-item').attr('data-lesson-id');
                        var params = {
                            title: 'Are you sure?',
                            text: 'It will be removed lesson from section & course!',
                            button_text: 'Yes, remove it!',
                            ajax_options: {
                                ajax_url: sikshya.ajax_url,
                                data: {
                                    "section_id": section_id,
                                    'action': 'sikshya_remove_lesson_from_course',
                                    'sikshya_nonce': sikshya.remove_lesson_from_course_nonce,
                                    'course_id': sikshya.course_id,
                                    'lesson_id': lesson_id
                                }

                            }
                        };
                        $this.swalFire(params);

                    });


                    /*Remove Quiz from Lesson, Section & Course*/
                    $(document).on('click', '.js-sikshya__remove-quiz', function (e) {
                        e.preventDefault();
                        var section_id = $(this).closest('.group.js-sikshya__sections-item').attr('data-section-id');
                        var lesson_id = $(this).closest('.group.js-sikshya__lesson-item').attr('data-lesson-id');
                        var quiz_id = $(this).closest('.group.js-sikshya__quiz-item').attr('data-quiz-id');
                        var params = {
                            title: 'Are you sure?',
                            text: 'It will be removed quiz from section, lesson & course!',
                            button_text: 'Yes, remove it!',
                            ajax_options: {
                                ajax_url: sikshya.ajax_url,
                                data: {
                                    "section_id": section_id,
                                    'action': 'sikshya_remove_quiz_from_course',
                                    'sikshya_nonce': sikshya.remove_quiz_from_course_nonce,
                                    'course_id': sikshya.course_id,
                                    'lesson_id': lesson_id,
                                    'quiz_id': quiz_id
                                }

                            }
                        };
                        $this.swalFire(params);
                    });

                    /*remove quiz question from quiz,lesson,section & course*/
                    $(document).on('click', '.js-sikshya__remove-quiz-question', function (e) {
                        e.preventDefault();
                        var section_id = $(this).closest('.group.js-sikshya__sections-item').attr('data-section-id');
                        var lesson_id = $(this).closest('.group.js-sikshya__lesson-item').attr('data-lesson-id');
                        var quiz_id = $(this).closest('.group.js-sikshya__quiz-item').attr('data-quiz-id');
                        var question_id = $(this).closest('.group.js-sikshya__quiz-question-item').attr('data-question-id');
                        debugger;
                        var params = {
                            title: 'Are you sure?',
                            text: 'It will be removed quiz from quiz, section, lesson & course!',
                            button_text: 'Yes, remove it!',
                            ajax_options: {
                                ajax_url: sikshya.ajax_url,
                                data: {
                                    "section_id": section_id,
                                    'action': 'sikshya_remove_question_from_course',
                                    'sikshya_nonce': sikshya.remove_question_from_course_nonce,
                                    'course_id': sikshya.course_id,
                                    'lesson_id': lesson_id,
                                    'quiz_id': quiz_id,
                                    'question_id': question_id
                                }

                            }
                        };
                        $this.swalFire(params);

                    });

                },
                swalFire: function (params) {
                    var ajax_options = params.ajax_options;
                    Swal.fire({
                        title: params.title,//'Are you sure?',
                        text: params.text,//"It will be deleted permanently!",
                        type: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: params.button_text,//'Yes, delete it!',
                        showLoaderOnConfirm: true,
                        preConfirm: function () {
                            return new Promise(function (resolve) {
                                $.ajax({
                                    url: ajax_options.ajax_url,
                                    type: 'POST',
                                    data: ajax_options.data,
                                    dataType: 'json'
                                })
                                    .done(function (response) {

                                        Swal.fire('Deleted!', response.message, response.status);
                                        location.reload();

                                    })
                                    .fail(function () {
                                        Swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                                    });
                            });
                        },
                        allowOutsideClick: false
                    });
                }
            };

            SikshyaAdmin.init();


            /*add lesson*/
            $(document).on('click', '.js-sikshya__sections-lessons-add', function (e) {
                e.preventDefault();
                var _that = $(this);
                var elem = _that.closest('.js-sikshya__sections-item_wrapper').find('.js-sikshya__sections-item_wrapper-lessons');

                var editor_id = _this._uniqid();
                var lesson_id = _this._uniqid();
                var section_id = _that.closest('.js-sikshya__sections-item').attr('data-section-id');
                var editor_name = 'sikshya_lesson[' + section_id + '][' + lesson_id + '][lessons_content]';
                var lesson_editor = _this._getEditorHtml(editor_id, editor_name);
                var lesson_el = $(_this._getLessonHtml(section_id, lesson_id, lesson_editor, ''));

                elem.append(lesson_el);

                _this._initAccordion(elem, false);

                elem.accordion({
                    active: -1
                });
            });

            /*add quiz*/
            $(document).on('click', '.js-sikshya__lesson-quizes-add', function (e) {
                e.preventDefault();
                var _that = $(this);
                if (_that.hasClass('disabled')) {
                    return;
                }
                //_that.addClass('disabled');

                var elem = _that.closest('.js-sikshya__lesson-form').find('.js-sikshya__wrapper-quizes');

                var editor_id = _this._uniqid();
                var quiz_id = _this._uniqid();
                var section_id = _that.closest('.js-sikshya__sections-item').attr('data-section-id');
                var lesson_id = _that.closest('.js-sikshya__lesson-item').attr('data-lesson-id');
                var editor_name = 'lessons_quiz[' + section_id + '][' + lesson_id + '][' + quiz_id + '][content]';
                var quiz_editor = _this._getEditorHtml(editor_id, editor_name);
                var quiz_template = _this._getQuizHtml(section_id, lesson_id, quiz_id, quiz_editor);

                elem.append(quiz_template);

                _this._initAccordion(elem, false);

                elem.accordion({
                    active: -1
                });
            });

            /*add quiz question*/
            $(document).on('click', '.js-sikshya__quiz-questions-add', function (e) {
                e.preventDefault();
                var _that = $(this);

                var elem = _that.closest('.js-sikshya__quiz-form').find('.js-sikshya__wrapper-quiz-questions');

                var quiz_id = _that.closest('.js-sikshya__quiz-item').attr('data-quiz-id');

                var section_id = _that.closest('.js-sikshya__sections-item').attr('data-section-id');
                var lesson_id = _that.closest('.js-sikshya__lesson-item').attr('data-lesson-id');
                var question_id = _this._uniqid();
                var question_template = _this._getQuizQuestionHtml(section_id, lesson_id, quiz_id, question_id);

                elem.append(question_template);

                _this._initAccordion(elem, true);

                elem.accordion({
                    active: -1
                });
            });


            /*add quiz question answer*/
            $(document).on('click', '.js-sikshya__quiz-question-answers-add', function (e) {
                e.preventDefault();
                var _that = $(this);

                var elem = _that.closest('.js-sikshya__quiz-question-form').find('.js-sikshya__wrapper-quiz-question-answers');

                var section_id = _that.closest('.js-sikshya__sections-item').attr('data-section-id');
                var lesson_id = _that.closest('.js-sikshya__lesson-item').attr('data-lesson-id');
                var quiz_id = _that.closest('.js-sikshya__quiz-item').attr('data-quiz-id');
                var question_id = _that.closest('.js-sikshya__quiz-question-item').attr('data-question-id');
                var answer_id = _this._uniqid();
                var answer_template = _this._getQuizQuestionAnswerHtml(section_id, lesson_id, quiz_id, question_id, answer_id);

                elem.append(answer_template);

                _that.closest('.js-sikshya__quiz-question-form').find('.js-sikshya__quiz-question-type').change();
            });

            /*change quiz question answer type*/
            $(document).on('change', '.js-sikshya__quiz-question-type', function (e) {
                var value = $(this).val();

                var answers = $(this).closest('.js-sikshya__quiz-question-item').find('.js-sikshya__wrapper-quiz-question-answers');
                var answersRows = $(this).closest('.js-sikshya__quiz-question-item').find('.js-sikshya__quiz-question-answer_type');

                answersRows.hide();
                answersRows.filter('.js-sikshya__quiz-question-answer_type_' + value).show();

                if (value === 'single' || value === 'single_image') {
                    answers.find('.js-sikshya__quiz-question-answer_correct').attr('type', 'radio');
                } else if (value === 'multi' || value === 'multi_image') {
                    answers.find('.js-sikshya__quiz-question-answer_correct').attr('type', 'checkbox');
                }
            });

            /*remove quiz question answer*/
            $(document).on('click', '.js-sikshya__remove-quiz-question-answer', function (e) {
                e.preventDefault();
                if (confirm('Remove?')) {
                    $(this).closest('.js-sikshya__quiz-question-answer-item').remove();
                }
            });

            /*Preview title*/
            $(document).on('keyup', '.js-sikshya__sections-item-title, .js-sikshya__lesson-title, .js-sikshya__quiz-title, .js-sikshya__quiz-question-title', function (e) {
                var val = $(this).val();
                if ($.trim(val) == '') {
                    val = 'New';
                }
                $(this).closest('.group').find('.js-group__label_text:eq(0)').text(val);
                _this._accm.accordion("refresh");
                _this._accl.accordion("refresh");
                _this._accq.accordion("refresh");
                _this._accqq.accordion("refresh");
            });

            /*Template selection*/
            $(document).on('click', '.js-sikshya__settings_template', function (e) {
                var val = $(this).val();
                if (val === 'custom') {
                    $('.js-sikshya__settings_colors').show();
                } else {
                    $('.js-sikshya__settings_colors').hide();
                }
            });

            /*Social selection*/
            $(document).on('click', '.js-sikshya__social-add', function (e) {
                e.preventDefault();
                var wrapper = $(this).closest('.js-sikshya__social-wrapper');
                var container = wrapper.find('.js-sikshya__social');
                var html = wrapper.find('.js-sikshya__social-item-tpl').html();
                html = _this._replaceAll(html, '{%image%}', '');
                html = _this._replaceAll(html, '{%url%}', '');
                html = _this._replaceAll(html, '{%label%}', '');
                html = _this._replaceAll(html, '{%description%}', '');
                container.append(html);
            });
            $(document).on('click', '.js-sikshya__social-delete', function (e) {
                e.preventDefault();
                var item = $(this).closest('.js-sikshya__social-item');
                item.remove();
            });

            /*Media selection*/
            var file_frame = null;
            $(document).on('click', '.js-sikshya__image_upload_button', function (e) {
                e.preventDefault();

                var $wrapper = $(this).closest('.js-sikshya__image_upload_wrapper');
                var size = $(this).data('size');

                if (!file_frame) {
                    file_frame = wp.media.frames.file_frame = wp.media({
                        title: 'Select an image to upload',
                        button: {text: 'Use this image'},
                        multiple: false
                    });
                }

                file_frame.off('select');
                file_frame.on('select', function () {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    var url = attachment.url;

                    if (size && attachment.hasOwnProperty('sizes') && attachment.sizes.hasOwnProperty(size)) {
                        url = attachment.sizes[size].url;
                    }

                    $wrapper.find('.js-sikshya__image_upload_value').val(url);
                });
                file_frame.open();
            });
        }

        , _initSocial: function () {
            var _this = this;

            $('.js-sikshya__social').each(function () {
                var container = $(this);
                var wrapper = container.closest('.js-sikshya__social-wrapper');
                var html = wrapper.find('.js-sikshya__social-item-tpl').html();

                var items = [];

                container.find('input[name="courses[social][image][]"]').each(function (index) {
                    if (index >= items.length) {
                        items.push({});
                    }
                    items[index].image = $(this).val();
                });
                container.find('input[name="courses[social][url][]"]').each(function (index) {
                    if (index >= items.length) {
                        items.push({});
                    }
                    items[index].url = $(this).val();
                });
                container.find('input[name="courses[social][label][]"]').each(function (index) {
                    if (index >= items.length) {
                        items.push({});
                    }
                    items[index].label = $(this).val();
                });
                container.find('input[name="courses[social][description][]"]').each(function (index) {
                    if (index >= items.length) {
                        items.push({});
                    }
                    items[index].description = $(this).val();
                });
                container.empty();

                $.each(items, function (index, item) {
                    itemHtml = _this._replaceAll(html, '{%image%}', _this._escapeHtml(item.image));
                    itemHtml = _this._replaceAll(itemHtml, '{%url%}', _this._escapeHtml(item.url));
                    itemHtml = _this._replaceAll(itemHtml, '{%label%}', _this._escapeHtml(item.label));
                    itemHtml = _this._replaceAll(itemHtml, '{%description%}', _this._escapeHtml(item.description));
                    container.append(itemHtml);
                });
            }).show();

            $('.js-sikshya__social-actions').show();
        }

        , _initPaymentSystems: function () {
            var _this = this;

            var wrapper = $('#ds24-existing');
            var product_content = $('#wmshp-ds24-product-content');
            var product_no_results = $('#wmshp-ds24-product-no-results');
            var product_spinner = $('#wmshp-ds24-product-spinner');
            product_spinner.parent().addClass('loading-content');
            product_content.hide();
            product_no_results.hide();

            $(document).on('change', '.js-sikshya__ds24-salespage-type', function (e) {
                var type = $(this).val();
                if (type === 'custom') {
                    $('.js-sikshya__ds24-salespage-type-custom').show();
                    $('.js-sikshya__ds24-salespage-type-default').hide();
                } else {
                    $('.js-sikshya__ds24-salespage-type-custom').hide();
                    $('.js-sikshya__ds24-salespage-type-default').show();
                }
            });

        }

        , _initAccordion: function (acc, sortable) {
            var _this = this;

            acc.accordion({
                heightStyle: "content",
                active: false,
                collapsible: true,
                activate: function (event, ui) {
                    ui.newPanel.find('.js-sikshya__editor:visible').each(function () {
                        _this._initEditor($(this).attr('id'));
                    });
                },
                beforeActivate: function (event, ui) {
                    if (event.originalEvent && event.originalEvent.target) {
                        var target = $(event.originalEvent.target);
                        if (target.is('.ui-icon-trash,a')) {
                            return false;
                        }
                    }
                    ui.oldPanel.find('.js-sikshya__editor').each(function () {
                        _this._removeEditor($(this).attr('id'));
                    });
                },
                /*icons: {
                    header: 'dashicons dashicons-menu',
                    activeHeader: 'ui-icon-triangle-1-n'
                },*/
                header: "> div > h3"
            });

            if (sortable) {
                acc.sortable({
                    axis: "y",
                    handle: "h3",
                    start: function (event, ui) { // turn TinyMCE off while sorting (if not, it won't work when resorted)
                        $(ui.item).find('.js-sikshya__editor').each(function () {
                            _this._removeEditor($(this).attr('id'));
                        });
                    },
                    stop: function (event, ui) {

                        // IE doesn't register the blur when sorting
                        // so trigger focusout handlers to remove .ui-state-focus
                        ui.item.children("h3").triggerHandler("focusout");
                        // Refresh accordion to handle new order
                        $(this).accordion("refresh");

                        $(ui.item).find('.js-sikshya__editor:visible').each(function () {
                            _this._initEditor($(this).attr('id'));
                        });

                        $(this).sortable("refresh");
                    }
                });
            }

            acc.accordion("refresh");
        }


        , _initAccordions: function () {
            this._accm = $(".js-sikshya__sections");
            this._initAccordion(this._accm, true);

            this._accl = $(".js-sikshya__sections-item_wrapper-lessons");
            this._initAccordion(this._accl, true);

            this._accq = $(".js-sikshya__wrapper-quizes");
            this._initAccordion(this._accq, false);

            this._accqq = $(".js-sikshya__wrapper-quiz-questions");
            this._initAccordion(this._accqq, true);

            return this;
        }

        , _replaceAll: function (str, toReplace, replaceWith) {
            return str ? str.split(toReplace).join(replaceWith) : '';
        }
        , _uniqid: function () {
            return '_' + Math.random().toString(36).substr(2, 9);
        }

        , _escapeHtml: function (text) {
            return text ? text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;') : '';
        }

        , _initEditor: function (editor_id) {
            if (tinyMCEPreInit.mceInit.hasOwnProperty(editor_id)) {
                tinymce.execCommand('mceRemoveEditor', true, editor_id);
                tinymce.execCommand('mceAddEditor', true, editor_id);
            } else {
                window.quicktags && quicktags({id: editor_id});

                tinyMCEPreInit.mceInit[editor_id] = tinymce.extend(
                    {},
                    tinyMCEPreInit.mceInit['content'],
                    {
                        toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,spellchecker,wp_adv',
                        selector: '#' + editor_id
                    }
                );

                try {
                    tinymce.init(tinyMCEPreInit.mceInit[editor_id]);
                } catch (e) {
                }
            }

            return this;
        }
        , _removeEditor: function (editor_id) {
            tinymce.execCommand('mceRemoveEditor', true, editor_id);
            return this;
        }
        , _getEditorHtml: function (editor_id, name) {
            var _this = this;
            var tpl = $('#sikshya-editor').html();
            tpl = this._replaceAll(tpl, '{%id%}', editor_id);
            tpl = this._replaceAll(tpl, '{%name%}', name);
            return tpl;
        }
        , _getLessonHtml: function (section_id, lesson_id, editor_html, quizes_html) {
            var _this = this;
            var tpl = $('#sikshya-accordion__lesson-template').html();
            tpl = this._replaceAll(tpl, '{%section_id%}', section_id);
            tpl = this._replaceAll(tpl, '{%lesson_id%}', lesson_id);
            tpl = this._replaceAll(tpl, '{%editor%}', editor_html);
            tpl = this._replaceAll(tpl, '{%quizes%}', quizes_html);
            return tpl;
        }, _getQuizHtml: function (section_id, lesson_id, quiz_id, editor_html) {
            var _this = this;
            var tpl = $('#sikshya-accordion__quiz-template').html();
            tpl = this._replaceAll(tpl, '{%section_id%}', section_id);
            tpl = this._replaceAll(tpl, '{%lesson_id%}', lesson_id);
            tpl = this._replaceAll(tpl, '{%quiz_id%}', quiz_id);
            tpl = this._replaceAll(tpl, '{%editor%}', editor_html);
            return tpl;
        }, _getQuizQuestionHtml: function (section_id, lesson_id, quiz_id, question_id) {
            var _this = this;
            var tpl = $('#sikshya-accordion__quiz-question-template').html();
            tpl = this._replaceAll(tpl, '{%section_id%}', section_id);
            tpl = this._replaceAll(tpl, '{%lesson_id%}', lesson_id);
            tpl = this._replaceAll(tpl, '{%quiz_id%}', quiz_id);
            tpl = this._replaceAll(tpl, '{%question_id%}', question_id);
            return tpl;
        }, _getQuizQuestionAnswerHtml: function (section_id, lesson_id, quiz_id, question_id, answer_id) {
            var _this = this;
            var tpl = $('#sikshya-accordion__quiz-question-answer-template').html();
            tpl = this._replaceAll(tpl, '{%section_id%}', section_id);
            tpl = this._replaceAll(tpl, '{%lesson_id%}', lesson_id);
            tpl = this._replaceAll(tpl, '{%quiz_id%}', quiz_id);
            tpl = this._replaceAll(tpl, '{%question_id%}', question_id);
            tpl = this._replaceAll(tpl, '{%answer_id%}', answer_id);
            return tpl;
        }, _getModuleHtml: function (section_id, lessons_html) {
            var _this = this;
            var tpl = $('#sikshya-accordion__section-template').html();
            tpl = this._replaceAll(tpl, '{%section_id%}', section_id);
            tpl = this._replaceAll(tpl, '{%lessons%}', lessons_html);
            return tpl;
        }
    };
    app.run();
});