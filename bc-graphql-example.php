<?php
/*
Plugin Name: BigCommerce GraphQL Example
Description: Creates example methods for getting and processing data from BigComcer Servers
Version: 1.0
Author: Topher
Author URI: http://topher1kenobe.com
License: GPLv3+
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

/**
 * Instantiate the BC_GraphQL_Example instance
 * @since BC_GraphQL_Example 1.0
 */
add_action( 'init', [ 'BC_GraphQL_Example', 'instance' ] );

class BC_GraphQL_Example {

	/**
	* Instance handle
	*
	* @static
	* @since 1.2
	* @var string
	*/
	private static $__instance = null;

	/**
	* Holds the API Token
	*
	* @access private
	* @since  1.0
	* @var	  string
	*/
	private $access_token = null;

	/**
	* Holds the API Client Key
	*
	* @access public
	* @since  1.0
	* @var	  object
	*/
	private $api_client = null;

	/**
	* Holds the store hash
	*
	* @access private
	* @since  1.0
	* @var	  object
	*/
	private $store_hash = null;

	/**
	* Holds the channel id
	*
	* @access private
	* @since  1.0
	* @var	  object
	*/
	private $channel_id = null;

	/**
	* Holds the channel name
	*
	* @access private
	* @since  1.0
	* @var	  object
	*/
	private $channel_name = null;

	/**
	* Holds the auth token
	*
	* @access private
	* @since  1.0
	* @var	  object
	*/
	private $auth_token = null;

	/**
	* BigCommerce store URL
	*
	* @access private
	* @since  1.0
	* @var	  object
	*/
	private $home_url = null;

	/**
	 * Constructor, actually contains nothing
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
	}

	/*
	 * Instance initiator, runs setup etc.
	 *
	 * @static
	 * @access public
	 * @return self
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self;
			self::$__instance->class_init();
			self::$__instance->get_local_auth_token();
		}

		return self::$__instance;
	}

	/*
	 * Get the store hash
	 *
	 * @access private
	 * @return string $output
	 */
	private function bc_get_store_hash() {

		$url = get_option( 'bigcommerce_store_url' );

		preg_match( '#stores/([^\/]+)/#', $url, $matches );
		if ( empty( $matches[ 1 ] ) ) {
			$output = '';
		} else {
			$output = $matches[ 1 ];
		}

		return $output;

	}

	/*
	 * Set the vars we need and create the shortcode
	 *
	 * @access public
	 * @return NULL
	 */
	public function class_init() {
		$this->home_url	    = get_option( 'bigcommerce_store_url' );
		$this->access_token	= get_option( 'bigcommerce_access_token' );
		$this->client_id	= get_option( 'bigcommerce_client_id' );
		$this->store_hash	= $this->bc_get_store_hash();
		$this->channel_id	= get_option( 'bigcommerce_channel_id' );
		$this->channel_name = get_option( 'bigcommerce_channel_name' );
		$this->get_local_auth_token();

		add_shortcode( 'render_graphql_data', [ $this, 'get_graphql_data' ] );
	}

	/*
	 * Get the auth token from local option
	 *
	 * @access public
	 * @return NULL
	 */
	public function get_local_auth_token() {

		// get the token from the options table
		$this->auth_token = get_option( 'bigcommerce_auth_token' );

		// if the local copy is empty, go get one from BigCommerce
		if ( empty( $this->auth_token ) ) {

			// get the token
			$token = $this->get_remote_auth_token();

			// set the local copy for next time
			update_option( 'bigcommerce_auth_token', $token );

			// set the token for this instance
			$this->auth_token = $token;

		}

	}

	/*
	 * Get the auth token from BigCommerce
	 *
	 * @access public
	 * @return string $token
	 */
	public function get_remote_auth_token() {

		// Set up the REST authentication headers
		$headers[ 'X-Auth-Token' ]	= $this->access_token;
		$headers[ 'X-Auth-Client' ] = $this->client_id;
		$headers[ 'content-type' ]  = 'application/json';

		// The token needs an expiration. You can either set it short
		// and manage recreation, or set it very long and never change
		// Regardless it needs to be a UTC timestamp
		$expires = date('U') + 100000000;

		// Set up the query to get the token. Formatted in JSON, requires
		// the channel ID, the expiration, and CORS allowance.
		$query = '
			{
			  "channel_id": ' . $this->channel_id . ',
			  "expires_at": ' . $expires . ',
			  "allowed_cors_origins": [
				"' . get_home_url() . '"
			  ]
			}
		';

		// set up the POST args
		$args[ 'method']  = 'POST';
		$args[ 'headers'] = $headers;
		$args[ 'body' ]   = $query;

		// The request gets sent to the token API endpoint with your 
		// store hash in the URL.
		$url = 'https://api.bigcommerce.com/stores/' . $this->store_hash . '/v3/storefront/api-token';

		// send the query
		$request = wp_safe_remote_post( $url, $args );

		// get the body out of the result
		$body = json_decode( $request['body'] );

		// get the token out of the body
		$token = $body->data->token;

		return $token;

	}

	/**
	 * Get Data from BigCommerce and return it
	 *
	 * @access public
	 * @return string $output
	 */
	public function get_graphql_data() {

		// Set up the headers
		$headers[ 'content-type' ] = 'application/json';
		$headers[ 'Authorization' ] = 'Bearer ' . $this->auth_token;

		// Set up the POST arguments. These need Headers, Method, and Body
		// headers holds the headers array we created above
		$args[ 'headers'] = $headers;
		$args[ 'method']  = 'POST';


        // set up the query
        $query = '
			query paginateProducts {
				  site {
					products (first: 4) {
					  pageInfo {
						startCursor
						endCursor
					  }
					  edges {
						cursor
						node {
						  entityId
						  name
						  path
						  images {
							edges {
							  node {
								...responsiveImageFragment
							  }
							}
						  }
						  variants {
							edges {
							  node {
								entityId
								defaultImage {
								  ...responsiveImageFragment
								}
							  }
							}
						  }
						}
					  }
					}
				  }
				}
			fragment responsiveImageFragment on Image {
				url320wide: url(width: 320)
				url640wide: url(width: 640)
				url960wide: url(width: 960)
				url1280wide: url(width: 1280)
			}
		';

		$args[ 'body' ] = wp_json_encode([
			'query' => $query
		]);

		$url  = 'https://store-' . $this->store_hash . '-' . $this->channel_id . '.mybigcommerce.com/graphql';

		$request = wp_safe_remote_post( $url, $args );

		$bc_data = json_decode( wp_remote_retrieve_body( $request ) );

		$output .= '<pre>';
		$output .= print_r( $bc_data, 1 );
		$output .= '</pre>';

		return $output;

	}

}
?>
