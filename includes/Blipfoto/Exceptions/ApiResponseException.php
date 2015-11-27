<?php

/**
* For when the API returns an error object.
**/

namespace blipper_widget_Blipfoto\blipper_widget_Exceptions;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Exceptions\BaseException;

class blipper_widget_ApiResponseException extends blipper_widget_BaseException {

}