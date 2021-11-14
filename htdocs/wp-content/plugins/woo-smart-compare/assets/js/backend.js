'use strict';

(function($) {
  $(function() {
    $('.woosc_color_picker').wpColorPicker();

    $('.woosc-fields').sortable({
      handle: '.label',
    });

    $('.woosc-attributes').sortable({
      handle: '.label',
    });
  });
})(jQuery);