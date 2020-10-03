// @var

jQuery(function ($) {
	var sikshyaImporter = {
		init: function () {

			var form = $('form.sikshya-import-course-form');

			var _this = this;
			form.on('submit', function (e) {
				e.preventDefault();
				var formData = new FormData(this);
				_this.import_course(formData, $(this));

			});

		},
		import_course: function (formData, form) {

			$.ajax({
				url: sikshyaImporterData.ajax_url,
				type: 'POST',
				data: formData,
				contentType: false,
				cache: false,
				processData: false,
				beforeSend: function () {
					form.trigger("reset");
					Swal.fire({
						title: 'Please wait.....',
						text: 'System is processing your request',
						showCancelButton: false, // There won't be any cancel button
						showConfirmButton: false, // There won't be any confirm button
						imageUrl: sikshyaImporterData.loading_image,
						imageWidth: 300
					});
				},
			}).done(function (response) {


				Swal.fire(
					'Congratulations!',
					'Import process successfully completed.',
					'success'
				);

			}).fail(function () {
				Swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
			});
		}

	};
	$(document).ready(function () {

		sikshyaImporter.init();
	});

});
