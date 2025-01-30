<?php

namespace Blipper_Widget_Blipfoto\Blipper_Widget_Api;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use Blipper_Widget_Blipfoto\Blipper_Widget_Api\Blipper_Widget_Client;
use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_OAuthException;

class Blipper_Widget_OAuth {

	protected $Blipper_Widget_Client;
	protected $oauth_key;

	/**
	 * Construct a new oauth instance.
	 *
	 * @param Blipper_Widget_Client $Blipper_Widget_Client
	 */
	public function __construct(Blipper_Widget_Client $Blipper_Widget_Client) {
		$this->Blipper_Widget_Client = $Blipper_Widget_Client;
		$this->oauth_key = Blipper_Widget_Client::SESSION_PREFIX . 'params';

		if (session_status() == PHP_SESSION_NONE) {
		    session_start();
		}
	}
	
	/**
	 * Begin authorization.
	 *
	 * @param string $redirect_uri
	 * @param string $scope (optional)
	 * @redirect
 	 */
	public function authorize($redirect_uri, $scope = Blipper_Widget_Client::SCOPE_READ) {
		header('Location: ' . $this->getAuthorizeUri($redirect_uri, $scope));
		exit;
	}
	
 	/**
	 * Generate and return the authorization URI.
	 *
	 * @param string $redirect_uri
	 * @param string $scope (optional)
	 * @return string
 	 */
	public function getAuthorizeUri($redirect_uri, $scope = Blipper_Widget_Client::SCOPE_READ) {

		$state = sha1(mt_rand());

		$_SESSION[$this->oauth_key] = [
			'redirect_uri'	=> $redirect_uri,
			'scope'			=> $scope,
			'state'			=> $state,
		];

		return $this->Blipper_Widget_Client->authorizationEndpoint() . '?' . http_build_query([
			'response_type'	=> 'code',
			'client_id'		=> $this->Blipper_Widget_Client->id(),
			'client_secret' => $this->Blipper_Widget_Client->secret(),
			'redirect_uri'	=> $redirect_uri,
			'scope'			=> $scope,
			'state'			=> $state,
		]);
	}

	/**
	 * Obtain an authorization code.
	 *
	 * @return string
	 * @throws Blipper_Widget_OAuthException
	 */
	public function getAuthorizationCode() {

		if (isset($_GET['error'])) {
			throw new Blipper_Widget_OAuthException($_GET['error'], 1);
		} elseif (!isset($_GET['code']) || !isset($_GET['state'])) {
			throw new Blipper_Widget_OAuthException('Invalid parameters', 2);
		} elseif (!isset($_SESSION[$this->oauth_key]['state'])) {
			throw new Blipper_Widget_OAuthException('No state found', 3);
		} elseif ($_GET['state'] != $_SESSION[$this->oauth_key]['state']) {
			throw new Blipper_Widget_OAuthException('State invalid', 4);
		}

		return $_GET['code'];
	}

	/**
	 * Swap an authorization code for a token.
	 *
	 * @param string $authorization_code (optional)
	 * @return array
	 */
	public function getToken($authorization_code = null) {

		if ($authorization_code == null) {
			$authorization_code = $this->getAuthorizationCode();
		}

		$params = $_SESSION[$this->oauth_key];
		unset($_SESSION[$this->oauth_key]);

		$response = $this->Blipper_Widget_Client->post('oauth/token', [
			'client_id'		=> $this->Blipper_Widget_Client->id(),
			'grant_type'	=> 'authorization_code',
			'code'			=> $authorization_code,
			'scope'			=> $params['scope'],
			'redirect_uri'	=> $params['redirect_uri'],
		]);
		return $response->data('token');
	}

}
