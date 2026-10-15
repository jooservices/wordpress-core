/* JOOservices generic block editor: ServerSideRender preview and inspector controls generated from the
 * "x-joo" key of each block.json (window.jooBlocks). Plain browser JavaScript, no build step. */
(function (root) {
  'use strict';

  const TYPES = ['range', 'toggle', 'select', 'text', 'number', 'color'];

  /** Keeps only controls with a known type and an attribute the block declares. */
  function usableControls(controls, attributes) {
    return (controls || []).filter(function (control) {
      return (
        control &&
        TYPES.indexOf(control.type) !== -1 &&
        attributes &&
        Object.prototype.hasOwnProperty.call(attributes, control.attribute)
      );
    });
  }

  /** Props for the matching @wordpress/components control (a plain description, testable without WordPress). */
  function describeControl(control, value) {
    const base = { label: control.label || control.attribute, value: value };
    switch (control.type) {
      case 'range':
        return {
          component: 'RangeControl',
          props: Object.assign(base, { min: control.min || 0, max: control.max || 10, withInputField: true }),
        };
      case 'toggle':
        return { component: 'ToggleControl', props: { label: base.label, checked: !!value } };
      case 'select':
        return {
          component: 'SelectControl',
          props: Object.assign(base, {
            options: (control.options || []).map(function (option) {
              return { value: String(option[0]), label: String(option[1]) };
            }),
          }),
        };
      case 'number':
        return {
          component: 'TextControl',
          props: Object.assign(base, { type: 'number', value: value === undefined ? '' : String(value) }),
        };
      case 'color':
        return { component: 'ColorPalette', props: { value: value } };
      default:
        return {
          component: 'TextControl',
          props: Object.assign(base, { value: value === undefined ? '' : String(value) }),
        };
    }
  }

  /** Converts the raw value of a control to the attribute value. */
  function toAttribute(control, raw) {
    if (control.type === 'range' || control.type === 'number') {
      const n = parseInt(raw, 10);
      return isNaN(n) ? 0 : n;
    }
    if (control.type === 'toggle') {
      return !!raw;
    }
    return raw;
  }

  /** Debounce helper: later calls within `wait` ms replace earlier ones. */
  function debounce(fn, wait) {
    let timer = null;
    return function () {
      const args = arguments;
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(function () {
        timer = null;
        fn.apply(null, args);
      }, wait);
    };
  }

  const api = {
    usableControls: usableControls,
    describeControl: describeControl,
    toAttribute: toAttribute,
    debounce: debounce,
    TYPES: TYPES,
  };
  root.jooBlocksEditor = api;
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }

  const wp = root.wp;
  const blocks = root.jooBlocks;
  if (!wp || !wp.blocks || !Array.isArray(blocks)) {
    return;
  }
  const el = wp.element.createElement;
  const components = wp.components;

  blocks.forEach(function (definition) {
    const controls = usableControls(definition.controls, definition.attributes);
    wp.blocks.registerBlockType(definition.name, {
      edit: function (props) {
        const panel = controls.length
          ? el(
              wp.blockEditor.InspectorControls,
              null,
              el(
                components.PanelBody,
                { title: wp.i18n.__('Settings', 'joo-core') },
                controls.map(function (control) {
                  const description = describeControl(control, props.attributes[control.attribute]);
                  const Component =
                    components[description.component] || (wp.blockEditor && wp.blockEditor[description.component]);
                  if (!Component) {
                    return null;
                  }
                  return el(
                    Component,
                    Object.assign({ key: control.attribute, __nextHasNoMarginBottom: true }, description.props, {
                      onChange: function (raw) {
                        const next = {};
                        next[control.attribute] = toAttribute(control, raw);
                        props.setAttributes(next);
                      },
                      onChangeComplete: undefined,
                    })
                  );
                })
              )
            )
          : null;
        return el(
          'div',
          wp.blockEditor.useBlockProps({ 'aria-busy': 'false' }),
          panel,
          el(wp.serverSideRender, {
            block: definition.name,
            attributes: props.attributes,
            EmptyResponsePlaceholder: function () {
              return el('p', null, definition.name);
            },
          })
        );
      },
      save: function () {
        return null;
      },
    });
  });
})(typeof window !== 'undefined' ? window : globalThis);
