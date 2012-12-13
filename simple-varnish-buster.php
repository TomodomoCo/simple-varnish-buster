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

		$this->prerequisites_met = true;		
	
	}

	/**
	 * Send a PURGE request to the Varnish cache server for the specified $url.
	 * @access protected
	 * @param string $url
	 * @return void
	 */
	protected function bust_cache_for_url( $url ) {

		$options = array(
			CURLOPT_URL		=>	$url,
			CURLOPT_USERAGENT	=>	$this->user_agent_string,
			CURLOPT_HTTPHEADER	=> 	array ('Host: ' . $this->varnish_host )
			CURLOPT_CUSTOMREQUEST	=>	'PURGE',
			CURLOPT_RETURNTRANSFER	=>	true,
			CURLOPT_TIMEOUT		=>	$this->timeout,
		);

		$request = curl_init();
		curl_setopt_array($request, $options);
		curl_exec($request);
	
	}


};

$vpm_svb_instance = new Simple_Varnish_Buster();

if ( $vpm_svb_instance->prerequisites_met ) {
	// hook up actions

}
