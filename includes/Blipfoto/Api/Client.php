<?php

namespace blipper_widget_Blipfoto\blipper_widget_Api;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use \ReflectionClass;
use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_File;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_NetworkException;
use blipper_widget_Blipfoto\blipper_widget_Traits\blipper_widget_Helper;

class blipper_widget_client {

	use blipper_widget_Helper;

	protected $id;
	protected $secret;
	protected $access_token;
	protected $endpoint;
	protected $authorization_endpoint;
	protected $before;
	protected $after;

	// Endpoint constants
	const URI_API 			= 'https://api.blipfoto.com/4/';
	const URI_AUTHORIZE 	= 'https://www.blipfoto.com/blipper_widget_OAuth/authorize/';

	// scope constants
	const SCOPE_READ 		= 'read';
	const SCOPE_READ_WRITE	= 'read,write';

	// misc constants
	const SESSION_PREFIX 	= 'blipfoto_';

	/**
	 * Create new Client instance.
	 *
	 * @param string $id
	 * @param string $secret
	 * @param string $access_token (optional)
	 */
	public function __construct($id, $secret, $access_token = null) {
		$this->id($id);
		$this->secret($secret);
		$this->accessToken($access_token);
	}

	/**
	 * Get and optionally set the id.
	 *
	 * @param string $id (optional)
	 * @return string
	 */
	public function id() {
		return $this->getset('id', func_get_args());
	}

	/**
	 * Get and optionally set the secret.
	 *
	 * @param string $secret (optional)
	 * @return string
	 */
	public function secret() {
		return $this->getset('secret', func_get_args());
	}

	/**
	 * Get and optionally set the user access token.
	 *
	 * @param string $access_token (optional)
	 * @return string
	 */
	public function accessToken() {
		return $this->getset('access_token', func_get_args());
	}

	/**
	 * Get and optionally set the beforeRequest callback.
	 *
	 * @param callable $before (optional)
	 * @return mixed
	 */
	public function before() {
		return $this->getset('before', func_get_args());
	}

	/**
	 * Get and optionally set the afterRequest callback.
	 *
	 * @param callable $after (optional)
	 * @return mixed
	 */
	public function after() {
		return $this->getset('after', func_get_args());
	}

	/**
	 * Get and optionally set the endpoint.
	 *
	 * @param string $endpoint (optional)
	 * @return string
	 */
	public function endpoint() {
		$endpoint = $this->getset('endpoint', func_get_args());
		return $this->validateEndpoint($endpoint ?: self::URI_API);
	}

	/**
	 * Get and optionally set the authorization endpoint.
	 *
	 * @param string $authorization_endpoint (optional)
	 * @return string
	 */
	public function authorizationEndpoint() {
		$endpoint = $this->getset('authorization_endpoint', func_get_args());
		return $this->validateEndpoint($endpoint ?: self::URI_AUTHORIZE);
	}

	/**
	 * Convenience method for creating a new blipper_widget_Request instance.
	 *
	 * @param mixed
	 * @return blipper_widget_Request
	 */	
	public function request() {
		return new blipper_widget_Request($this);
	}

	/**
	 * Convenience method for creating a new blipper_widget_OAuth instance.
	 *
	 * @return blipper_widget_OAuth
	 */
	public function OAuth() {
		return new blipper_widget_OAuth($this);
	}

	/**
	 * Convenience method for creating and sending a new GET blipper_widget_Request.
	 */
	public function get() {
		return $this->run('GET', func_get_args());
	}

	/**
	 * Convenience method for creating and sending a new POST blipper_widget_Request.
	 */
	public function post() {
		return $this->run('POST', func_get_args());
	}

	/**
	 * Convenience method for creating and sending a new PUT blipper_widget_Request.
	 */
	public function put() {
		return $this->run('PUT', func_get_args());
	}

	/**
	 * Convenience method for creating and sending a new DELETE blipper_widget_Request.
	 */
	public function delete() {
		return $this->run('DELETE', func_get_args());
	}

	/**
	 * Convenience method for sending a blipper_widget_Request and returning a response.
	 *
	 * @return Response
	 * @throws OAuthException|ApiResponseException
	 */
	protected function run($method, $args) {
		$request = $this->request();
		$request->method($method);
		$request->resource(array_shift($args));
		if (count($args)) {
			$request->params(array_shift($args));
		}
		if (count($args)) {
			$request->files(array_shift($args));
		}
		return $request->send();
	}

	/**
	 * Ensures that an endoint is valid.
	 *
	 * @param string $endpoint
	 * @return string
	 * @throws NetworkException
	 */
	protected function validateEndpoint($endpoint) {
		if (!preg_match("/^https/", $endpoint)) {
			throw new blipper_widget_NetworkException(sprintf('Invalid endpoint "%s" does not use the HTTPS protocol.', $endpoint), -1);
		}
		return $endpoint;
	}

}