<?php
/*
Plugin Name: My Tickets: Sharing
Plugin URI: http://www.joedolson.com/
Description: Invite purchasers to share your event on social media after they make their purchase.
Author: Joseph C Dolson
Author URI: http://www.joedolson.com/product/my-tickets-sharing/
Version: 1.0.0
*/
/*  Copyright 2015-2016  Joe Dolson (email : joe@joedolson.com)

    This program is open source software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
global $mts_version;
$mts_version = '1.0.0';

load_plugin_textdomain( 'my-tickets-sharing', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );


// The URL of the site with EDD installed
define( 'EDD_MTS_STORE_URL', 'https://www.joedolson.com' ); 
// The title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_MTS_ITEM_NAME', 'My Tickets: Sharing' ); 

if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist 
	include( dirname( __FILE__ ) . '/updates/EDD_SL_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'mts_license_key' ) ); 
// setup the updater
$edd_updater = new EDD_SL_Plugin_Updater( EDD_MTS_STORE_URL, __FILE__, array(
	'version' 	=> $mts_version				// current version number
	'license' 	=> $license_key,			// license key (used get_option above to retrieve from DB)
	'item_name'     => EDD_MTS_ITEM_NAME,	// name of this plugin
	'author' 	=> 'Joe Dolson',			// author of this plugin
	'url'           => home_url()
) );


/*
 * Get the post data that will be sent to social sharing pages.
 * 
 * @param integer $post_ID ID of the current post.
 *
 * @return array of post data for use in sharing.
 */
function mts_post_information( $post_ID ) {
	$data = array();
	$data['title']     = get_the_title( $post_ID ); 
	$data['url']       = get_permalink( $post_ID );
	$image_ID          = get_post_thumbnail_id( $post_ID );
	$image             = wp_get_attachment_image_src( $image_ID, 'large' );
	$data['image_url'] = $image[0];
	$data['image_alt'] = get_post_meta( $image_ID, '_wp_attachment_image_alt', true );
	
	return $data;
}

/* 
 * Generate the URLs used to post data to services.
 * 
 * @param integer $post_ID of current post
 * 
 * @return array of URLs for posting to each service.
 */
function mts_create_urls( $post_ID ) {
	$data      = mts_post_information( $post_ID );	
	$twitter   = "https://twitter.com/intent/tweet?text=" . urlencode( $data['title'] . ' ' . $data['url'] );
	$facebook  = "https://www.facebook.com/sharer/sharer.php?u=" . urlencode( $data['url'] );
	$google    = "https://plus.google.com/share?url=" . urlencode( $data['url'] );
	if ( esc_url( $data['image_url'] ) && $data['image_alt'] != '' ) {
		$pinterest = "https://pinterest.com/pin/create/button/?url=" . urlencode( $data['url'] ) . "&media=" . urlencode( $data['image_url'] ) . "&description=" . urlencode( $data['image_alt'] );
	} else {
		$pinterest = false;
	}
	
	return apply_filters( 'mts_social_service_links', array( 
		'twitter'   => $twitter,
		'facebook'  => $facebook,
		'google'    => $google,
		'pinterest' => $pinterest
	), $data );
}

/*
 * Generate the HTML links using URLs.
 *
 * @param integer $post_ID of current post
 *
 * @return string block of HTML links.
 */
function mts_create_links( $post_ID ) {
	$urls = mts_create_urls( $post_ID );
	$html = '';
	
	$settings  = get_option( 'mts_settings' );
	$enabled   = ( isset( $settings['enabled'] ) ) ? $settings['enabled'] : array( 'twitter' => 'on', 'facebook' => 'on', 'google' => 'on', 'pinterest' => 'on' );

	foreach ( $urls as $service => $url ) {
		$is_enabled = in_array( $service, array_keys( $enabled ) );
		if ( $url && $is_enabled ) {
			$html .= "
					<div class='mts-link $service'>
						<a href='" . esc_url( $url ) . "' rel='nofollow external' aria-describedby='description-$service'>
							<span class='mts-icon $service' aria-hidden='true'></span>
							<span class='mts-text $service'>" . ucfirst( $service ) . "</span>
						</a>
						<span class='description' role='tooltip' id='description-$service'>
							" . __( 'Share this post' ) . "
						</span>
					</div>";
		}
	}
	
	return "<div class='mts-links'>" . $html . "</div>";
}

/*
 * Fetch HTML for links and wrap in a container. Add heading and ARIA landmark role.
 *
 * @param integer $post_ID of current post.
 *
 * @return full HTML block.
 */
function mts_social_block( $post_ID ) {
	$links = mts_create_links( $post_ID );
	$post_title = get_the_title( $post_ID );
	
	$html = "
			<nav aria-labelledby='my-tickets-sharing'>
				<h3 id='my-tickets-sharing'>" . sprintf( __( 'Share this! %s', 'my-tickets-sharing' ), $post_title ) . "</h3>			
				<div class='mts-social-share'>				
					$links
				</div>
			</nav>";
	
	return $html;
}
/*
 * Use WordPress filter 'mt_response_messages' to add sharing links into cart confirmation data.
 *
 * @param $content The current content of the post.
 * 
 * @return $content The previous content of the post plus social sharing links.
 */
add_filter( 'mt_response_messages', 'mts_social_sharing', 30, 2 );
function mts_social_sharing( $message, $response_code ) {
	if ( $response_code == 'thanks' && isset( $_GET['payment'] ) ) {
		$post_ID = intval( $_GET['payment'] );
		$events = mt_list_events( $post_ID );
		foreach ( $events as $event_ID ) {
			$mts_social = mts_social_block( $event_ID );
			$message .= $mts_social;
		}
	}
	
	return $message;
}

/*
 * Register custom stylesheet for social sharing.
 */
add_action( 'wp_enqueue_scripts', 'mts_register_styles' );
function mts_register_styles() {
	wp_register_style( 'mts-icomoon', plugins_url( 'fonts/icomoon.css', __FILE__ ) );
	if ( !is_admin() ) {
		// option to replace stylesheet.
		if ( file_exists( get_stylesheet_directory() . '/mts.css' ) ) {
			$stylesheet = get_stylesheet_directory_uri() . '/mts.css';
		} else {
			$stylesheet = plugins_url( 'css/mts.css', __FILE__ );
		}
		wp_enqueue_style( 'mts-social-share', plugins_url( 'css/mts.css', __FILE__ ), array( 'dashicons', 'mts-icomoon' ) );
	}
}



add_action( 'admin_menu', 'mts_menu_item', 11 );
/**
 * Add submenus item to show donations page.
 */
function mts_menu_item() {
	$permission = apply_filters( 'mt_sharing_permissions', 'manage_options' );
	add_submenu_page( 'my-tickets', __( 'My Tickets: Sharing', 'my-tickets' ), __( 'Sharing', 'my-tickets' ), $permission, 'my-tickets-sharing', 'mts_list' );
}


function mts_settings_fields() {
	$settings = get_option( 'mts_settings' );
	$available = apply_filters( 'mts_social_services', array( 'twitter', 'facebook', 'google', 'pinterest'	) );	
	
	if ( is_array( $settings ) ) {
		$enabled = $settings['enabled'];
	} else {
		$enabled = array();
	}
	if ( isset( $_POST['mts_update'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-tickets-sharing-nonce' ) ) { wp_die( __( 'Invalid request', 'my-tickets-sharing' ) );	}
		$enabled = $_POST['mts_enabled'];
		foreach ( $enabled as $value ) {
			$new_enabled[$value] = 'on';
		}
		$settings['enabled'] = $new_enabled;
		$enabled = $new_enabled;
		update_option( 'mts_settings', $settings );
		echo "<div class='notice updated'><p>" . __( 'Social Sharing Services Updated', 'my-tickets-sharing' ) . "</p></div>";
	}
		
	$fields = '';
	foreach( $available as $value ) {
		if ( is_array( $enabled ) ) {
			$checked = ( in_array( $value, array_keys( $enabled ) ) ) ? ' checked="checked"' : '';
		} else {
			$checked = '';
		}
		$fields .= "<li><input type='checkbox' name='mts_enabled[]' value='" . esc_attr( $value ) . "' id='mts_enabled_" . esc_attr( $value ) . "' $checked /> <label for='mts_enabled_" . esc_attr( $value ) . "'>" . ucfirst( esc_html( $value ) ) . "</label></li>";
	}
	$form = "
		<form method='post' action='" . admin_url( 'admin.php?page=my-tickets-sharing' )."'>
			<div><input type='hidden' name='_wpnonce' value='" . wp_create_nonce( 'my-tickets-sharing-nonce' ) . "' /></div>
			<fieldset>
				<legend>" . __( 'Social Sharing Services', 'my-tickets-sharing' ) . "</legend>
				<ul>
					$fields
				</ul>
			</fieldset>
			<p>
				<input type='submit' class='button-primary' name='mts_update' value='" . __( 'Save Settings', 'my-tickets-sharing' ) . "' />
			</p>
		</form>";
	echo $form;

}

function mts_list() {
	?>
	<?php $response = mts_update_settings( $_POST ); ?>
	<div class="wrap my-tickets" id="mt_sharing">
		<div id="icon-options-general" class="icon32"><br/></div>
		<h2><?php _e( 'Social Sharing Options', 'my-tickets-sharing' ); ?></h2>
		<div class="postbox-container jcd-wide">
			
			<div class="metabox-holder">

				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h3><?php _e( 'Social Sharing Settings', 'my-tickets-sharing' ); ?></h3>
						<div class="inside">
							<?php echo $response; ?>
							<?php mts_settings_fields(); ?>									
						</div>
					</div>
				</div>
			</div>			
		</div>
		<?php mt_show_support_box(); ?>		
	</div>
	<?php
}

function mts_update_settings( $post ) {
	if ( isset( $post['mtd-settings'] ) ) {
		$nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : false;
		if ( !$nonce ) {
			return;
		}
		if ( ! wp_verify_nonce( $nonce, 'my-tickets-sharing' ) ) {
			return false;
		}
		if ( isset( $_POST['mts_cta'] ) ) {
			$mts_cta = $_POST['mts_cta'];
			update_option( 'mts_cta', $mts_cta );
		}

		return "<div class=\"updated\"><p><strong>" . __( 'Social Sharing Settings saved', 'my-tickets-sharing' ) . "</strong></p></div>";
	}

	return false;
}

/**
 * Insert license key field onto license keys page.
 *
 * @param $fields string Existing fields.
 * @return string
 */
add_action( 'mt_license_fields', 'mts_license_field' );
function mts_license_field( $fields ) {
	$field = 'mts_license_key';
	$name =  __( 'My Tickets: Sharing', 'my-tickets-sharing' );
	return $fields . "
	<p class='license'>
		<label for='$field'>$name</label><br/>
		<input type='text' name='$field' id='$field' size='60' value='".esc_attr( trim( get_option( $field ) ) )."' />
	</p>";
}

add_action( 'mt_save_license', 'mts_save_license', 10, 2 );
function mts_save_license( $response, $post ) {
	$field = 'mts_license_key';
	$name =  __( 'My Tickets: Sharing', 'my-tickets-sharing' );	
	if ( $post[$field] != get_option( $field ) ) {
		$verify = mt_verify_key( $field, EDD_MTS_ITEM_NAME, EDD_MTS_STORE_URL )
	} else {
		$verify = '';
	}
	$verify = "<li>$verify</li>";
	return $response . $verify;
}

// these are existence checkers. Exist if licensed.
if ( get_option( 'mts_license_key_valid' ) == 'true' ) {
	function mts_valid() {
		return true;
	}
} else {
	$message = sprintf( __( "Please <a href='%s'>enter your My Tickets: Sharing license key</a> to be eligible for support.", 'my-tickets-sharing' ), admin_url( 'admin.php?page=my-tickets' ) );
	add_action( 'admin_notices', create_function( '', "if ( ! current_user_can( 'manage_options' ) ) { return; } else { echo \"<div class='error'><p>$message</p></div>\";}" ) );
}	