/**
 * The `wp-postviews/views` block.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP. That is what
 * makes the block and the `[views]` shortcode able to share one renderer -- the
 * count and the template around it are read at render time, for both of them,
 * so a block saved last year shows today's number and today's template.
 *
 * The preview is core's ServerSideRender, which posts the attributes to
 * /wp/v2/block-renderer/wp-postviews/views and draws what the front end would
 * draw. That is also why this block registers no REST route of its own.
 *
 * Previewing does not count a view. Counting happens on `wp_head` for an
 * ordinary page load, or from a script posting to `admin-ajax.php` where a page
 * cache is in the way; a block-renderer request is neither, and the renderer
 * this block calls only reads the stored number.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

/**
 * The editor view.
 *
 * A named component with a capitalised name rather than an `edit()` shorthand
 * on the settings object: useBlockProps is a React hook, and the hook rules can
 * only tell a component from a plain function by that capital.
 *
 * The post is chosen by id typed into a field rather than from a dropdown of
 * posts. Every post on the site has a count, so a picker here would be a search
 * over the whole posts list for the uncommon case -- the common one is leaving
 * it at zero and showing the count of the post being written.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor view.
 */
function Edit( { attributes, setAttributes } ) {
	const { id } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Post Views', 'wp-postviews' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Post ID', 'wp-postviews' ) }
						help={ __(
							'Zero shows the count of the post this block is in, which is what an empty [views] shortcode does.',
							'wp-postviews',
						) }
						type="number"
						min={ 0 }
						value={ id }
						onChange={ ( value ) =>
							setAttributes( { id: parseInt( value, 10 ) || 0 } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
