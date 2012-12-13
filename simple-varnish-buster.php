<?php
/*
Plugin Name: Simple Varnish Buster
Plugin URI: http://www.vanpattenmedia.com/
Description: A simple and lightweight way for your new content to 'bust' through your Varnish cache.
Version: 1.0
Author: Peter Upfold
Author URI: http://www.vanpattenmedia.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: simple-varnish-buster
/* ----------------------------------------------*/

/*  Copyright (C) 2012 Peter Upfold.

    This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * A simple and lightweight way for your new content to 'bust' through your Varnish cache.
 * @package Simple_Varnish_Buster
 */

class Simple_Varnish_Buster {


	/**
	 * Whether or not curl (and other prerequisites) are available, ergo whether the class should hook up to WP actions.
	 * @access public
	 * @var bool
	 */
	public $prerequisites_met = false;

	/**
	 * The user agent string SVB will use when PURGEing the Varnish cache with curl
	 * @access private
	 * @var string
	 */
	private $user_agent_string = "Simple Varnish Buster/1.0";

	/**
	 * The IP address or hostname where the Varnish server is running
	 * @access private
	 * @var string
	 */
	private $varnish_host;

	/**
	 * The maximum timeout to wait for the PURGE operation to complete.
	 * @access private
	 * @var int
	 */
	private $timeout;

	/**
	 * Constructor, which checks for prerequisites
	 */
	public function __construct() {
		// we do require curl!
		if ( ! function_exists( 'curl_init') ) {
			trigger_error( __( 'Simple Varnish Buster requires curl to be loaded and available within PHP. (Install a php5-curl package?)', 'simple-varnish-buster') , E_USER_WARNING );
			$this->prerequisites_met = false;

			return false;
		}
	
                // set up varnish host
                $this->varnish_host = parse_url( get_option( 'svb_varnish_host' ), PHP_URL_HOST );          
                $port = parse_url ( get_option( 'svb_varnish_host' ), PHP_URL_PORT );

                if ( $port ) { 
                        $this->varnish_host .= ':' . intval( $port );
                }   
                    
                $this->timeout = intval( get_option( 'svb_timeout' ) );
                    
                if ( ! $this->varnish_host || ! $this->timeout ) { 
                        $this->initial_setup();
                }   

                $this->prerequisites_met = true;  	
	}

	/**
	 * Initial setup of the default options for Varnish host and timeout upon initial plugin activation.
	 * @access public
	 * @return void
	 */
	public function initial_setup() {
		// set some sensible defaults

		$default_varnish_host = 'http://127.0.0.1';
		$default_timeout = '1';

		if ( ! get_option( 'svb_varnish_host' ) || ! parse_url( get_option( 'svb_varnish_host' ), PHP_URL_HOST) ) {
			add_option( 'svb_varnish_host', $default_varnish_host, '', 'yes' );
			$this->varnish_host = parse_url( $default_varnish_host, PHP_URL_HOST );
		}

		if ( ! get_option( 'svb_timeout' ) ) {
			add_option( 'svb_timeout', $default_timeout, '', 'yes' );
			$this->timeout = intval( $default_timeout );
		}
		
	}

	/**
	 * Bust the cache for the given post_id. Also expire the homepage and feed caches. Should run on edit_post.
	 * @access public
	 * @return void 
	 */
	public function cache_bust_post( $post_id ) {

		$post_url = get_permalink( $post_id );

		$this->bust_cache_for_url( $post_url );
		
		// also expire homepage
		$home = get_home_url( null, '', 'http' );
		// if there is no path, add a trailing slash so there is one!
		if ( ! parse_url( $home, PHP_URL_PATH ) ) {
			$home .= '/';
		}

		$this->bust_cache_for_url( $home );

		// also expire feeds
		$feed_urls[] = get_bloginfo( 'rss2_url' );
		$feed_urls[] = get_bloginfo( 'atom_url' );
		$feed_urls[] = get_bloginfo( 'rdf_url' );
		$feed_urls[] = get_bloginfo( 'rss_url' );
	
		foreach( $feed_urls as $feed ) {
			$this->bust_cache_for_url( $feed );
		}
	
	}

	/**
	 * Bust the cache for this post as it transitions between post statuses
	 * @access public
	 * @return void
	 */
	public function cache_bust_post_transitional( $old, $new, $post ) {
		$post_id = $post->ID;
		$this->cache_bust_post( $post_id );
	}

	/**
	 * Bust the cache for a post when comments on it have been altered.
	 * @access public
	 * @return void
	 */
	public function cache_bust_comments( $comment_id ) {
		$comment = get_comment( $comment_id );

		// only bust cache if this is not spam
		if ( '1' == $comment->comment_approved || 'trash' == $comment->comment_approved ) {
			$post_id = $comment->comment_post_ID;
			$this->cache_bust_post( $post_id );	
		}
	
	}

	/**
	 * Send a PURGE request to the Varnish cache server for the specified $url.
	 * @access protected
	 * @param string $url
	 * @return void
	 */
	protected function bust_cache_for_url( $url ) {

		// split up URL, so we can target the actual Varnish server in the CURLOPT_URL,
		// but then use the Host header to ensure it knows which site we are working with

		// for example, we will always target '127.0.0.1' in the URL, so we use the loopback iface
		// and therefore Varnish lets us PURGE, but we still need to tell it which Host it
		// is working with!
		$url_parts = parse_url( $url );

		if (
			is_array( $url_parts ) &&
			count( $url_parts ) > 0 &&
			array_key_exists( 'scheme', $url_parts ) &&
			array_key_exists( 'path', $url_parts ) &&
			array_key_exists( 'host', $url_parts )
		) {
			// add the query string in with its preceding '?' character, or set it to a blank string
			$url_parts['query'] = array_key_exists( 'query', $url_parts ) ? '?' . $url_parts['query'] : '';	

			$reconstructed_url = $url_parts['scheme'] . '://' . $this->varnish_host . $url_parts['path'] . $url_parts['query'];
		}
		else {
			return false;
		}

		$options = array(
			CURLOPT_URL		=>	$reconstructed_url,
			CURLOPT_USERAGENT	=>	$this->user_agent_string,
			CURLOPT_HTTPHEADER	=> 	array ('Host: ' . $url_parts['host'] ),
			CURLOPT_CUSTOMREQUEST	=>	'PURGE',
			CURLOPT_RETURNTRANSFER	=>	true,
			CURLOPT_TIMEOUT		=>	$this->timeout,
		);

		$request = curl_init();
		curl_setopt_array($request, $options);
		curl_exec($request);
	
	}

	/**
	 * Register a settings page for the plugin, so that the options can be configured.
	 * @access public
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Simple Varnish Buster', 'simple-varnish-buster' ),		/* page title */
			__( 'Simple Varnish Buster', 'simple-varnish-buster' ),		/* menu title */
			'manage_options',						/* capability */
			'simple-varnish-buster',					/* menu slug */
			array( $this, 'print_settings_page')				/* callback */	
		);
	}

	/**
	 * Handle and print to output the plugin's settings page.
	 * @access public
	 * @return void
	 */
	public function print_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<h1>';
			_e( 'You do not have permission to manage Varnish settings.', 'simple-varnish-buster' );
			echo '</h1>';
			die();	
		}

		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div><h2><?php _e( 'Simple Varnish Buster', 'simple-varnish-buster' ); ?></h2>

			<form method="post" action="options-general.php?page=simple-varnish-buster">
				<?php wp_nonce_field( 'simple-varnish-buster-settings' ); ?>
				
				<table class="form-table edit-controls-form-table">
					<tbody>
						<tr class="form-field">
							<th scope="row">
								<label for="varnish_host"><?php _e( 'Varnish Host', 'simple-varnish-buster' ); ?></label>
							</th>
							<td><fieldset>
								<input type="text" name="varnish_host" id="varnish_host" value="<?php echo esc_attr( get_option( 'svb_varnish_host' ) ); ?>" />
								<br />
								<span class="description"><?php _e( 'Address of Varnish server (including port, if required)', 'simple-varnish-buster' ); ?></span>
							</fieldset></td>
						</tr>
						<tr class="form-field">
							<th scope="row">
								<label for="timeout"><?php _e( 'Maximum Timeout', 'simple-varnish-buster' ); ?></label>
							</th>
							<td><fieldset>
								<input type="text" name="timeout" id="timeout" value="<?php echo esc_attr( get_option( 'svb_timeout' ) ); ?>" />
								<br />
								<span class="description"><?php _e( 'Maximum time to wait for Varnish to clear the cache for any given page. Must be an integer, greater than zero.', 'simple-varnish-buster' ); ?></span>
							</fieldset></td>
						</tr>
	
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" id="submit" />
				</p>

			</form>	
		</div>
		<?php		
	
	}


};

$vpm_svb_instance = new Simple_Varnish_Buster();

if ( $vpm_svb_instance->prerequisites_met ) {
	// hook up actions

	add_action( 'edit_post', array( $vpm_svb_instance, 'cache_bust_post' ), 99 );
	add_action( 'delete_post', array( $vpm_svb_instance, 'cache_bust_post' ), 99);
	add_action( 'transition_post_status', array( $vpm_svb_instance, 'cache_bust_post_transitional' ), 99 );

	add_action( 'comment_post', array( $vpm_svb_instance, 'cache_bust_comments' ), 99 );
	add_action( 'edit_comment', array( $vpm_svb_instance, 'cache_bust_comments' ), 99 );
	add_action( 'trashed_comment', array( $vpm_svb_instance, 'cache_bust_comments' ) , 99 );
	add_action( 'untrashed_comment', array( $vpm_svb_instance, 'cache_bust_comments' ), 99 );
	add_action( 'deleted_comment', array( $vpm_svb_instance, 'cache_bust_comments' ), 99 );

	add_action( 'admin_menu', array( $vpm_svb_instance, 'add_settings_page' ) );
	
	register_activation_hook( __FILE__, array( $vpm_svb_instance, 'initial_setup' ) );

}
