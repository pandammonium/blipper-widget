<?php

namespace Blipper_Widget_Blipfoto\Blipper_Widget_Api;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_FileException;
use Blipper_Widget_Blipfoto\Blipper_Widget_Traits\Blipper_Widget_Helper;

class Blipper_Widget_File {

	use Blipper_Widget_Helper;

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
			throw new Blipper_Widget_FileException(sprintf('File "%s" cannot be read.', $path), 1);
		}
		if ($data[2] != IMG_JPG) {
			throw new Blipper_Widget_FileException(sprintf('File "%s" is not a JPG.', $path), 240);
		}
		if ($data[0] < 600 || $data[1] < 600) {
			throw new Blipper_Widget_FileException(sprintf('File "%s" is too small.', $path), 241);
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
