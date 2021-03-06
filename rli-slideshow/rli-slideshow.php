<?php
/*
Plugin Name: Rocket Lift Slideshow
Version: 0.4
Plugin URI: http://rocketlift.com/software/rli-slideshow
Description: Creates slideshow from 'slide' custom post type with Slides JS ( http://slidesjs.com/ ). NOTE this is pre-release alpha software geared specifically for The Mobile Tech PC website. It is not yet ready for open-source release.
Author: Matthew Eppelsheimer based on work by Peter Molnar
Author URI: http://rocketlift.com/
License: Apache License, Version 2.0
*/

/*  Copyright 2012 Rocket Lift Incorporated  (email : hello@rocketlift.com )
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
*/

/*
 * ACTIVATION
 *
 */

// set up 'rli_slide' custom post type.
// 
// TODO 'menu_icon' => 'some-image.png',

function rli_slideshow_create_rli_slide_post_type() {
	register_post_type( 'rli_slide',
					array(
						'labels' => array(
							'name' => __( 'Slides' ),
							'singular_name' => __( 'Slide' )
						),
						'public' => true,
						'description' => 'Individual slides to be included in a slide deck.',
						'exclude_from_search' => true,
						'publicly_queryable' => false,
						'show_in_nav_menus' => false,
						'menu_position' => 5,
						'map_meta_cap' => true,
						'hierarchical' => true,
						'supports' => array(
							'title' , 'editor' , 'thumbnail' , 'page-attributes'
						),
						'has_archive' => false
					)
				);
			}

add_action( 'init', 'rli_slideshow_create_rli_slide_post_type' );

// First, we "add" the custom post type via the above written function.
// Then we flush_rewrite_rules to set up permalinks.
// @todo Bachuber said this is too late to flush rewrite rules. What's the fix?
//

function rli_slideshow_rewrite_flush()  {
    rli_slideshow_create_rli_slide_post_type();

    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'rli_slideshow_rewrite_flush' );

/*
 * Set up UI assets for File Attachment Uploader
 * @todo how are we using this?
 */

function rli_slideshow_admin_scripts() {
	wp_register_script('rli-slideshow-admin', plugins_url('js/rli-slideshow-admin.js', __FILE__ ), array('jquery','media-upload','thickbox'));
	wp_enqueue_script('rli-slideshow-admin');
}

function rli_slideshow_admin_styles() {

}

function rli_slideshow_admin_assets() {
	global $post_type;
		if( 'rli_slide' == $post_type ) {
			rli_slideshow_admin_scripts();
			rli_slideshow_admin_styles();
    }
}  

add_action( 'admin_print_scripts-post-new.php', 'rli_slideshow_admin_assets', 11 );
add_action( 'admin_print_scripts-post.php', 'rli_slideshow_admin_assets', 11 );

function rli_slideshow_image_selector_setup() {  
	global $pagenow;

	if ( 'media-upload.php' == $pagenow || 'async-upload.php' == $pagenow ) { 
		// Replace the 'Insert into Post Button' inside Thickbox  
		add_filter( 'gettext', 'rli_replace_thickbox_text'  , 1, 3 );
		
		// set default image size to full size
		add_filter('attachment_fields_to_edit', 'rli_set_default_image_size', 11, 2);
	}
}

add_action( 'admin_init', 'rli_slideshow_image_selector_setup');

function rli_replace_thickbox_text($translated_text, $text, $domain) { 
	if ('Insert into Post' == $text) {
		$referer = strpos( wp_get_referer(), 'rli_slide_select_settings' );
		if ( $referer != '' ) {
			return __('Set Image as Slide Background');  
		}
	}
	return $translated_text;
}

/*
 * Sets default image size in media uploader to full size for slideshow plugin
 */
function rli_set_default_image_size($form_fields, $post){
	$referer = strpos( wp_get_referer(), 'rli_slide_select_settings' );
	if ( $referer != '' ) {
		$form_fields['image-size'] = image_size_input_fields( $post, 'full'); // called in wp-admin/includes/media.php
	}
	return $form_fields;
}

/*
 * rli_slideshow_get_slide_template_options( $template )
 * Returns option keys and values associated with the given slideshow template
 *
 * @param str $template - the slug name of the slide's template
 * @todo replace $template_keys array declaration with function to return $template_keys by looking up $template
 */

function rli_slideshow_get_slide_template_specifications( $template ) {
	// @todo replace this with a function to return $template_keys by looking up the template
	$template_keys = array(
		array(
			'slug' => 'background_image',
			'order' => 0, 
			'name' => 'Background Image',
			'description' => 'The slide\'s background image',
			// 'help' => 'Defaults to the default image in settings.',
			'css' => '%s { background-image: url(\'%s\'); }',
			'css_params' => array( // maybe this should be the default.
				'slide_class',
				'data'
			),
			'slide_class' => array( // classes to append to the slide's wrapper div
				'', // value if this setting is not blank
				'default-background' // value if this setting is not blank
			),
			'setting_type' => 'string'
		),
		array(
			'slug' => 'content',
			'order' => 1,
			'name' => 'Slide Content',
			'description' => 'The slide\'s content, based on the editor box',
			'lookup' => 'the_content',
			'html' => '<div class=\'%s\'>%s</div>',
			'html_params' => array(
				'slide-content' // @TODO rethink this. Its kind of inflexible. 
			),
			'setting_type' => 'lookup'
		)
	);
	return $template_keys;
}


/*
 * Generate a button to select an image using WordPress's media upload and a preview image
 * 	Assumes js includes are already included for media uploader and js function is named rli_upload_media
 *	Returns string of html code for the added button 
 *	@param str $text_id - id of existing textfield to submit img_url
 *	@param str $image_id - id of preview image
 *	@param str $image_init - initial state of image
 *	@param str $button_name - displayed name of button
 *	@param str $button_id - id of button to create
 *	@todo Move thiis function to a better location
 */

function rli_create_media_upload_button($text_id, $image_id, $image_init, $button_name, $button_id) {
	$ret = "<div id='$image_id' style='min-height: 40px;'>";
	$ret .="<img height='100px' src='$image_init' />  <br/>";
	$ret .= "<input id='$button_id' type='button' class='button' onclick=\"rli_upload_media($text_id, $image_id);\" value='$button_name' />";
	$ret .= "</div>";
 
	return $ret;
}

/*
 *	rli_slideshow_render_setting_from_template( $setting, $value, $template )
 *	@param str $setting - setting name (slug)
 *	@param str $value - setting value
 *	@param str $template - template name
 */

function rli_slideshow_render_setting_from_template( $setting, $value, $template ) {
	$template_specs = rli_slideshow_get_slide_template_specifications( $template );

	$pattern;
	$output = "";

	foreach ( $template_specs as $specification ) {
		if ( $specification['slug'] == $setting ){
			$pattern = $specification;
			break;
		}
	}

	switch ( $pattern['setting_type'] ) {
		case 'lookup':
			break;
		case 'string':
			$textfield_id = "rli_slideshow_background_image_$setting";
			
			$output .= "<h4>" . $pattern['name'] . "</h4>\n";
			$output .= "<input type='hidden' id='$textfield_id' name='rli_slideshow_slide_" . $setting . "' value='" . esc_attr( $value ) . "' />";
			$output .= rli_create_media_upload_button($textfield_id, 'upload_bg_preview', esc_attr( $value ), _('Choose Background Image'), 'rli_slideshow_upload_background_button');

			if ( isset( $pattern['help'] ) ) {
				$output .= " <em class='how-to'>" . $pattern['help'] . "</em>";
			}
			
			$output .= "\n";
			break;
	}

	return $output;
}

/*
 * Generate html for Slide Settings Metabox
 *	@todo before the foreach loop, order the $slide_template array by 'order'
 */

function rli_slideshow_settings_metabox_render( $post ) {

	$slide_settings = get_post_meta( $post->ID, '_rli_slideshow_slide_settings', true );

	// Get and store the template name.
	if ( ! isset( $slide_settings['template'] ) ) 
		$slide_settings['template'] = 'default';
	$slide_template = $slide_settings['template'];

	$template_specs = rli_slideshow_get_slide_template_specifications( $slide_template );

	$output = "<div>\n";

	// Build the settings form based on the template specifications
	foreach ( $template_specs as $setting ) {
		$slug = $setting['slug'];
		if ( ! isset( $slide_settings[$slug] ) )
			$slide_settings[$slug] = '';
		$output .= rli_slideshow_render_setting_from_template( $slug, $slide_settings[$slug], $slide_template );
	}

	$output .="</div>\n";

	echo $output;
}


/*
 * Create Settings Metabox
 */

function rli_slideshow_create_detail_metabox() {
	add_meta_box( 'rli-slide-settings', 'Slide Settings', 'rli_slideshow_settings_metabox_render', 'rli_slide', 'normal', 'high' );
}

add_action( 'add_meta_boxes', 'rli_slideshow_create_detail_metabox' );

/**
 *	Save slide settings metabox data
 *	rli_slideshow_save_slide_meta()
 *
 *	@todo	Extend to detect slide's template and base this on it. 
 *	@todo	Handle default settings
 */

function rli_slideshow_save_slide_meta( $post_id ) {
	// verify if this is an auto save routine. 
	// If it is our form has not been submitted, so we dont want to do anything
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return $post_id;
        
	$slide_settings = get_post_meta( $post_id, '_rli_slideshow_slide_settings', true );

	$template_specs = rli_slideshow_get_slide_template_specifications( 'default' );

	foreach ( $template_specs as $specification ) {
		switch ( $specification['setting_type'] ) {
			case 'lookup':
				break;
			case 'string':
				$str = "rli_slideshow_slide_" . $specification['slug'];
				if ( isset( $_POST[$str] ) ) 
					$slide_settings[$specification['slug']] = strip_tags( $_POST[$str] );
				break;
		}
	}

	// a temporary measure
	$slide_settings['template'] = 'default';

	update_post_meta( $post_id, '_rli_slideshow_slide_settings', $slide_settings );

}

add_action( 'save_post', 'rli_slideshow_save_slide_meta' );

/**
 * Modal Button.
 *
 * Create a button in the modal media window to associate the current image with the slide.
 * @from      Taxonomy Images Plugin by Michael Fields
 *
 * @param     array     Multidimensional array representing the images form.
 * @param     stdClass  WordPress post object.
 * @return    array     The image's form array with added button if modal window was accessed by this script.
 *
 * @access    private
 * @since     2010-10-28
 * @alter     0.7
 */

function rli_slideshow_modal_button( $fields, $post ) {
	if ( isset( $fields['image-size'] ) && isset( $post->ID ) ) {
		$image_id = (int) $post->ID;

		$o = '<div class="rli-slideshow-modal-control" id="' . esc_attr( 'rli-slideshow-modal-control-' . $image_id ) . '">';

		$o.= '<span class="button create-association">' . sprintf( esc_html__( 'Associate with %1$s', 'rli-slideshows' ), '<span class="term-name">' . esc_html__( 'this term', 'rli-slideshows' ) . '</span>' ) . '</span>';

		$o.= '<span class="remove-association">' . sprintf( esc_html__( 'Remove association with %1$s', 'rli-slideshows' ), '<span class="term-name">' . esc_html__( 'this term', 'rli-slideshows' ) . '</span>' ) . '</span>';

		$o.= '<input class="rli-slideshow-button-image-id" name="' . esc_attr( 'rli-slideshow-button-image-id-' . $image_id ) . '" type="hidden" value="' . esc_attr( $image_id ) . '" />';

		$o.= '<input class="rli-slideshow-button-nonce-create" name="' . esc_attr( 'rli-slideshow-button-nonce-create-' . $image_id ) . '" type="hidden" value="' . esc_attr( wp_create_nonce( 'rli-slideshow-plugin-create-association' ) ) . '" />';

		$o.= '<input class="rli-slideshow-button-nonce-remove" name="' . esc_attr( 'rli-slideshow-button-nonce-remove-' . $image_id ) . '" type="hidden" value="' . esc_attr( wp_create_nonce( 'rli-slideshow-plugin-remove-association' ) ) . '" />';

		$o.= '</div>';

		$fields['image-size']['extra_rows']['rli-slideshow-plugin-button']['html'] = $o; }
	return $fields;
}

add_filter( 'attachment_fields_to_edit', 'rli_slideshow_modal_button', 20, 2 );

/*
 *	rli_slideshow_frontend_setup() to enqueue JS
 *	Currently, this must be called manually in a theme template file 
 *	before the 'wp_head' action to avoid including it everywhere.
 *
 *	@todo Rethink this.
 */

function rli_slideshow_frontend_setup() {
	wp_enqueue_script( 'rli-jquery-slides' , plugins_url( 'js/slides.min.jquery.js', __FILE__ ) , array('jquery') );
}

/**
 * RLI Utility to get get custom posts of a given type
 *
 *	@param		str		$post_type	The post type to query for
 *	@param		array	$args	Array of arguments formulated to pass to the WP_Query class constructor
 *	@param		array	$defaults_override	Array of default arguments formulated to pass to the 
 *						WP_Query class constructor and override the utility's own defaults
 *	@returns			an array of WP_Query results of the custom post type passed
 *
 *	@since		2012/11/01
 */

if ( ! function_exists( 'rli_library_get_custom_posts' ) ) {
	function rli_library_get_custom_posts( $post_type, $args, $defaults_override = array() ) {
		$defaults = array(
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'menu_order'
		);
		$new_defaults = wp_parse_args( $defaults_override, $defaults );
		$query_args = wp_parse_args( $args, $new_defaults );
		$query_args['post_type'] = $post_type;
	
		$results = new WP_Query( $query_args );
	
		return $results;
	}
}

/**
 * Utility to query slides
 *
 *	@param		array	$args	Array of arguments formulated to pass to the WP_Query class constructor
 *	@returns	an array of WP_Query results with rli_slide posts
 *
 *	@uses		rli_library_get_custom_posts()
 *	@since		version 0.4
 */

function rli_slideshow_get_slides( $args = array() ) {
	return rli_library_get_custom_posts( 'rli_slide', $args );
}

/*
 *	Pull in assets for slidshow display
 */

require_once ( plugin_dir_path( __FILE__ ) . 'display-slides.php' );

// Support for direct manipulation with action hooks in theme templates
add_action( 'rli_wpslides', 'rli_slideshow_display_slideshow' );


/*
 *	rli_slideshow_register_shortcode() registers shortcode 
 */

function rli_slideshow_register_shortcode() {
	add_shortcode( 'rli-slidshow', 'rli_slideshow_shortcode' );
}

add_action( 'init', 'rli_slideshow_register_shortcode' );

/*
 * rli_slideshow_shortcode() creates a shortcode to display a slideshow on demand
 */

function rli_slideshow_shortcode( $atts ) {
	extract( 
		shortcode_atts( 
			array(
				'slideshow' => 'default'
			), 
			$atts 
		) 
	);

	return rli_slideshow_display_slideshow( $slideshow );
}

