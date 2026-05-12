( function( wp ) {
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const { PanelBody, ToggleControl } = wp.components;
	const { Fragment, createElement } = wp.element;

	function addAttributes( settings, name ) {
		if ( name !== 'core/query' ) {
			return settings;
		}

		settings.attributes = Object.assign( {}, settings.attributes, {
			qbrpHideCurrentPost: {
				type: 'boolean',
				default: false,
			},
			qbrpShowRelatedPosts: {
				type: 'boolean',
				default: false,
			},
		} );

		return settings;
	}

	addFilter(
		'blocks.registerBlockType',
		'query-block-related-posts/register-attributes',
		addAttributes
	);

	const withInspectorControls = createHigherOrderComponent(
		function( BlockEdit ) {
			return function( props ) {
				if ( props.name !== 'core/query' ) {
					return createElement( BlockEdit, props );
				}

				const { attributes, setAttributes } = props;

				const hideCurrentPost =
					!! attributes.qbrpHideCurrentPost ||
					!! ( attributes.query && attributes.query.hideCurrentPost );

				const showRelatedPosts =
					!! attributes.qbrpShowRelatedPosts ||
					!! ( attributes.query && attributes.query.showRelatedPosts );

				function updateFlags( updates ) {
					const nextQuery = Object.assign(
						{},
						attributes.query || {},
						updates.query || {}
					);

					const nextTop = updates.top || {};

					setAttributes(
						Object.assign(
							{},
							nextTop,
							{ query: nextQuery }
						)
					);
				}

				return createElement(
					Fragment,
					{},
					createElement( BlockEdit, props ),
					createElement(
						InspectorControls,
						{},
						createElement(
							PanelBody,
							{
								title: 'Query Block Related Posts',
								initialOpen: true,
							},
							createElement( ToggleControl, {
								label: 'Hide the current post',
								checked: hideCurrentPost,
								onChange: function( value ) {
									updateFlags( {
										top: { qbrpHideCurrentPost: value },
										query: { hideCurrentPost: value },
									} );
								},
							} ),
							createElement( ToggleControl, {
								label: 'Show related posts',
								checked: showRelatedPosts,
								onChange: function( value ) {
									updateFlags( {
										top: { qbrpShowRelatedPosts: value },
										query: { showRelatedPosts: value },
									} );
								},
								help: 'Show items from the same post type with shared taxonomy terms.',
							} )
						)
					)
				);
			};
		},
		'withInspectorControls'
	);

	addFilter(
		'editor.BlockEdit',
		'query-block-related-posts/inspector-controls',
		withInspectorControls
	);
} )( window.wp );