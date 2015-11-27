<?php

/**
* For cases where the API response can't be understood.
**/

namespace blipper_widget_Blipfoto\blipper_widget_Exceptions;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Exceptions\BaseException;

class blipper_widget_InvalidResponseException extends blipper_widget_BaseException {
	
}