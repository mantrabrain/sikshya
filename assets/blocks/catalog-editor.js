/**
 * Sikshya catalog blocks — editor UI.
 *
 * sikshya/course-grid, sikshya/course-categories, sikshya/course-search.
 *
 * Loaded from the same `sikshya-blocks-editor` handle as editor.js, as a
 * separate IIFE so the older three blocks and these three never share state.
 *
 * Previews are ServerSideRender, like the rest of the family: the card markup
 * lives in a PHP partial a theme can override, so a JS-rendered preview would
 * show the plugin's card while the front end showed the theme's.
 *
 * @package Sikshya
 * @since 1.0.21
 */
( function ( wp ) {
  if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.components || ! wp.element ) {
    return;
  }

  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var PanelBody = wp.components.PanelBody;
  var PanelRow = wp.components.PanelRow;
  var SelectControl = wp.components.SelectControl;
  var RangeControl = wp.components.RangeControl;
  var TextControl = wp.components.TextControl;
  var ToggleControl = wp.components.ToggleControl;
  var FormTokenField = wp.components.FormTokenField;
  var Placeholder = wp.components.Placeholder;
  var Notice = wp.components.Notice;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var __ = wp.i18n.__;
  var ServerSideRender = wp.serverSideRender;
  var apiFetch = wp.apiFetch;

  var GRID_ICON = el(
    'svg',
    { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
    el( 'path', {
      fill: 'currentColor',
      d: 'M3 4h8v6H3V4zm0 10h8v6H3v-6zm10-10h8v6h-8V4zm0 10h8v6h-8v-6z',
    } )
  );

  var CATEGORY_ICON = el(
    'svg',
    { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
    el( 'path', {
      fill: 'currentColor',
      d: 'M4 4h6l2 2h8v12H4V4zm2 2v10h12V8h-6.83l-2-2H6z',
    } )
  );

  var SEARCH_ICON = el(
    'svg',
    { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
    el( 'path', {
      fill: 'currentColor',
      d: 'M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z',
    } )
  );

  /**
   * Terms for a taxonomy, fetched once per taxonomy per editor session.
   *
   * The `core` data store can resolve dynamic taxonomy entities, but only once
   * it has loaded /wp/v2/taxonomies; asking apiFetch directly means the picker
   * fills on first render instead of after a round trip we cannot observe.
   *
   * @param {string} taxonomy REST base.
   * @return {{terms: Array, loading: boolean, error: string}} Fetch state.
   */
  function useTerms( taxonomy ) {
    var state = useState( { terms: [], loading: true, error: '' } );
    var value = state[ 0 ];
    var setValue = state[ 1 ];

    useEffect(
      function () {
        var cancelled = false;

        if ( ! apiFetch ) {
          setValue( { terms: [], loading: false, error: '' } );
          return;
        }

        setValue( { terms: [], loading: true, error: '' } );

        apiFetch( {
          path: '/wp/v2/' + taxonomy + '?per_page=100&orderby=name&order=asc&_fields=id,name',
        } )
          .then( function ( terms ) {
            if ( cancelled ) {
              return;
            }
            setValue( {
              terms: Array.isArray( terms ) ? terms : [],
              loading: false,
              error: '',
            } );
          } )
          .catch( function ( error ) {
            if ( cancelled ) {
              return;
            }
            setValue( {
              terms: [],
              loading: false,
              error: error && error.message ? error.message : __( 'Could not load terms.', 'sikshya' ),
            } );
          } );

        return function () {
          // Guards the setState-after-unmount warning when the block is
          // removed while the request is still in flight.
          cancelled = true;
        };
      },
      [ taxonomy ]
    );

    return value;
  }

  /**
   * FormTokenField over a taxonomy, storing term IDs.
   *
   * @param {Object} props Control props.
   * @return {Object} Element.
   */
  function TermTokenField( props ) {
    var state = useTerms( props.taxonomy );
    var terms = state.terms;
    var selected = Array.isArray( props.value ) ? props.value : [];

    var byId = {};
    var byName = {};
    terms.forEach( function ( term ) {
      byId[ term.id ] = term.name;
      // Lower-cased key: FormTokenField hands back whatever the user typed.
      byName[ String( term.name ).toLowerCase() ] = term.id;
    } );

    if ( state.error ) {
      return el( Notice, { status: 'warning', isDismissible: false }, state.error );
    }

    return el( FormTokenField, {
      label: props.label,
      help: props.help,
      /*
       * An id with no matching term still renders as its number rather than
       * disappearing: a category deleted after the block was saved must stay
       * visible and removable, not silently vanish from the UI while the
       * query still filters on it.
       */
      value: selected.map( function ( id ) {
        return byId[ id ] || String( id );
      } ),
      suggestions: terms.map( function ( term ) {
        return term.name;
      } ),
      __experimentalExpandOnFocus: true,
      onChange: function ( tokens ) {
        var ids = [];
        tokens.forEach( function ( token ) {
          var name = typeof token === 'string' ? token : token.value;
          var id = byName[ String( name ).toLowerCase() ];
          if ( ! id && /^\d+$/.test( String( name ) ) ) {
            id = parseInt( name, 10 );
          }
          if ( id && ids.indexOf( id ) === -1 ) {
            ids.push( id );
          }
        } );
        props.onChange( ids );
      },
    } );
  }

  /**
   * Server preview shared by the three blocks.
   *
   * @param {string} blockName Block name.
   * @param {Object} attributes Block attributes.
   * @return {Object} Element.
   */
  function preview( blockName, attributes ) {
    return el( ServerSideRender, {
      block: blockName,
      attributes: attributes,
      EmptyResponsePlaceholder: function () {
        return el( Placeholder, {
          icon: GRID_ICON,
          label: __( 'Nothing to show yet', 'sikshya' ),
          instructions: __(
            'No courses match these settings. Publish a course, or widen the filters.',
            'sikshya'
          ),
        } );
      },
      ErrorResponsePlaceholder: function ( props ) {
        return el( Placeholder, {
          icon: GRID_ICON,
          label: __( 'Preview error', 'sikshya' ),
          instructions: props && props.error ? String( props.error ) : '',
        } );
      },
      LoadingResponsePlaceholder: function () {
        return el( Placeholder, { icon: GRID_ICON, label: __( 'Loading preview…', 'sikshya' ) } );
      },
    } );
  }

  /* ---------------------------------------------------------------------
   * sikshya/course-grid
   * ------------------------------------------------------------------ */

  registerBlockType( 'sikshya/course-grid', {
    icon: GRID_ICON,
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps ? useBlockProps() : {};

      function set( key ) {
        return function ( value ) {
          var next = {};
          next[ key ] = value;
          setAttributes( next );
        };
      }

      function toggle( key, label, help ) {
        return el( ToggleControl, {
          label: label,
          help: help,
          checked: !! attributes[ key ],
          onChange: set( key ),
        } );
      }

      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          { key: 'inspector' },

          el(
            PanelBody,
            { title: __( 'Layout', 'sikshya' ), initialOpen: true },
            el( SelectControl, {
              label: __( 'Layout', 'sikshya' ),
              value: attributes.layout,
              options: [
                { label: __( 'Grid', 'sikshya' ), value: 'grid' },
                { label: __( 'List', 'sikshya' ), value: 'list' },
              ],
              onChange: set( 'layout' ),
            } ),
            el( RangeControl, {
              label: __( 'Columns (desktop)', 'sikshya' ),
              value: attributes.columns,
              min: 1,
              max: 6,
              onChange: function ( v ) {
                setAttributes( { columns: typeof v === 'number' ? v : 3 } );
              },
            } ),
            el( RangeControl, {
              label: __( 'Columns (tablet)', 'sikshya' ),
              value: attributes.columnsTablet,
              min: 1,
              max: 6,
              onChange: function ( v ) {
                setAttributes( { columnsTablet: typeof v === 'number' ? v : 2 } );
              },
            } ),
            el( RangeControl, {
              label: __( 'Columns (mobile)', 'sikshya' ),
              value: attributes.columnsMobile,
              min: 1,
              max: 6,
              onChange: function ( v ) {
                setAttributes( { columnsMobile: typeof v === 'number' ? v : 1 } );
              },
            } ),
            el( SelectControl, {
              label: __( 'Card style', 'sikshya' ),
              value: attributes.cardStyle,
              options: [
                { label: __( 'Card', 'sikshya' ), value: 'card' },
                { label: __( 'Bordered', 'sikshya' ), value: 'bordered' },
                { label: __( 'Minimal', 'sikshya' ), value: 'minimal' },
              ],
              onChange: set( 'cardStyle' ),
            } ),
            el( SelectControl, {
              label: __( 'Image ratio', 'sikshya' ),
              value: attributes.aspectRatio,
              options: [
                { label: __( '16:9', 'sikshya' ), value: '16-9' },
                { label: __( '4:3', 'sikshya' ), value: '4-3' },
                { label: __( '3:2', 'sikshya' ), value: '3-2' },
                { label: __( 'Square', 'sikshya' ), value: '1-1' },
                { label: __( 'Original', 'sikshya' ), value: 'auto' },
              ],
              onChange: set( 'aspectRatio' ),
            } )
          ),

          el(
            PanelBody,
            { title: __( 'Which courses', 'sikshya' ), initialOpen: false },
            el( RangeControl, {
              label: __( 'Courses to show', 'sikshya' ),
              value: attributes.perPage,
              min: 1,
              max: 48,
              onChange: function ( v ) {
                setAttributes( { perPage: typeof v === 'number' ? v : 6 } );
              },
            } ),
            el( SelectControl, {
              label: __( 'Order by', 'sikshya' ),
              value: attributes.orderBy,
              options: [
                { label: __( 'Newest', 'sikshya' ), value: 'date' },
                { label: __( 'Title', 'sikshya' ), value: 'title' },
                { label: __( 'Price', 'sikshya' ), value: 'price' },
                { label: __( 'Menu order', 'sikshya' ), value: 'menu_order' },
                { label: __( 'Most enrolled', 'sikshya' ), value: 'popular' },
                { label: __( 'Random', 'sikshya' ), value: 'rand' },
              ],
              onChange: set( 'orderBy' ),
            } ),
            el( SelectControl, {
              label: __( 'Direction', 'sikshya' ),
              value: attributes.order,
              options: [
                { label: __( 'Descending', 'sikshya' ), value: 'desc' },
                { label: __( 'Ascending', 'sikshya' ), value: 'asc' },
              ],
              onChange: set( 'order' ),
            } ),
            el( TermTokenField, {
              label: __( 'Categories', 'sikshya' ),
              help: __( 'Leave empty for all. Subcategories are included.', 'sikshya' ),
              taxonomy: 'sikshya_course_category',
              value: attributes.categories,
              onChange: set( 'categories' ),
            } ),
            el( TermTokenField, {
              label: __( 'Tags', 'sikshya' ),
              taxonomy: 'sikshya_course_tag',
              value: attributes.tags,
              onChange: set( 'tags' ),
            } ),
            el( SelectControl, {
              label: __( 'Price', 'sikshya' ),
              value: attributes.priceFilter,
              options: [
                { label: __( 'Any', 'sikshya' ), value: 'any' },
                { label: __( 'Free only', 'sikshya' ), value: 'free' },
                { label: __( 'Paid only', 'sikshya' ), value: 'paid' },
              ],
              onChange: set( 'priceFilter' ),
            } ),
            toggle(
              'enrolledOnly',
              __( 'Only courses the visitor is enrolled in', 'sikshya' ),
              __( 'Signed-out visitors see the empty message.', 'sikshya' )
            ),
            toggle(
              'excludeEnrolled',
              __( 'Hide courses the visitor is already enrolled in', 'sikshya' )
            ),
            el( TextControl, {
              label: __( 'Specific course IDs', 'sikshya' ),
              help: __( 'Comma separated. Overrides the filters above and keeps this order.', 'sikshya' ),
              value: ( attributes.includeIds || [] ).join( ', ' ),
              onChange: function ( v ) {
                setAttributes( { includeIds: parseIds( v ) } );
              },
            } ),
            el( TextControl, {
              label: __( 'Exclude course IDs', 'sikshya' ),
              value: ( attributes.excludeIds || [] ).join( ', ' ),
              onChange: function ( v ) {
                setAttributes( { excludeIds: parseIds( v ) } );
              },
            } )
          ),

          el(
            PanelBody,
            { title: __( 'Card contents', 'sikshya' ), initialOpen: false },
            toggle( 'showThumbnail', __( 'Featured image', 'sikshya' ) ),
            toggle( 'showCategory', __( 'Category', 'sikshya' ) ),
            toggle( 'showDifficulty', __( 'Difficulty badge', 'sikshya' ) ),
            toggle( 'showInstructor', __( 'Instructor', 'sikshya' ) ),
            toggle( 'showRating', __( 'Rating', 'sikshya' ) ),
            toggle( 'showExcerpt', __( 'Excerpt', 'sikshya' ) ),
            attributes.showExcerpt
              ? el( RangeControl, {
                  label: __( 'Excerpt words', 'sikshya' ),
                  value: attributes.excerptLength,
                  min: 4,
                  max: 60,
                  onChange: function ( v ) {
                    setAttributes( { excerptLength: typeof v === 'number' ? v : 18 } );
                  },
                } )
              : null,
            toggle( 'showMeta', __( 'Lessons, quizzes and duration', 'sikshya' ) ),
            toggle( 'showProgress', __( 'Progress bar for enrolled courses', 'sikshya' ) ),
            toggle( 'showPrice', __( 'Price', 'sikshya' ) ),
            toggle( 'showButton', __( 'Button', 'sikshya' ) ),
            attributes.showButton
              ? el( TextControl, {
                  label: __( 'Button label', 'sikshya' ),
                  help: __( 'Leave empty to adapt to the visitor (Start free, Continue learning…).', 'sikshya' ),
                  value: attributes.buttonLabel,
                  onChange: set( 'buttonLabel' ),
                } )
              : null
          ),

          el(
            PanelBody,
            { title: __( 'Pagination', 'sikshya' ), initialOpen: false },
            toggle(
              'pagination',
              __( 'Show pagination', 'sikshya' ),
              __( 'Adds page links below the grid.', 'sikshya' )
            ),
            attributes.pagination
              ? el( RangeControl, {
                  label: __( 'Grid number', 'sikshya' ),
                  help: __( 'Give each paginated grid on a page a different number so they page independently.', 'sikshya' ),
                  value: attributes.queryId,
                  min: 0,
                  max: 20,
                  onChange: function ( v ) {
                    setAttributes( { queryId: typeof v === 'number' ? v : 0 } );
                  },
                } )
              : null,
            el(
              PanelRow,
              null,
              el( TextControl, {
                label: __( 'Empty message', 'sikshya' ),
                value: attributes.emptyMessage,
                onChange: set( 'emptyMessage' ),
              } )
            )
          )
        ),

        el( 'div', blockProps, preview( 'sikshya/course-grid', attributes ) )
      );
    },
    save: function () {
      return null;
    },
  } );

  /* ---------------------------------------------------------------------
   * sikshya/course-categories
   * ------------------------------------------------------------------ */

  registerBlockType( 'sikshya/course-categories', {
    icon: CATEGORY_ICON,
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps ? useBlockProps() : {};

      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          { key: 'inspector' },
          el(
            PanelBody,
            { title: __( 'Categories', 'sikshya' ), initialOpen: true },
            el( SelectControl, {
              label: __( 'Style', 'sikshya' ),
              value: attributes.displayStyle,
              options: [
                { label: __( 'Tiles', 'sikshya' ), value: 'tiles' },
                { label: __( 'Pills', 'sikshya' ), value: 'pills' },
                { label: __( 'List', 'sikshya' ), value: 'list' },
              ],
              onChange: function ( v ) {
                setAttributes( { displayStyle: v || 'tiles' } );
              },
            } ),
            attributes.displayStyle === 'tiles'
              ? el( RangeControl, {
                  label: __( 'Columns', 'sikshya' ),
                  value: attributes.columns,
                  min: 1,
                  max: 6,
                  onChange: function ( v ) {
                    setAttributes( { columns: typeof v === 'number' ? v : 4 } );
                  },
                } )
              : null,
            el( RangeControl, {
              label: __( 'Maximum categories', 'sikshya' ),
              value: attributes.limit,
              min: 1,
              max: 60,
              onChange: function ( v ) {
                setAttributes( { limit: typeof v === 'number' ? v : 8 } );
              },
            } ),
            el( ToggleControl, {
              label: __( 'Show course count', 'sikshya' ),
              checked: !! attributes.showCount,
              onChange: function ( v ) {
                setAttributes( { showCount: !! v } );
              },
            } ),
            attributes.displayStyle === 'tiles'
              ? el( ToggleControl, {
                  label: __( 'Show category image', 'sikshya' ),
                  checked: !! attributes.showImage,
                  onChange: function ( v ) {
                    setAttributes( { showImage: !! v } );
                  },
                } )
              : null,
            el( ToggleControl, {
              label: __( 'Hide empty categories', 'sikshya' ),
              checked: !! attributes.hideEmpty,
              onChange: function ( v ) {
                setAttributes( { hideEmpty: !! v } );
              },
            } ),
            el( ToggleControl, {
              label: __( 'Top-level categories only', 'sikshya' ),
              checked: !! attributes.topLevelOnly,
              onChange: function ( v ) {
                setAttributes( { topLevelOnly: !! v } );
              },
            } )
          )
        ),
        el( 'div', blockProps, preview( 'sikshya/course-categories', attributes ) )
      );
    },
    save: function () {
      return null;
    },
  } );

  /* ---------------------------------------------------------------------
   * sikshya/course-search
   * ------------------------------------------------------------------ */

  registerBlockType( 'sikshya/course-search', {
    icon: SEARCH_ICON,
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps ? useBlockProps() : {};

      return el(
        Fragment,
        null,
        el(
          InspectorControls,
          { key: 'inspector' },
          el(
            PanelBody,
            { title: __( 'Search', 'sikshya' ), initialOpen: true },
            el( TextControl, {
              label: __( 'Placeholder', 'sikshya' ),
              value: attributes.placeholder,
              onChange: function ( v ) {
                setAttributes( { placeholder: v || '' } );
              },
            } ),
            el( ToggleControl, {
              label: __( 'Show button', 'sikshya' ),
              checked: !! attributes.showButton,
              onChange: function ( v ) {
                setAttributes( { showButton: !! v } );
              },
            } ),
            attributes.showButton
              ? el( TextControl, {
                  label: __( 'Button label', 'sikshya' ),
                  value: attributes.buttonLabel,
                  onChange: function ( v ) {
                    setAttributes( { buttonLabel: v || '' } );
                  },
                } )
              : null,
            el( ToggleControl, {
              label: __( 'Add a category filter', 'sikshya' ),
              checked: !! attributes.showCategoryFilter,
              onChange: function ( v ) {
                setAttributes( { showCategoryFilter: !! v } );
              },
            } )
          )
        ),
        el( 'div', blockProps, preview( 'sikshya/course-search', attributes ) )
      );
    },
    save: function () {
      return null;
    },
  } );

  /**
   * "12, 34" -> [12, 34].
   *
   * @param {string} value Raw field value.
   * @return {Array} Positive integers, de-duplicated.
   */
  function parseIds( value ) {
    var ids = [];
    String( value || '' )
      .split( /[\s,]+/ )
      .forEach( function ( part ) {
        var id = parseInt( part, 10 );
        if ( id > 0 && ids.indexOf( id ) === -1 ) {
          ids.push( id );
        }
      } );

    return ids;
  }
} )( window.wp );
