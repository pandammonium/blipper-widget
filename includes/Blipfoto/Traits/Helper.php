<?php

namespace blipper_widget_Blipfoto\blipper_widget_Traits;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

trait blipper_widget_Helper {

	/**
	 * Get and optionally set the value for a property.
	 *
	 * @param string $property
	 * @param array $args
	 */
	public function getset($property, $args) {
		if (count($args)) {
			$this->$property = $args[0];
		}
		return $this->$property;
	}

}