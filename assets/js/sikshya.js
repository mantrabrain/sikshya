(function ($, document) {
	$(document).ready(function () {

		var SikshyaFrontend = {
			init: function () {
 				this.initEvents();
			},
			initEvents: function () {

				var _this = this;
				$('.sikshya-topbar-item.sikshya-hide-sidebar-bar a.sikshya-lesson-sidebar-hide-bar').on("click", function (e) {
					e.preventDefault();
					var sidebar = $('body').find('.sikshya-lesson-sidebar');
					if (sidebar.hasClass('sikshya-hide')) {
						sidebar.removeClass('sikshya-hide');
					} else {
						sidebar.addClass('sikshya-hide');
					}
				});
				$('.lecture-group-title').on('click', function () {

					var wrap = $(this).closest('.lecture-group-wrapper');
					if (wrap.find(".lecture-list").hasClass('show')) {
						wrap.find(".lecture-list").removeClass('show');
						$(this).find(".icon").removeClass('dashicons-minus').addClass('dashicons-plus');
					} else {
						wrap.find(".lecture-list").addClass('show');
						$(this).find(".icon").removeClass('dashicons-plus').addClass('dashicons-minus');
					}
					wrap.find(".lecture-list").slideToggle("slow", function () {
						// Animation complete.
					});

				});
				$('.sikshya-question-answer.sikshya-question-answer-loop-wrap .sikshya-answer-item').on('change', function () {

					var skip_form = $('form.sikshya-skip-question-form');
					var complete_form = $('form.sikshya-complete-question-form');
					var next_form = $('form.sikshya-next-question-form');
					var prev_form = $('form.sikshya-prev-question-form');
					var answer_id = $(this).attr('data-answer-id');
					var sikshya_selected_answer_items = [];

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

				$('input[type="checkbox"][name="sikshya_change_password"]').on('change', function () {
					var password_change_wrap = $(this).closest('.sikshya-change-password');

					if (this.checked) {
						password_change_wrap.find('input[type="password"].sikshya-password-field').removeAttr('disabled');
					} else {
						password_change_wrap.find('input[type="password"].sikshya-password-field').attr('disabled', 'disabled');
					}

				});
				var video_content = $('.video-content').html();
				var title = $('#CoursePreviewModal').attr('data-modal-title');
				new jBox('Modal', {
					attach: '#CoursePreviewModal',
					width: 800,
					height: 500,
					blockScroll: false,
					draggable: 'title',
					closeButton: true,
					content: video_content,
					title: title,
					overlay: false,
					reposition: false,
					repositionOnOpen: false
				});

				$('body').on('click', '.sikshya-update-cart', function (e) {

					e.preventDefault();

					var form = $(this).closest('form');
					var form_data = form.serialize();

					_this.update_cart(form_data, $(this));
				});
			},
			update_cart: function (form_data, update_cart_button) {

				debugger;
			}
		};

		SikshyaFrontend.init();
	});
})(jQuery);
