// @var sikshya

jQuery(function ($) {

    var Sikshya_Templates = {
        quizQuestionAnswer: function (question_id, answer_id) {
            var _this = this;
            var tpl = $('#sikshya-quiz-question-answer-template').html();
            tpl = this._replaceAll(tpl, '{%question_id%}', question_id);
            tpl = this._replaceAll(tpl, '{%answer_id%}', answer_id);
            return tpl;
        },
        quizQuestion: function (quiz_id, question_id) {
            var _this = this;
            var tpl = $('#sikshya-quiz-question-template').html();
            tpl = this._replaceAll(tpl, '{%quiz_id%}', quiz_id);
            tpl = this._replaceAll(tpl, '{%question_id%}', question_id);
            return tpl;
        }
        , _replaceAll: function (str, toReplace, replaceWith) {
            return str ? str.split(toReplace).join(replaceWith) : '';
        }

    };
    var Sikshya_Admin = {
        init: function () {
            this.template = Sikshya_Templates;
            this.initAnswer();
        },
        uniqid: function () {
            return '_' + Math.random().toString(36).substr(2, 9);
        },
        initAnswer() {
            var _this = this;
            /*add quiz question answer*/
            $(document).on('click', '.sik-add-question-answer-button', function (e) {
                e.preventDefault();
                var _that = $(this);
                var elem = _that.closest('div').find('.sikshya-quiz-question-answer-item-container').find('.js-sikshya__wrapper-quiz-question-answers');

                var section_id = _that.closest('.sik-box-data').attr('data-section-id');
                var lesson_id = _that.closest('.sik-box-data').attr('data-lesson-id');
                var quiz_id = _that.closest('.sik-box-data').attr('data-quiz-id');
                var question_id = _that.closest('.sik-box-data').attr('data-question-id');
                var answer_id = _this.uniqid();
                var answer_template = _this.template.quizQuestionAnswer(question_id, answer_id);
                elem.append(answer_template);

                _that.closest('div').find('.sikshya-quiz-question-answer-item-container').find('.js-sikshya_quiz-question-type').change();
            });
            /*add quiz question*/
            $(document).on('click', '.sik-add-question-button', function (e) {
                e.preventDefault();
                var _that = $(this);

                var elem = _that.closest('.sik-box-data-content').find('.sikshya-quiz-question-item-container');

                var quiz_id = _that.closest('.sik-admin-editor.admin-editor-sik-quiz-question').attr('data-quiz-id');

                var question_id = _this.uniqid();

                var question_template = _this.template.quizQuestion(quiz_id, question_id);

                elem.append(question_template);

            });


            /*change quiz question answer type*/
            $(document).on('click', '.sikshya-question-item-wrap .right-button span.sik-toggle', function (e) {
                _this.toggleQuestion($(this));
            });
            /*change quiz question answer type*/
            $(document).on('change', '.js-sikshya_quiz-question-type', function (e) {
                var value = $(this).val();

                var answers = $(this).closest('.sikshya-quiz-question-answer-item-container').find('.js-sikshya__wrapper-quiz-question-answers');
                var answersRows = $(this).closest('.sikshya-quiz-question-answer-item-container').find('.js-sikshya__quiz-question-answer_type');

                answersRows.hide();

                answersRows.filter('.js-sikshya__quiz-question-answer_type_' + value).show();

                if (value === 'single' || value === 'single_image') {
                    answers.find('.js-sikshya__quiz-question-answer_correct').attr('type', 'radio');
                } else if (value === 'multi' || value === 'multi_image') {
                    answers.find('.js-sikshya__quiz-question-answer_correct').attr('type', 'checkbox');
                }
            });
        },
        toggleQuestion: function (toggle_node) {
            var item_wrap = toggle_node.closest('.sikshya-question-item-wrap');
            var question_content = item_wrap.find('.question-content');
            if (toggle_node.hasClass('open')) {
                toggle_node.removeClass('dashicons-arrow-up-alt2').removeClass('open').addClass('dashicons-arrow-down-alt2')
                question_content.addClass('hide-if-js');
            } else {
                toggle_node.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2 open')
                question_content.removeClass('hide-if-js');
            }
        }

    };
    Sikshya_Admin.init();

});