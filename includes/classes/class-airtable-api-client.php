<?php
/**
 * WCF7 Airtable API client class.
 *
 * @package add-on-cf7-for-airtable
 */

namespace WPC_WPCF7_AT;

use Exception;

/**
 * Airtable Api Client class
 */
class Airtable_API_Client {
	/**
	 * API Endpoint
	 *
	 * @var string
	 */
	protected $endpoint = 'https://api.airtable.com/v0';

	/**
	 * Authentication Token
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Constructor
	 *
	 * @param string $token Authentication Token.
	 */
	public function __construct( $token ) {
		$this->token = $token;
	}

	/**
	 * List bases
	 *
	 * @param array $options Endpoint options.
	 *
	 * @return mixed|object
	 * @throws Exception Exception from make_api_request and validate_response method.
	 */
	public function list_bases( $options = array() ) {
		return $this->make_api_request( '/meta/bases', $options );
	}

	/**
	 * Get tables
	 *
	 * @param string $base_id Base id.
	 *
	 * @return mixed|object
	 * @throws Exception Exception from make_api_request and validate_response method.
	 */
	public function get_tables( $base_id ) {
		return $this->make_api_request( "/meta/bases/$base_id/tables" );
	}

	/**
	 * Get table
	 */

	/**
	 * List records
	 *
	 * @param string $base_id Base id.
	 * @param string $table_id Table id.
	 * @param array  $options Endpoint options.
	 *
	 * @return mixed|object
	 * @throws Exception Exception from make_api_request and validate_response method.
	 */
	public function list_records( $base_id, $table_id, $options = array() ) {
		return $this->make_api_request( "/$base_id/$table_id", $options );
	}

	/**
	 * Get record
	 *
	 * @param string $base_id Base id.
	 * @param string $table_id Table id.
	 * @param string $record_id Record id.
	 * @param array  $options Endpoint options.
	 *
	 * @return mixed|object
	 * @throws Exception Exception from make_api_request and validate_response method.
	 */
	public function get_record( $base_id, $table_id, $record_id, $options = array() ) {
		return $this->make_api_request( "/$base_id/$table_id/$record_id", $options );
	}

	/**
	 * Create records
	 *
	 * @param string $base_id Base id.
	 * @param string $table_id Table id.
	 * @param array  $records Records to create.
	 *
	 * @return mixed|object
	 * @throws Exception Exception from make_api_request and validate_response method.
	 */
	public function create_records( $base_id, $table_id, $records ) {
		return $this->make_api_request( "/$base_id/$table_id", array( 'records' => $records ), 'POST' );
	}

	/**
	 * Performs API request
	 *
	 * @param string $url Endpoint URL.
	 * @param array  $data Data to post.
	 * @param string $type Request type.
	 *
	 * @return mixed|object
	 * @throws Exception Exception from make_api_request and validate_response method.
	 */
	protected function make_api_request( $url, $data = array(), $type = 'GET' ) {
		if ( empty( $this->token ) ) {
			throw new Exception( 'Airtable API: Missing Access Token' );
		}

		$url = $this->endpoint . $url;

		if ( 'POST' === $type ) {
			$data = wp_json_encode( $data );
			if ( false === $data ) {
				throw new Exception( 'Cannot encode body in JSON' );
			}
		}
		$args     = $this->get_request_args( array( 'body' => $data ) );
		$response = 'POST' === $type ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );
		return $this->validate_response( $response );
	}

	/**
	 * Build request args
	 *
	 * @param array $args Request args.
	 *
	 * @return mixed
	 */
	protected function get_request_args( $args = array() ) {
		return array_merge(
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 15,
			),
			$args
		);
	}

	/**
	 * Validate HTTP Response and returns data
	 *
	 * @param \WP_Error|array $response Response from Airtable API.
	 *
	 * @return mixed|object
	 * @throws Exception Error from Airtable API.
	 */
	protected function validate_response( $response ) {

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( sprintf( 'Airtable API: %s', $response->get_error_message() ) ) );
		}
		// Check HTTP code.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$body          = wp_remote_retrieve_body( $response );
			$data          = json_decode( $body );
			$error_message = $this->get_error_message( $data );

			return (object) array(
				'error'   => true,
				'message' => sprintf( 'Airtable API: %s (HTTP code %s)', $error_message, $response_code ),
			);
		}
		// Get JSON data from request body.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		if ( is_null( $data ) ) {
			return (object) array(
				'error'   => true,
				'message' => 'Airtable API: Couldn\'t decode JSON response',
			);
		}
		return $data;
	}

	/**
	 * Get error message from Airtable response
	 *
	 * @param object $data Error response data.
	 *
	 * @return mixed|string
	 */
	protected function get_error_message( $data ) {
		if ( ! empty( $data->error->message ) ) {
			return $data->error->message;
		}
		if ( ! empty( $data->error->type ) ) {
			return $data->error->type;
		}
		if ( is_string( $data->error ) ) {
			return $data->error;
		}
		return 'No error message';
	}
}
