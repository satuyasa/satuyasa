/**
 * Registrasi sisi editor untuk blok dinamis tema Aksara.
 *
 * Ditulis dalam JavaScript biasa memakai global wp.* — TANPA JSX, webpack,
 * atau npm. Alasannya sama dengan seluruh proyek ini: tidak ada build step,
 * jadi berkas yang dibaca sama dengan berkas yang dijalankan.
 *
 * Semua blok di sini dirender di server (lihat inc/blocks.php), jadi
 * pratinjau editornya memakai ServerSideRender — tampilan di editor adalah
 * hasil render yang sesungguhnya, bukan tiruan yang bisa melenceng.
 */
( function ( blocks, element, blockEditor, components, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var ServerSideRender = serverSideRender;

	/**
	 * Pratinjau server + (opsional) panel pengaturan di sidebar.
	 *
	 * @param {string}   name       Nama blok.
	 * @param {Object}   attributes Atribut saat ini.
	 * @param {Function} controls   Pengembali elemen kontrol, boleh null.
	 * @return {Object} Elemen React.
	 */
	function preview( name, attributes, controls ) {
		var children = [
			el( ServerSideRender, {
				key: 'ssr',
				block: name,
				attributes: attributes,
				// Blok ini butuh konteks post yang sedang disunting (mis.
				// halaman font Authentype membaca get_the_ID()).
				urlQueryArgs: {}
			} )
		];

		if ( controls ) {
			children.unshift( el( InspectorControls, { key: 'inspector' }, controls ) );
		}

		return el( 'div', useBlockProps(), children );
	}

	/** Blok tanpa pengaturan apa pun. */
	function registerSimple( name, title, description, icon ) {
		blocks.registerBlockType( name, {
			apiVersion: 3,
			title: title,
			description: description,
			icon: icon,
			category: 'aksara',
			supports: { html: false, align: false },
			edit: function ( props ) {
				return preview( name, props.attributes, null );
			},
			save: function () {
				return null; // dirender di server
			}
		} );
	}

	registerSimple(
		'aksara/category-row',
		__( 'Aksara: Category row', 'aksara' ),
		__( 'Three linked category cards. Destination URLs are resolved at render time.', 'aksara' ),
		'grid-view'
	);
	registerSimple(
		'aksara/license-list',
		__( 'Aksara: License list', 'aksara' ),
		__( 'Every license type, rendered from WooCommerce > Font Licenses.', 'aksara' ),
		'media-document'
	);
	registerSimple(
		'aksara/font-library',
		__( 'Aksara: Font library', 'aksara' ),
		__( 'Searchable Authentype font catalog with pagination.', 'aksara' ),
		'search'
	);
	registerSimple(
		'aksara/authentype-single',
		__( 'Aksara: Font product body', 'aksara' ),
		__( 'Full font product page: breadcrumb, gallery, details, specimen, related families.', 'aksara' ),
		'editor-textcolor'
	);
	registerSimple(
		'aksara/header-actions',
		__( 'Aksara: Header actions', 'aksara' ),
		__( 'Sign in link and cart with a live item count.', 'aksara' ),
		'cart'
	);

	blocks.registerBlockType( 'aksara/hero', {
		apiVersion: 3,
		title: __( 'Aksara: Hero', 'aksara' ),
		description: __( 'Home hero with search, collection links, and live catalog counts.', 'aksara' ),
		icon: 'align-wide',
		category: 'aksara',
		supports: { html: false, align: false },
		edit: function ( props ) {
			var a = props.attributes;
			var setA = props.setAttributes;

			var controls = el(
				components.PanelBody,
				{ title: __( 'Hero text', 'aksara' ), initialOpen: true },
				el( components.TextControl, {
					label: __( 'Eyebrow', 'aksara' ),
					value: a.eyebrow,
					onChange: function ( v ) { setA( { eyebrow: v } ); }
				} ),
				el( components.TextareaControl, {
					label: __( 'Headline', 'aksara' ),
					value: a.headline,
					onChange: function ( v ) { setA( { headline: v } ); }
				} ),
				el( components.TextareaControl, {
					label: __( 'Subtitle', 'aksara' ),
					value: a.subtitle,
					onChange: function ( v ) { setA( { subtitle: v } ); }
				} )
			);

			return preview( 'aksara/hero', a, controls );
		},
		save: function () { return null; }
	} );

	blocks.registerBlockType( 'aksara/font-list', {
		apiVersion: 3,
		title: __( 'Aksara: Font specimen list', 'aksara' ),
		description: __( 'Large specimen rows for the most recent font families.', 'aksara' ),
		icon: 'editor-paragraph',
		category: 'aksara',
		supports: { html: false, align: false },
		edit: function ( props ) {
			var controls = el(
				components.PanelBody,
				{ title: __( 'Settings', 'aksara' ), initialOpen: true },
				el( components.RangeControl, {
					label: __( 'How many families', 'aksara' ),
					value: props.attributes.limit,
					min: 1,
					max: 24,
					onChange: function ( v ) { props.setAttributes( { limit: v } ); }
				} )
			);
			return preview( 'aksara/font-list', props.attributes, controls );
		},
		save: function () { return null; }
	} );

	blocks.registerBlockType( 'aksara/asset-grid', {
		apiVersion: 3,
		title: __( 'Aksara: Canva asset grid', 'aksara' ),
		description: __( 'Grid of Canva Templates and Elements.', 'aksara' ),
		icon: 'screenoptions',
		category: 'aksara',
		supports: { html: false, align: false },
		edit: function ( props ) {
			var controls = el(
				components.PanelBody,
				{ title: __( 'Settings', 'aksara' ), initialOpen: true },
				el( components.SelectControl, {
					label: __( 'Show', 'aksara' ),
					value: props.attributes.type,
					options: [
						{ label: __( 'Templates and elements', 'aksara' ), value: 'both' },
						{ label: __( 'Canva Templates only', 'aksara' ), value: 'template' },
						{ label: __( 'Canva Elements only', 'aksara' ), value: 'element' }
					],
					onChange: function ( v ) { props.setAttributes( { type: v } ); }
				} ),
				el( components.RangeControl, {
					label: __( 'How many items', 'aksara' ),
					value: props.attributes.limit,
					min: 1,
					max: 48,
					onChange: function ( v ) { props.setAttributes( { limit: v } ); }
				} )
			);
			return preview( 'aksara/asset-grid', props.attributes, controls );
		},
		save: function () { return null; }
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
) );
