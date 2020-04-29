/*
$(document).ready(function(){
    $('#collapseJakob').on('hide.bs.collapse', function(){
        var hash = $(this).data('hash');
        var date = new Date;
        date.setDate(date.getDate() + 365);
        document.cookie = 'sikshya_dashboard_notice_hash=' + hash + '; expires=' + date.toUTCString() + ';  path=/';
    });

    // avatar
    $('#avatar_delete').click(function(e) {
        e.preventDefault();
        $('#avatar_delete_form').submit();
    });
    $('#avatar_upload_file').change(function(e) {
        e.preventDefault();
        $('#avatar_upload_form').submit();
    });
    $("#avatar_upload_form, #avatar_delete_form").submit(function() {
        $('.avatar').addClass('loading');

        var formData = new FormData(this);
        var onSuccess = function(url) { $('.avatar').removeClass('loading'); $('.avatar-url').attr('src', url); };
        var onError = function() { $('.avatar').removeClass('loading'); };

        if (typeof window.FormData !== 'function') {
            return;
        }

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (typeof data === 'object' && data.hasOwnProperty('success') && data.success) {
                    onSuccess(data.data);
                } else {
                    onError();
                }
            },
            error: function() {
                onError();
            }
        });

        return false;
    });
    $('#avatar_upload').click(function(e) {
        e.preventDefault();
        $('#avatar_upload_file').click();
    });

    //tabs
    $('.sikshya-tabs').each(function(){
        var block = $(this);
        var tabs = block.find('.tab');
        var links = block.find('a[data-res!=""]');
        tabs.slideUp(0);
        tabs.eq(0).slideDown(0);
        links.click(function(){
            var res = $(this).data('res');
            block.find('.tab[data-tab="'+res+'"]').slideDown(300);
            block.find('.tab[data-tab!="'+res+'"]').slideUp(300);
            return false;
        });
    });

    //share
    $('.fbShare').click(function(e) {
        e.preventDefault();

        var url = 'http://www.facebook.com/sharer.php?s=100';
        url += '&u=' + encodeURIComponent($(this).attr('href'));
        window.open(url, 'sikshya-share', 'height=450,width=760,resizable=0,toolbar=0,menubar=0,status=0,location=0,scrollbars=0');
    });
});

*/
