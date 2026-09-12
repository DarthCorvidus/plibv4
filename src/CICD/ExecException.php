<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use RuntimeException;

/**
 * ExecException is thrown when execution of a command fails
 */
class ExecException extends RuntimeException {
	private string $output = "";

	/**
	 * Get command output
	 * @return string
	 */
	public function getOutput(): string {
		return $this->output;
	}

	/**
	 * Set command output
	 * @param string $output
	 */
	public function setOutput(string $output): void {
		$this->output = $output;
	}
}