<?php

/**
* The base class for all Blipfoto exceptions.
**/

namespace blipper_widget_Blipfoto\blipper_widget_Exceptions;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use \ErrorException;

class blipper_widget_BaseException extends ErrorException {
	
}