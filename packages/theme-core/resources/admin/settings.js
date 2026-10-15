/* JOOservices settings page enhancements (plain JavaScript, no build step). */
(function () {
  'use strict';

  // Media picker: only shown when the WordPress media frame is available.
  document.querySelectorAll('[data-joo-media]').forEach(function (wrapper) {
    const button = wrapper.querySelector('[data-joo-media-pick]');
    const input = wrapper.querySelector('input');
    const preview = wrapper.querySelector('.joo-media__preview');
    if (!button || !input || !window.wp || !window.wp.media || input.disabled) {
      return;
    }
    button.hidden = false;
    let frame = null;
    button.addEventListener('click', function () {
      if (!frame) {
        frame = window.wp.media({ title: button.textContent.trim(), library: { type: 'image' }, multiple: false });
        frame.on('select', function () {
          const attachment = frame.state().get('selection').first().toJSON();
          input.value = String(attachment.id);
          if (preview) {
            const size =
              attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            preview.src = size;
            preview.hidden = false;
          }
        });
      }
      frame.open();
    });
  });

  // Confirmation for destructive buttons, without window.confirm: uses a native <dialog>.
  document.querySelectorAll('[data-joo-confirm]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (typeof HTMLDialogElement !== 'function') {
        return; // The server still requires the nonce; submit directly.
      }
      event.preventDefault();
      const form = button.form;
      const dialog = document.createElement('dialog');
      const text = document.createElement('p');
      text.textContent = button.getAttribute('data-joo-confirm') || '';
      const ok = document.createElement('button');
      ok.type = 'button';
      ok.className = 'button button-primary';
      ok.textContent = button.textContent.trim();
      const cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'button';
      cancel.textContent = 'Cancel';
      dialog.append(text, ok, cancel);
      document.body.append(dialog);
      ok.addEventListener('click', function () {
        dialog.close();
        form.submit();
      });
      cancel.addEventListener('click', function () {
        dialog.close();
        dialog.remove();
      });
      dialog.showModal();
    });
  });
})();
