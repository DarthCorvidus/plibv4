<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4;

use RuntimeException;

/**
 * Project represents a single plibv project
 */
class Project {
	private string $path;
	
	/**
	 * Constructor
	 * @param string $path Path to the project directory
	 */
	public function __construct(string $path) {
		$this->path = rtrim($path, '/');
	}
	
	/**
	 * Check if psalm.xml exists in the project
	 * @return bool True if psalm.xml exists
	 */
	public function hasPsalm(): bool {
		return file_exists($this->path . '/psalm.xml');
	}
	
	/**
	 * Check if phpunit.xml exists in the project
	 * @return bool True if phpunit.xml exists
	 */
	public function hasPHPUnit(): bool {
		return file_exists($this->path . '/phpunit.xml');
	}
	
	/**
	 * Check if composer.json exists in the project
	 * @return bool True if composer.json exists
	 */
	public function hasComposer(): bool {
		return file_exists($this->path . '/composer.json');
	}
	
	/**
	 * Check if project is complete (has all three required files)
	 * @return bool True if composer.json, phpunit.xml, and psalm.xml all exist
	 */
	public function isComplete(): bool {
		return $this->hasComposer() && $this->hasPHPUnit() && $this->hasPsalm();
	}
	
	/**
	 * Get path to psalm.xml
	 * @return string Path to psalm.xml
	 * @throws RuntimeException If psalm.xml doesn't exist
	 */
	public function getPsalm(): string {
		if (!$this->hasPsalm()) {
			throw new RuntimeException("psalm.xml not found in {$this->path}");
		}
		return $this->path . '/psalm.xml';
	}
	
	/**
	 * Get path to phpunit.xml
	 * @return string Path to phpunit.xml
	 * @throws RuntimeException If phpunit.xml doesn't exist
	 */
	public function getPHPUnit(): string {
		if (!$this->hasPHPUnit()) {
			throw new RuntimeException("phpunit.xml not found in {$this->path}");
		}
		return $this->path . '/phpunit.xml';
	}
	
	/**
	 * Get path to composer.json
	 * @return string Path to composer.json
	 * @throws RuntimeException If composer.json doesn't exist
	 */
	public function getComposer(): string {
		if (!$this->hasComposer()) {
			throw new RuntimeException("composer.json not found in {$this->path}");
		}
		return $this->path . '/composer.json';
	}
}

// Made with Bob
