<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use plibv4\Project;

/**
 * TestResult stores the results of testing a project on a specific Docker environment
 */
class TestResult {
	private Project $project;
	private DockerBuild $dockerfile;
	private ?CommandResult $composerInstall = null;
	private ?CommandResult $composerTest = null;
	private ?CommandResult $composerPsalm = null;
	private ?string $error = null;
	private float $startTime;
	private float $endTime;
	
	/**
	 * Constructor
	 * @param Project $project
	 * @param DockerBuild $dockerfile
	 */
	public function __construct(Project $project, DockerBuild $dockerfile) {
		$this->project = $project;
		$this->dockerfile = $dockerfile;
		$this->startTime = microtime(true);
	}
	
	/**
	 * Mark the test as complete
	 */
	public function complete(): void {
		$this->endTime = microtime(true);
	}
	
	/**
	 * Set composer install result
	 * @param CommandResult $result
	 */
	public function setComposerInstall(CommandResult $result): void {
		$this->composerInstall = $result;
	}
	
	/**
	 * Set composer test result
	 * @param CommandResult $result
	 */
	public function setComposerTest(CommandResult $result): void {
		$this->composerTest = $result;
	}
	
	/**
	 * Set composer psalm result
	 * @param CommandResult $result
	 */
	public function setComposerPsalm(CommandResult $result): void {
		$this->composerPsalm = $result;
	}
	
	/**
	 * Set error message
	 * @param string $error
	 */
	public function setError(string $error): void {
		$this->error = $error;
	}
	
	/**
	 * Check if all tests passed
	 * @return bool
	 */
	public function isSuccess(): bool {
		if ($this->error !== null) {
			return false;
		}
		
		if ($this->composerInstall === null || !$this->composerInstall->isSuccess()) {
			return false;
		}
		
		if ($this->composerTest === null || !$this->composerTest->isSuccess()) {
			return false;
		}
		
		if ($this->composerPsalm === null || !$this->composerPsalm->isSuccess()) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the project
	 * @return Project
	 */
	public function getProject(): Project {
		return $this->project;
	}
	
	/**
	 * Get the dockerfile
	 * @return DockerBuild
	 */
	public function getDockerFile(): DockerBuild {
		return $this->dockerfile;
	}
	
	/**
	 * Get composer install result
	 * @return CommandResult|null
	 */
	public function getComposerInstall(): ?CommandResult {
		return $this->composerInstall;
	}
	
	/**
	 * Get composer test result
	 * @return CommandResult|null
	 */
	public function getComposerTest(): ?CommandResult {
		return $this->composerTest;
	}
	
	/**
	 * Get composer psalm result
	 * @return CommandResult|null
	 */
	public function getComposerPsalm(): ?CommandResult {
		return $this->composerPsalm;
	}
	
	/**
	 * Get error message
	 * @return string|null
	 */
	public function getError(): ?string {
		return $this->error;
	}
	
	/**
	 * Get execution time in seconds
	 * @return float
	 */
	public function getExecutionTime(): float {
		if (!isset($this->endTime)) {
			return microtime(true) - $this->startTime;
		}
		return $this->endTime - $this->startTime;
	}
	
	/**
	 * Get a summary string
	 * @return string
	 */
	public function getSummary(): string {
		$status = $this->isSuccess() ? '✓ PASS' : '✗ FAIL';
		$time = number_format($this->getExecutionTime(), 2);
		
		$summary = "{$status} - {$this->project->getName()} on {$this->dockerfile} ({$time}s)\n";
		
		if (!$this->isSuccess()) {
			if ($this->error !== null) {
				$summary .= "  Error: {$this->error}\n";
			}
			if ($this->composerInstall !== null && !$this->composerInstall->isSuccess()) {
				$summary .= "  Composer install failed (exit code: {$this->composerInstall->getExitCode()})\n";
			}
			if ($this->composerTest !== null && !$this->composerTest->isSuccess()) {
				$summary .= "  Tests failed (exit code: {$this->composerTest->getExitCode()})\n";
			}
			if ($this->composerPsalm !== null && !$this->composerPsalm->isSuccess()) {
				$summary .= "  Psalm failed (exit code: {$this->composerPsalm->getExitCode()})\n";
			}
		}
		
		return $summary;
	}
}

// Made with Bob
