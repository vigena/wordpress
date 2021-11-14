'use strict';

(function($) {
  $(function() {
    if ($('.woosw_color_picker').length > 0) {
      $('.woosw_color_picker').wpColorPicker();
    }
  });

  $(document).on('click touch', '.woosw_action', function(e) {
    var pid = $(this).attr('data-pid');
    var key = $(this).attr('data-key');

    if ($('#woosw_popup').length < 1) {
      $('body').append('<div id=\'woosw_popup\'></div>');
    }

    $('#woosw_popup').html('Loading...');

    if (key && key != '') {
      $('#woosw_popup').
          dialog({
            minWidth: 460,
            title: 'Wishlist #' + key,
            dialogClass: 'wpc-dialog',
          });

      var data = {
        action: 'wishlist_quickview',
        nonce: woosw_vars.nonce,
        key: key,
      };

      $.post(ajaxurl, data, function(response) {
        $('#woosw_popup').html(response);
      });
    }

    if (pid && pid != '') {
      $('#woosw_popup').
          dialog({
            minWidth: 460,
            title: 'Product ID #' + pid,
            dialogClass: 'wpc-dialog',
          });

      var data = {
        action: 'wishlist_quickview',
        nonce: woosw_vars.nonce,
        pid: pid,
      };

      $.post(ajaxurl, data, function(response) {
        $('#woosw_popup').html(response);
      });
    }

    e.preventDefault();
  });
})(jQuery);