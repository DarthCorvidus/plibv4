<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

/**
 * CommandResult represents the result of executing a command
 */
class CommandResult {
	private bool $success;
	private int $exitCode;
	private string $output;
	private string $command;
	
	/**
	 * Constructor
	 * @param bool $success Whether the command succeeded
	 * @param int $exitCode Exit code from the command
	 * @param string $output Command output (stdout and stderr)
	 * @param string $command The command that was executed
	 */
	public function __construct(bool $success, int $exitCode, string $output, string $command = '') {
		$this->success = $success;
		$this->exitCode = $exitCode;
		$this->output = $output;
		$this->command = $command;
	}
	
	/**
	 * Check if command was successful
	 * @return bool
	 */
	public function isSuccess(): bool {
		return $this->success;
	}
	
	/**
	 * Get exit code
	 * @return int
	 */
	public function getExitCode(): int {
		return $this->exitCode;
	}
	
	/**
	 * Get command output
	 * @return string
	 */
	public function getOutput(): string {
		return $this->output;
	}
	
	/**
	 * Get the command that was executed
	 * @return string
	 */
	public function getCommand(): string {
		return $this->command;
	}
}

// Made with Bob
