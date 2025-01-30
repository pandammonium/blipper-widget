<?php

/**
* For cases where the API response can't be understood.
**/

namespace Blipper_Widget_Blipfoto\Blipper_Widget_Exception;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\BaseException;

class Blipper_Widget_InvalidResponseException extends Blipper_Widget_BaseException {
	
}
