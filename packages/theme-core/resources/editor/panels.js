/* JOOservices editor panels: renders the panels declared by PHP (window.jooPanels) in the document sidebar.
 * Plain browser JavaScript using the wp.* globals, no build step. */
(function (wp, config) {
  'use strict';
  if (!wp || !config || !Array.isArray(config)) {
    return;
  }
  const el = wp.element.createElement;
  const components = wp.components;
  const useSelect = wp.data.useSelect;
  const useDispatch = wp.data.useDispatch;

  /** Renders one control for a field definition. */
  function Field(props) {
    const field = props.field;
    const meta = props.meta;
    const value = meta[field.key];
    const update = function (next) {
      const change = {};
      change[field.key] = next;
      props.onChange(change);
    };
    if (field.ui === 'toggle') {
      return el(components.ToggleControl, {
        label: field.label,
        help: field.help || undefined,
        checked: !!value,
        onChange: update,
        __nextHasNoMarginBottom: true,
      });
    }
    if (field.ui === 'select' && Array.isArray(field.enum)) {
      return el(components.SelectControl, {
        label: field.label,
        help: field.help || undefined,
        value: value || field.enum[0],
        options: field.enum.map(function (option) {
          return { label: String(option), value: option };
        }),
        onChange: update,
        __nextHasNoMarginBottom: true,
      });
    }
    if (field.ui === 'number') {
      return el(components.__experimentalNumberControl || components.TextControl, {
        label: field.label,
        help: field.help || undefined,
        value: value === undefined ? 0 : value,
        min: field.min === null ? undefined : field.min,
        max: field.max === null ? undefined : field.max,
        onChange: function (next) {
          update(parseInt(next, 10) || 0);
        },
        __nextHasNoMarginBottom: true,
      });
    }
    if (field.ui === 'textarea') {
      return el(components.TextareaControl, {
        label: field.label,
        help: field.help || undefined,
        value: value || '',
        onChange: update,
        __nextHasNoMarginBottom: true,
      });
    }
    return el(components.TextControl, {
      label: field.label,
      help: field.help || undefined,
      value: value || '',
      maxLength: field.maxLength || undefined,
      onChange: update,
      __nextHasNoMarginBottom: true,
    });
  }

  /** One sidebar panel. */
  function Panel(props) {
    const panel = props.panel;
    const postType = useSelect(function (select) {
      return select('core/editor').getCurrentPostType();
    }, []);
    const meta = useSelect(function (select) {
      return select('core/editor').getEditedPostAttribute('meta') || {};
    }, []);
    const editPost = useDispatch('core/editor').editPost;
    const fields = panel.fields.filter(function (field) {
      return field.postTypes.indexOf(postType) !== -1;
    });
    if (!fields.length) {
      return null;
    }
    return el(
      wp.editPost.PluginDocumentSettingPanel,
      { name: 'joo-' + panel.id, title: panel.title, className: 'joo-panel joo-panel-' + panel.id },
      fields.map(function (field) {
        return el(Field, {
          key: field.key,
          field: field,
          meta: meta,
          onChange: function (change) {
            editPost({ meta: change });
          },
        });
      })
    );
  }

  wp.plugins.registerPlugin('joo-panels', {
    render: function () {
      return el(
        wp.element.Fragment,
        null,
        config.map(function (panel) {
          return el(Panel, { key: panel.id, panel: panel });
        })
      );
    },
  });
})(window.wp, window.jooPanels);
