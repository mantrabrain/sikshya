// @var SikshyaAdminData

(function ($) {

	var Sikshya_Admin_Course = {
		getSectionParams: function (id) {
			let node = $('#' + id);
			return {
				attach: '#' + id,
				width: 350,
				height: 250,
				closeButton: 'title',
				animation: false,
				title: 'Course Section',
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
						this.setTitle('<div class="ajax-sending">Loading...</div>');
					},
					complete: function () {
						this.setTitle('<div class="ajax-complete">Course Section</div>');
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
				width: 350,
				height: 250,
				closeButton: 'title',
				animation: false,
				title: 'Course Lesson',
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
						this.setTitle('<div class="ajax-sending">Loading...</div>');
					},
					complete: function () {
						this.setTitle('<div class="ajax-complete">Course Lesson</div>');
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
				width: 350,
				height: 250,
				closeButton: 'title',
				animation: false,
				title: 'Course Quiz',
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
						this.setTitle('<div class="ajax-sending">Loading...</div>');
					},
					complete: function () {
						this.setTitle('<div class="ajax-complete">Course Quiz</div>');
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
			this.loadLib();
			var sectionParams = this.getSectionParams('sik-add-new-section');
			this.initModal(sectionParams);
			this.initSortable();
			this.bind();


		},
		loadLib: function () {


			tippy('.sikshya-tippy-tooltip', {
				//content: "Hello World",

				allowHTML: true,
			});
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

			$(".sikshya-course-meta-curriculum-tab").sortable({

				stop: function (event, ui) {
					var tmpl = ui.item.closest('.sikshya-course-meta-curriculum-tab');
					var course_section_tmpl = tmpl.find('.course-section-template');
					var loop_start = 0;
					$.each(course_section_tmpl, function () {
						loop_start++;
						$(this).find('input.sikshya_section_order').val(loop_start);
					});
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


			$('body').on('click', '.remove-lesson-quiz', function (e) {
				e.preventDefault();
				var _el = $(this);
				var params = {
					title: 'Are you sure?',
					text: 'It will be removed from section & course!',
					button_text: 'Yes, remove it!',
					confirm_callback: function (result) {

						var lesson_quiz_data = {
							section_id: _el.closest('.course-section-template').attr('data-section-id'),
							post_id: _el.closest('.sikshya-card-item').find('.sikshya-course-content').val(),
							action: 'sikshya_remove_lesson_quiz_from_section',
							sikshya_nonce: SikshyaAdminData.remove_lesson_quiz_from_section_nonce
						};
						if (result.value) {
							$.ajax({
								url: SikshyaAdminData.ajax_url,
								type: 'POST',
								data: lesson_quiz_data,
								dataType: 'json',
								beforeSend: function () {
									Swal.fire(
										'Please wait.....',
										'System is processing your request',
										'info'
									)
								},
							}).done(function (response) {

								_el.closest('.sikshya-card-item').remove();
								Swal.fire(
									'Removed',
									response.message,
									'success'
								)

							}).fail(function () {
								Swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
							});

						}
					}

				};
				_that.swalConfirm(params);

			});

			$('body').on('click', '.remove-section', function (e) {
				e.preventDefault();
				var _el = $(this);
				var params = {
					title: 'Are you sure?',
					text: 'It will be removed from course!',
					button_text: 'Yes, remove it!',
					confirm_callback: function (result) {

						var section_quiz_data = {
							section_id: _el.closest('.course-section-template').attr('data-section-id'),
							course_id: SikshyaAdminData.course_id,
							action: 'sikshya_remove_section_from_course',
							sikshya_nonce: SikshyaAdminData.remove_section_from_course_nonce
						};
						if (result.value) {
							$.ajax({
								url: SikshyaAdminData.ajax_url,
								type: 'POST',
								data: section_quiz_data,
								dataType: 'json',
								beforeSend: function () {
									Swal.fire(
										'Please wait.....',
										'System is processing your request',
										'info'
									)
								},
							}).done(function (response) {

								_el.closest('.course-section-template').remove();
								Swal.fire(
									'Removed',
									response.message,
									'success'
								)

							}).fail(function () {
								Swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
							});

						}
					}

				};
				_that.swalConfirm(params);

			});

			// Requirement Repeator
			$('body').on('click', '.sikshya-add-requirements', function () {
				var parentNode = $(this).closest('.tab-content-item.requirements');
				var template = parentNode.find('#sikshya_course_requirements_template');
				parentNode.append(template.html());
			});
			$('body').on('click', '.sikshya-remove-requirements', function () {
				var parentNode = $(this).closest('.tab-content-item.requirements');
				if (parentNode.find('.sikshya_course_requirements').length > 1) {
					$(this).closest('.sikshya-field-wrap').remove();
				}
			});

			// Outcomes Repeater

			$('body').on('click', '.sikshya-add-outcomes', function () {
				var parentNode = $(this).closest('.tab-content-item.outcomes');
				var template = parentNode.find('#sikshya_course_outcomes_template');
				parentNode.append(template.html());
			});
			$('body').on('click', '.sikshya-remove-outcomes', function () {
				var parentNode = $(this).closest('.tab-content-item.outcomes');
				if (parentNode.find('.sikshya_course_outcomes').length > 1) {
					$(this).closest('.sikshya-field-wrap').remove();
				}
			});

		},
		bindSectionForm: function ($this) {
			var form = $this;
			var url = $this.attr('action');

			var order_num = $('.sikshya-course-meta-curriculum-tab').find('.course-section-template').length + 1;
			form.append('<input type="hidden" name="section_order" value="' + order_num + '"/>');
			$.ajax({
				type: "POST",
				url: url,
				data: form.serialize(), // serializes the form's elements.,
				beforeSend: function () {
					$this.closest('.jBox-container').find('.jBox-content').css('visibility', 'hidden');
					$this.closest('.jBox-container').append('<div class="jBox-spinner" style="transform: translateY(24.5px);"></div>');
				},
				complete: function () {
					$this.closest('.jBox-container').find('.jBox-content').css('visibility', 'visible');
					$this.closest('.jBox-container').find('.jBox-spinner').remove();
				},
				success: function (data) {

					$('.jBox-closeButton').trigger('click');
					if (typeof data.success == "undefined") {

						$('.sikshya-course-meta-curriculum-tab').append(data);
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
				beforeSend: function () {
					$this.closest('.jBox-container').find('.jBox-content').css('visibility', 'hidden');
					$this.closest('.jBox-container').append('<div class="jBox-spinner" style="transform: translateY(24.5px);"></div>');
				},
				complete: function () {
					$this.closest('.jBox-container').find('.jBox-content').css('visibility', 'visible');
					$this.closest('.jBox-container').find('.jBox-spinner').remove();
				},
				success: function (data) {

					$('.jBox-closeButton').trigger('click');

					if (typeof data.success == "undefined") {

						$('.sikshya-course-meta-curriculum-tab #course-section-template-' + section_id + ' .course-section-template-inner').append(data);
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
				data: form.serialize(), // serializes the form's elements.,
				beforeSend: function () {
					$this.closest('.jBox-container').find('.jBox-content').css('visibility', 'hidden');
					$this.closest('.jBox-container').append('<div class="jBox-spinner" style="transform: translateY(24.5px);"></div>');
				},
				complete: function () {
					$this.closest('.jBox-container').find('.jBox-content').css('visibility', 'visible');
					$this.closest('.jBox-container').find('.jBox-spinner').remove();
				},
				success: function (data) {

					$('.jBox-closeButton').trigger('click');
					if (typeof data.success == "undefined") {
						$('.sikshya-course-meta-curriculum-tab #course-section-template-' + section_id + ' .course-section-template-inner').append(data);
					}
				}
			});
		},
		swalConfirm: function (params) {
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
				allowOutsideClick: false
			}).then((result) => {
				if (typeof params.confirm_callback === 'function') {
					params.confirm_callback(result);
				}

			});
		}


	};

	$(document).ready(function () {
		Sikshya_Admin_Course.init();
	});

}(jQuery));
