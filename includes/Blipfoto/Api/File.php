<?php

namespace blipper_widget_Blipfoto\blipper_widget_Api;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_FileException;
use blipper_widget_Blipfoto\blipper_widget_Traits\blipper_widget_Helper;

class blipper_widget_File {

	use blipper_widget_Helper;

	protected $path;

	/**
	 * Create new Upload instance.
	 *
	 * @param string $path;
	 */
	public function __construct($path) {
		$this->path($path);
	}

	/**
	 * Get or set the path.
	 *
	 * @param string $path (optional)
	 * @return string
	 */
	public function path() {
		$args = func_get_args();
		if (count($args)) {
			$this->path = $this->verify($args[0]);
		}
		return $this->path;
	}

	/**
	 * Verify the file at a path.
	 *
	 * @param string $path
	 * @return string
	 * @throws FileException
	 */
	public function verify($path) {
		$full_path = realpath($path);
		$data = @getimagesize($full_path);
		if (!$data) {
			throw new blipper_widget_FileException(sprintf('File "%s" cannot be read.', $path), 1);
		}
		if ($data[2] != IMG_JPG) {
			throw new blipper_widget_FileException(sprintf('File "%s" is not a JPG.', $path), 240);
		}
		if ($data[0] < 600 || $data[1] < 600) {
			throw new blipper_widget_FileException(sprintf('File "%s" is too small.', $path), 241);
		}
		return $full_path;
	}

	/**
	 * Returns the name of the file, including the extension.
	 *
	 * @return string
	 */
	public function name() {
		return basename($this->path);
	}

	/**
	 * Returns the contents of the file.
	 *
	 * @return string
	 */
	public function contents() {
		return file_get_contents($this->path);
	}
}