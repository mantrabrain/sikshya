;
(function ($, window, document) {

    var hash = window.location.hash;

    function init(element) {
        
        if(window.location.hash) {
            $('html, body').scrollTop(0); 
        }
        
        hash && $('a[href="' + hash + '"][data-toggle="tab"]').tab('show');

        $('[data-toggle="tab"]', element).click(function (e) {
            window.location.hash = this.hash;
        });

    }


    $(window).on('load', function () {
        var container = '.js-tabs-hash';

        $(container).each(function (i, elem) {
            init($(elem));
        });
    });



})(jQuery, window, document);
