(function ($) {
  'use strict';

  function dismissNotice($notice) {
    var id = $notice.data('sikshya-notice-id');
    if (!id) {
      return;
    }

    $.ajax({
      url: (window.sikshyaWpMarketingNotices && window.sikshyaWpMarketingNotices.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'sikshya_dismiss_marketing_notice',
        nonce: window.sikshyaWpMarketingNotices && window.sikshyaWpMarketingNotices.nonce,
        notice_id: id,
      },
    });
  }

  $(document).on('click', '.sikshya-marketing-notice.is-dismissible .notice-dismiss', function () {
    var $notice = $(this).closest('.sikshya-marketing-notice');
    dismissNotice($notice);
  });

  $(document).on('click', '[data-sikshya-notice-dismiss]', function (e) {
    e.preventDefault();
    var $notice = $(this).closest('.sikshya-marketing-notice');
    dismissNotice($notice);
    $notice.fadeOut(200, function () {
      $(this).remove();
    });
  });
})(jQuery);
