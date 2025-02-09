<?php

/**
* The base class for all Blipfoto exceptions.
**/

namespace Blipper_Widget_Blipfoto\Blipper_Widget_Exception;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use \ErrorException;

class Blipper_Widget_BaseException extends \ErrorException {
	
}
