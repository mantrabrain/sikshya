/**
 * Sikshya Gutenberg blocks — editor UI (dynamic blocks; server-side preview).
 *
 * Mirrors shortcodes: sikshya_courses, sikshya_login, sikshya_registration.
 *
 * @package Sikshya
 */
( function ( wp ) {
  if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.components || ! wp.element ) {
    return;
  }

  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var RangeControl = wp.components.RangeControl;
  var TextControl = wp.components.TextControl;
  var ToggleControl = wp.components.ToggleControl;
  var Placeholder = wp.components.Placeholder;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var __ = wp.i18n.__;
  var ServerSideRender = wp.serverSideRender;

  /**
   * Shared graduation-cap icon for editor placeholders (loading, error,
   * empty-response states) — these represent the Sikshya block system as
   * a whole, not any single block, so they keep the family mark.
   */
  var SIKSHYA_ICON = el(
    'svg',
    {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 24 24',
      width: 24,
      height: 24,
      'aria-hidden': true,
    },
    el( 'path', {
      fill: 'currentColor',
      d: 'M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm0 13.5L4.5 12.5 12 8.5l7.5 4-7.5 4zM5 18v2h14v-2H5z',
    } )
  );

  /*
   * Per-block icons. Previously every block was registered with the
   * shared SIKSHYA_ICON (graduation cap), so the block picker showed
   * three identical entries and users couldn't tell them apart at a
   * glance. Each block now gets a purpose-shaped mark that matches the
   * dashicon set on its block.json (which is what wordpress.org's
   * Block Directory scans for the plugin listing).
   */

  /** Grid of course cards — matches `grid-view` dashicon in block.json. */
  var COURSES_ICON = el(
    'svg',
    {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 24 24',
      width: 24,
      height: 24,
      'aria-hidden': true,
    },
    el( 'path', {
      fill: 'currentColor',
      d: 'M4 4h7v7H4V4zm0 9h7v7H4v-7zm9-9h7v7h-7V4zm0 9h7v7h-7v-7z',
    } )
  );

  /** Open padlock / sign-in — matches `unlock` dashicon in block.json. */
  var LOGIN_ICON = el(
    'svg',
    {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 24 24',
      width: 24,
      height: 24,
      'aria-hidden': true,
    },
    el( 'path', {
      fill: 'currentColor',
      d: 'M12 2a5 5 0 00-5 5v3H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V11a1 1 0 00-1-1H9V7a3 3 0 016 0h2a5 5 0 00-5-5zm0 12a2 2 0 012 2 2 2 0 01-1 1.72V19h-2v-1.28A2 2 0 0110 16a2 2 0 012-2z',
    } )
  );

  /** Person + plus badge — matches `id-alt` dashicon in block.json. */
  var REGISTRATION_ICON = el(
    'svg',
    {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 24 24',
      width: 24,
      height: 24,
      'aria-hidden': true,
    },
    el( 'path', {
      fill: 'currentColor',
      d: 'M15 12a4 4 0 100-8 4 4 0 000 8zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
    } )
  );

  function blockPreview( blockName, attributes ) {
    if ( ! ServerSideRender ) {
      return el( Placeholder, {
        icon: SIKSHYA_ICON,
        label: __( 'Sikshya', 'sikshya' ),
        instructions: __( 'Preview requires the ServerSideRender component.', 'sikshya' ),
      } );
    }

    return el(
      'div',
      {
        className: 'sikshya-block-editor-preview sik-f-scope',
        'data-sikshya-block-preview': blockName,
      },
      el( ServerSideRender, {
        block: blockName,
        attributes: attributes,
        EmptyResponsePlaceholder: function () {
          return el( Placeholder, {
            icon: SIKSHYA_ICON,
            label: __( 'Sikshya', 'sikshya' ),
            instructions: __( 'No output for these settings.', 'sikshya' ),
          } );
        },
        ErrorResponsePlaceholder: function ( props ) {
          return el( Placeholder, {
            icon: SIKSHYA_ICON,
            label: __( 'Sikshya preview error', 'sikshya' ),
            instructions: props && props.error ? String( props.error ) : '',
          } );
        },
        LoadingResponsePlaceholder: function () {
          return el( Placeholder, {
            icon: SIKSHYA_ICON,
            label: __( 'Loading Sikshya preview…', 'sikshya' ),
          } );
        },
      } )
    );
  }

  function coursesInspector( attributes, setAttributes ) {
    return el(
      InspectorControls,
      { key: 'inspector' },
      el(
        PanelBody,
        { title: __( 'Course list', 'sikshya' ), initialOpen: true },
        el( RangeControl, {
          label: __( 'Courses per page', 'sikshya' ),
          help: __( 'Minimum 1, maximum 50. Same as shortcode per_page.', 'sikshya' ),
          value: attributes.per_page,
          min: 1,
          max: 50,
          onChange: function ( v ) {
            setAttributes( { per_page: v || 9 } );
          },
        } ),
        el( SelectControl, {
          label: __( 'View', 'sikshya' ),
          value: attributes.view,
          options: [
            { label: __( 'Grid', 'sikshya' ), value: 'grid' },
            { label: __( 'List', 'sikshya' ), value: 'list' },
          ],
          onChange: function ( v ) {
            setAttributes( { view: v || 'grid' } );
          },
        } ),
        el( RangeControl, {
          label: __( 'Columns', 'sikshya' ),
          help: __( 'Use 3 for a fixed three-column grid; 0 for auto layout.', 'sikshya' ),
          value: attributes.columns,
          min: 0,
          max: 6,
          onChange: function ( v ) {
            setAttributes( { columns: typeof v === 'number' ? v : 0 } );
          },
        } ),
        el( TextControl, {
          label: __( 'Category slug', 'sikshya' ),
          help: __( 'Course category taxonomy slug.', 'sikshya' ),
          value: attributes.category,
          onChange: function ( v ) {
            setAttributes( { category: v || '' } );
          },
        } ),
        el( TextControl, {
          label: __( 'Tag slug', 'sikshya' ),
          value: attributes.tag,
          onChange: function ( v ) {
            setAttributes( { tag: v || '' } );
          },
        } ),
        el( TextControl, {
          label: __( 'Search', 'sikshya' ),
          value: attributes.search,
          onChange: function ( v ) {
            setAttributes( { search: v || '' } );
          },
        } ),
        el( SelectControl, {
          label: __( 'Order by', 'sikshya' ),
          value: attributes.orderby,
          options: [
            { label: __( 'Date', 'sikshya' ), value: 'date' },
            { label: __( 'Title', 'sikshya' ), value: 'title' },
            { label: __( 'Price', 'sikshya' ), value: 'price' },
          ],
          onChange: function ( v ) {
            setAttributes( { orderby: v || 'date' } );
          },
        } ),
        el( SelectControl, {
          label: __( 'Order', 'sikshya' ),
          value: attributes.order,
          options: [
            { label: __( 'Descending', 'sikshya' ), value: 'desc' },
            { label: __( 'Ascending', 'sikshya' ), value: 'asc' },
          ],
          onChange: function ( v ) {
            setAttributes( { order: v || 'desc' } );
          },
        } ),
        el( ToggleControl, {
          label: __( 'Show pagination', 'sikshya' ),
          help: __( 'Uses query arg sikshya_courses_page on the page URL.', 'sikshya' ),
          checked: !! attributes.pagination,
          onChange: function ( v ) {
            setAttributes( { pagination: !! v } );
          },
        } )
      ),
      el(
        PanelBody,
        { title: __( 'Shortcode', 'sikshya' ), initialOpen: false },
        el( 'p', { className: 'description' }, __( 'Equivalent shortcode:', 'sikshya' ) ),
        el( 'code', null, buildCoursesShortcode( attributes ) )
      )
    );
  }

  function buildCoursesShortcode( attrs ) {
    var parts = ['sikshya_courses'];
    var map = {
      per_page: attrs.per_page,
      columns: attrs.columns,
      view: attrs.view,
      category: attrs.category,
      tag: attrs.tag,
      search: attrs.search,
      orderby: attrs.orderby,
      order: attrs.order,
      pagination: attrs.pagination ? '1' : '0',
    };
    Object.keys( map ).forEach( function ( key ) {
      var val = map[ key ];
      if ( val === '' || val === null || typeof val === 'undefined' ) {
        return;
      }
      if ( key === 'per_page' && val === 9 ) {
        return;
      }
      if ( key === 'columns' && ( val === 0 || val === '0' ) ) {
        return;
      }
      if ( key === 'view' && val === 'grid' ) {
        return;
      }
      if ( key === 'orderby' && val === 'date' ) {
        return;
      }
      if ( key === 'order' && val === 'desc' ) {
        return;
      }
      if ( key === 'pagination' && val === '1' ) {
        return;
      }
      parts.push( key + '="' + String( val ).replace( /"/g, '' ) + '"' );
    } );
    return '[' + parts.join( ' ' ) + ']';
  }

  function authRedirectInspector( attributes, setAttributes, label ) {
    return el(
      InspectorControls,
      { key: 'inspector' },
      el(
        PanelBody,
        { title: label, initialOpen: true },
        el( TextControl, {
          label: __( 'Redirect after success', 'sikshya' ),
          help: __( 'Optional URL (absolute or relative). Same as shortcode redirect_to.', 'sikshya' ),
          value: attributes.redirect_to,
          onChange: function ( v ) {
            setAttributes( { redirect_to: v || '' } );
          },
        } )
      )
    );
  }

  var coursesShortcodeTransform = {
    type: 'shortcode',
    tag: 'sikshya_courses',
    attributes: {
      per_page: { type: 'number', shortcode: 'per_page', default: 9 },
      columns: { type: 'number', shortcode: 'columns', default: 0 },
      view: { type: 'string', shortcode: 'view', default: 'grid' },
      category: { type: 'string', shortcode: 'category', default: '' },
      tag: { type: 'string', shortcode: 'tag', default: '' },
      search: { type: 'string', shortcode: 'search', default: '' },
      orderby: { type: 'string', shortcode: 'orderby', default: 'date' },
      order: { type: 'string', shortcode: 'order', default: 'desc' },
      pagination: {
        type: 'string',
        shortcode: 'pagination',
        default: '1',
      },
    },
  };

  registerBlockType( 'sikshya/courses', {
    icon: COURSES_ICON,
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();

      return el(
        Fragment,
        null,
        coursesInspector( attributes, setAttributes ),
        el( 'div', blockProps, blockPreview( 'sikshya/courses', attributes ) )
      );
    },
    save: function () {
      return null;
    },
    transforms: {
      from: [ coursesShortcodeTransform ],
    },
  } );

  registerBlockType( 'sikshya/login', {
    icon: LOGIN_ICON,
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();

      return el(
        Fragment,
        null,
        authRedirectInspector( attributes, setAttributes, __( 'Login', 'sikshya' ) ),
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: __( 'Shortcode', 'sikshya' ), initialOpen: false },
            el( 'code', null, '[sikshya_login' + ( attributes.redirect_to ? ' redirect_to="' + attributes.redirect_to + '"' : '' ) + ']' )
          )
        ),
        el( 'div', blockProps, blockPreview( 'sikshya/login', attributes ) )
      );
    },
    save: function () {
      return null;
    },
    transforms: {
      from: [
        {
          type: 'shortcode',
          tag: 'sikshya_login',
          attributes: {
            redirect_to: { type: 'string', shortcode: 'redirect_to', default: '' },
          },
        },
      ],
    },
  } );

  registerBlockType( 'sikshya/registration', {
    icon: REGISTRATION_ICON,
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();

      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          { key: 'inspector' },
          el(
            PanelBody,
            { title: __( 'Registration', 'sikshya' ), initialOpen: true },
            el( SelectControl, {
              label: __( 'Registration type', 'sikshya' ),
              help: __(
                'Instructor creates a student account and a pending teaching application.',
                'sikshya'
              ),
              value: attributes.type,
              options: [
                { label: __( 'Student', 'sikshya' ), value: 'student' },
                { label: __( 'Instructor (apply to teach)', 'sikshya' ), value: 'instructor' },
              ],
              onChange: function ( v ) {
                setAttributes( { type: v || 'student' } );
              },
            } ),
            el( TextControl, {
              label: __( 'Redirect after success', 'sikshya' ),
              value: attributes.redirect_to,
              onChange: function ( v ) {
                setAttributes( { redirect_to: v || '' } );
              },
            } )
          ),
          el(
            PanelBody,
            { title: __( 'Shortcode', 'sikshya' ), initialOpen: false },
            el(
              'code',
              null,
              '[sikshya_registration type="' +
                ( attributes.type || 'student' ) +
                '"' +
                ( attributes.redirect_to ? ' redirect_to="' + attributes.redirect_to + '"' : '' ) +
                ']'
            )
          )
        ),
        el( 'div', blockProps, blockPreview( 'sikshya/registration', attributes ) )
      );
    },
    save: function () {
      return null;
    },
    transforms: {
      from: [
        {
          type: 'shortcode',
          tag: 'sikshya_registration',
          attributes: {
            type: { type: 'string', shortcode: 'type', default: 'student' },
            redirect_to: { type: 'string', shortcode: 'redirect_to', default: '' },
          },
        },
      ],
    },
  } );
} )( window.wp );
