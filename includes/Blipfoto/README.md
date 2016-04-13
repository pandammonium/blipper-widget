# Blipfoto PHP SDK

The Blipfoto SDK for PHP lets you integreate easily with the Blipfoto API.

## Requirements

- PHP 5.4.0 or greater
- libcurl

## Installation

To install the SDK, we recommend using [Composer](https://getcomposer.org/). Just add the following to your `composer.json` file and run `composer update`:

	{
		"require": {
			"blipfoto/blipfoto": "dev-master"
		}
	}

Alternatively, you can clone the [GitHub repository](https://github.com/Blipfoto/php-sdk) and add the classes using your own auto-loader.

## Basic usage

Create a `Blipfoto\Api\Client` instance, passing in your app's `client_id` and `client_secret`:

	use Blipfoto\Api\Client;
	
	$client = new Client('abcd', '1234');
	
The client's `get()`, `post()`, `put()` and `delete()`  convenience methods all accept a resource as the first argument, and an optional array of parameters / values in the second argument. 

	$response = $client->get('user/profile', [
		'username' => 'arya'
	]);
	var_dump($response->data()); // => [...]
	
A `Response` object is returned. Its `data()` method returns an array, the contents of which depends on the resource being called. Note you can pass a period-separated key to `data()` for quick access to a nested item in the array:

	echo $response->data('user.username');	//=> 'arya'

## Uploading files

To upload files during `post()` or `put()`, pass an array of parameters / file paths in the third argument:

	$response = $client->post('entry', [
		'title' => 'Ser Bronn'
	], [
		'image => 'bronn.jpg'
	]);

## Exceptions

If something goes wrong, a subclass of `Blipfoto\Exceptions\BaseException` will be thrown. The type of exceptions are:

- `FileException`

	Thrown when a problem was encountered with a file upload.

- `NetworkException`

	Thrown when a connection fails, times out, etc.
	
- `InvalidResponseException`

	Thrown when the server returns a response that can't be understood.
	
- `OAuthException`

	Thrown when OAuth is misconfigured or the user denies permisson to your app during the auth flow.
	
- `ApiResponseException`

	Thrown when the API returns an Error object.

## Authorization

By default, the client will authorize using App auth, i.e. just sending your app's client ID. To perform an action on behalf of a user, you'll need to obtain an access token for the user using OAuth 2.

The `Blipfoto\Api\OAuth` class makes this easy. Your `Client` can create an instance for you :

	$oauth = $client->oauth();
	
Now call `authorize()`, passing in the `redirect_uri` in your app settings, and optionally the scope of the token you're requesting (default is read-only):

	$oauth->authorize('https://yoursite.com/callback', Client::SCOPE_READ_WRITE);
	
The user can now grant or deny app permissions, and will be taken back to your `redirect_uri`. On this page, we create the OAuth instance as before, obtain the access token and store it on the client:

	$oauth = $client->oauth();
	try {
		$token = $oauth->getToken();
		$client->accessToken($token['access_token']);
	} catch (OAuthException $e) {
		// handle OAuth errors here
	}

You'll probably want to store this token somewhere in your database, so your users don't have to authorize each time they use your app.

## Custom requests

There may be cases where you need more visibility into the underlying request. The `Client`'s `before()` and `after()` methods accept a callback which is invoked immediately before / after the request is sent. This lets you inspect or customize the request, for example adding your own curl options:

	$client->before(function($request) {
		// customise timeout
		curl_setopt($request->curl(), CURLOPT_CONNECTTIMEOUT, 5);
	});


## License

This software is licensed under the MIT License - see the LICENSE file for details.
