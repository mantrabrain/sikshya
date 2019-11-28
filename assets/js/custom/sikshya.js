(function ($, document) {
    $(document).ready(function () {
        var info_blocks = $('.sikshya_info_block');
        if (info_blocks.length)
            info_blocks.each(function () {
                var block = $(this);
                $.ajax({
                    url: block.data('url'),
                    type: 'GET',
                    cache: false,
                    timeout: 0,
                    processData: false, // Don't process the files
                    contentType: false, // Set content type to false as jQuery will tell the server its a query string request
                    success: function (data) {
                        block.append(data);
                    },
                });
            });

        var notices = $('.sikshya-notice');
        if (notices.length)
            notices.each(function () {
                $(this).insertAfter($('#wpbody .wrap ' + ($('#wpbody .wrap hr.wp-header-end').length ? 'hr.wp-header-end' : 'h1')));
                $(this).show();
            });

        $('.sikshya-notice .sikshya-notice-actions button').on('click', function (e) {
            e.preventDefault();

            var button = $(this);

            $.ajax({
                url: $(this).data('dismiss-url'),
                type: 'GET',
                cache: false,
                timeout: 0,
                processData: false, // Don't process the files
                contentType: false, // Set content type to false as jQuery will tell the server its a query string request
                success: function (data) {
                    if (data == 'success')
                        button.closest('.sikshya-notice').hide();
                },
            });
        });

        $('.sikshya-notice.js-sikshya-notice-ajax .sikshya-notice-actions a').on('click', function (e) {
            e.preventDefault();

            var wrapper = $(this).closest('.sikshya-notice'),
                loader = wrapper.find('.sikshya-notice-actions .sikshya-notice-actions-loader');

            if (wrapper.hasClass('sikshya-ajax-go'))
                return;

            $.ajax({
                url: $(this).attr('href'),
                type: 'GET',
                cache: false,
                timeout: 0,
                processData: false, // Don't process the files
                contentType: false, // Set content type to false as jQuery will tell the server its a query string request
                success: function (data) {
                    data = JSON.parse(data);

                    if (data.status == 'success')
                        wrapper.find('.sikshya-notice-actions').hide();
                    else {
                        wrapper.removeClass('sikshya-ajax-go');
                        loader.hide();
                    }

                    wrapper.find('.sikshya-notice-text').html('<p class="sikshya-notice-text-type-' + data.status + '">' + data.message + '</p>');
                },
                beforeSend: function () {
                    wrapper.addClass('sikshya-ajax-go');
                    loader.show();
                }
            });
        });


        $('.course-tab-panel-curriculum .sikshya-section-list>li>a.item-link').on('click', function () {

            $(this).next('ul').toggle("slow", function () {
                // Animation complete.
            });
        });
        $('.sikshya-single-lesson-wrap .sikshya-sections-title').on('click', function () {

            if ($(this).find(".sikshya-single-lesson-topic-toggle").find('i.dashicons').hasClass('dashicons-plus')) {

                $(this).find(".sikshya-single-lesson-topic-toggle").find('i.dashicons').removeClass('dashicons-plus').addClass('dashicons-minus');
            } else {
                $(this).find(".sikshya-single-lesson-topic-toggle").find('i.dashicons').removeClass('dashicons-minus').addClass('dashicons-plus');
            }
            $(this).next('.sikshya-lessons-under-section').slideToggle("slow", function () {
                // Animation complete.
            });

        });
        $('.sikshya-topbar-item.sikshya-hide-sidebar-bar a.sikshya-lesson-sidebar-hide-bar').on("click", function (e) {
            e.preventDefault();
            var sidebar = $('body').find('.sikshya-lesson-sidebar');
            if (sidebar.hasClass('sikshya-hide')) {
                sidebar.removeClass('sikshya-hide');
            } else {
                sidebar.addClass('sikshya-hide');
            }
        });
        // Single Answer
        var sikshya_selected_answer_items = [];


        $('.sikshya-question-answer.sikshya-question-answer-loop-wrap .sikshya-answer-item').on('change', function () {

            var skip_form = $('form.sikshya-skip-question-form');
            var complete_form = $('form.sikshya-complete-question-form');
            var next_form = $('form.sikshya-next-question-form');
            var prev_form = $('form.sikshya-prev-question-form');
            var answer_id = $(this).attr('data-answer-id');

            if ($.inArray(answer_id, sikshya_selected_answer_items) === -1 && this.checked) {
                sikshya_selected_answer_items.push(answer_id);
            } else if ($.inArray(answer_id, sikshya_selected_answer_items) !== -1 && !this.checked) {
                sikshya_selected_answer_items.splice($.inArray(answer_id, sikshya_selected_answer_items), 1);

            }
            if (sikshya_selected_answer_items.length > 0) {
                var json_string = encodeURIComponent(JSON.stringify(sikshya_selected_answer_items));
                skip_form.find('input[name="sikshya_selected_answer"]').val(json_string);
                complete_form.find('input[name="sikshya_selected_answer"]').val(json_string);
                next_form.find('input[name="sikshya_selected_answer"]').val(json_string);
                prev_form.find('input[name="sikshya_selected_answer"]').val(json_string);

            }


        });
    });
})(jQuery);