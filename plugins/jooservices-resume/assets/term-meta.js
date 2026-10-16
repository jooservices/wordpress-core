/* Media picker for the topic image field (category and tag screens). */
(function ($) {
  'use strict';
  $(document).on('click', '[data-joo-media-pick]', function (event) {
    event.preventDefault();
    const target = $('#' + this.getAttribute('data-joo-media-pick'));
    const frame = window.wp.media({ title: 'Topic image', library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      target.val(attachment.id);
    });
    frame.open();
  });
})(window.jQuery);
