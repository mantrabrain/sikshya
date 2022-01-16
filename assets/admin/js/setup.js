// @var
jQuery(function ($) {
    var sikshyaSetupImportDemoSampleData = {
        init: function () {
            var _this = this;
            $('button.sikshya-import-dummy-data').on('click', function (e) {
                e.preventDefault();
                _this.import($(this));

            });

        },
        import: function ($this) {
            console.log(sikshyaSetup);
            $.ajax({
                url: sikshyaSetup.ajax_url,
                type: 'POST',
                data: {
                    action: sikshyaSetup.import_action,
                    sikshya_nonce: sikshyaSetup.import_nonce,
                },
                beforeSend: function () {
                    Swal.fire({
                        title: 'Please wait.....',
                        text: 'System is processing your request',
                        showCancelButton: false, // There won't be any cancel button
                        showConfirmButton: false, // There won't be any confirm button
                        imageUrl: sikshyaSetup.loading_image,
                        imageWidth: 300
                    });
                },
            }).done(function (response) {
                if (typeof response.success != "undefined" && response.success) {
                    Swal.fire(
                        'Congratulations!',
                        'Import process successfully completed.',
                        'success'
                    );
                    $this.hide();
                } else {
                    var error_message = 'Something went wrong with ajax !';
                    if (typeof response.data != "undefined") {
                        error_message = response.data;
                    }
                    Swal.fire('Oops...', error_message, 'error');

                }

            }).fail(function () {
                Swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
            });
        }

    };
    $(document).ready(function () {

        sikshyaSetupImportDemoSampleData.init();
    });

});
