<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( !class_exists('acf_early_access') ):

class acf_early_access {
	
	/** @var string The selected version used to update */
	var $version = '';
	
	
	/**
	*  __construct
	*
	*  This function will setup the class functionality
	*
	*  @type	function
	*  @date	12/9/17
	*  @since	1.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function __construct() {
		
		// actions
		if( is_admin() ) {
			
			// modify plugins transient
			add_filter( 'pre_set_site_transient_update_plugins', array($this, 'modify_plugins_transient'), 10, 1 );
			
		}
		
	}
	
	
	/**
	*  request
	*
	*  This function will make a request to an external server
	*
	*  @type	function
	*  @date	8/4/17
	*  @since	1.0.0
	*
	*  @param	$url (string)
	*  @param	$body (array)
	*  @return	(mixed)
	*/
	
	function request( $url = '', $body = null ) {
		
		// post
		$raw_response = wp_remote_post($url, array(
			'timeout'	=> 10,
			'body'		=> $body
		));
		
		
		// wp error
		if( is_wp_error($raw_response) ) {
			
			return $raw_response;
		
		// http error
		} elseif( wp_remote_retrieve_response_code($raw_response) != 200 ) {
			
			return new WP_Error( 'server_error', wp_remote_retrieve_response_message($raw_response) );
			
		}
		
		
		// vars
		$raw_body = wp_remote_retrieve_body($raw_response);
		
		
		// attempt object
		$obj = @unserialize( $raw_body );
		if( $obj ) return $obj;
		
		
		// attempt json
		$json = json_decode( $raw_body, true );
		if( $json ) return $json;
		
		
		// return
		return $json;
		
	}
	
	
	/**
	*  get_plugin_info
	*
	*  This function will get plugin info and save as transient
	*
	*  @type	function
	*  @date	9/4/17
	*  @since	1.0.0
	*
	*  @param	n/a
	*  @return	(array)
	*/
	
	function get_plugin_info() {
		
		// var
		$transient_name = 'acf_early_access_info';
		
		
		// delete transient (force-check is used to refresh)
		if( !empty($_GET['force-check']) ) {
		
			delete_transient($transient_name);
			
		}
	
	
		// try transient
		$transient = get_transient($transient_name);
		if( $transient !== false ) return $transient;
		
		
		// connect
		$response = $this->request('http://api.wordpress.org/plugins/info/1.0/advanced-custom-fields');
		
		
		// ensure response is expected object
		if( !is_wp_error($response) ) {
			
			// store minimal data
			$info = array(
				'version'	=> $response->version,
				'versions'	=> array_keys( $response->versions ),
				'tested'	=> $response->tested
			);
			
			
			// order versions (latest first)
			$info['versions'] = array_reverse($info['versions']);
			
			
			// update var
			$response = $info;
			
		}
		
		
		// update transient
		set_transient($transient_name, $response, HOUR_IN_SECONDS);
		
		
		// return
		return $response;
		
	}
	
	
	/**
	*  modify_plugins_transient
	*
	*  This function will modify the 'update_plugins' transient with custom data
	*
	*  @type	function
	*  @date	11/9/17
	*  @since	1.0.0
	*
	*  @param	$transient (object)
	*  @return	$transient
	*/
	
	function modify_plugins_transient( $transient ) {
		
		// bail early if empty
		if( !$transient || empty($transient->checked) ) return $transient;
		
		
		// bail early if acf was not checked
		if( !isset($transient->checked['advanced-custom-fields/acf.php']) ) return $transient;
		
		
		// vars
		$info = $this->get_plugin_info();
		$old_version = $transient->checked['advanced-custom-fields/acf.php'];
		$new_version = $this->version;
		
		
		// no version selected, find latest tag
		if( !$new_version ) {
			
			// attempt to find latest tag
			foreach( $info['versions'] as $version ) {
				
				// ignore trunk
				if( $version == 'trunk' ) continue;
				
				
				// ignore if older than $old_version
				if( version_compare($version, $old_version, '<') ) continue;
				
				
				// ignore if older than $new_version
				if( version_compare($version, $new_version, '<') ) continue;
				
				
				// this tag is a newer version!
				$new_version = $version;
				
			}
			
		}
		
		
		// bail ealry if no $new_version
		if( !$new_version ) return $transient;
		
		
		// response
		$response = new stdClass();
		$response->id = 'w.org/plugins/advanced-custom-fields';
		$response->slug = 'advanced-custom-fields';
		$response->plugin = 'advanced-custom-fields/acf.php';
		$response->new_version = $new_version;
		$response->url = 'https://wordpress.org/plugins/advanced-custom-fields/';
		$response->package = 'https://downloads.wordpress.org/plugin/advanced-custom-fields.'.$new_version.'.zip';
		$response->tested = $info['tested'];
		
		
		// append
		$transient->response['advanced-custom-fields/acf.php'] = $response;
		
		
		
		// return 
        return $transient;
        
	}
	
}

// instantiate
new acf_early_access();

endif; // class_exists check

?>