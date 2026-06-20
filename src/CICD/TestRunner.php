<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use plibv4\Project;
use RuntimeException;

/**
 * TestRunner orchestrates Docker-based testing of projects using Container/Containers
 */
class TestRunner {
	private string $volumeName = 'jenkins-workspace';
	private int $totalTests = 0;
	private int $passedTests = 0;
	private int $failedTests = 0;
	
	/**
	 * Set the volume name to use
	 * @param string $volumeName
	 */
	public function setVolumeName(string $volumeName): void {
		$this->volumeName = $volumeName;
	}
	
	/**
	 * Run tests for a project on all containers
	 * @param Project $project
	 * @param Containers $containers
	 */
	public function runTests(Project $project, Containers $containers): void {
		$this->ensureVolumeExists();
		
		foreach ($containers->getContainers() as $container) {
			$this->runTest($project, $container);
		}
	}
	
	/**
	 * Run test for a project on a specific container
	 * @param Project $project
	 * @param Container $container
	 */
	public function runTest(Project $project, Container $container): void {
		$containerName = $container->getName();
		
		echo "\n" . str_repeat('=', 70) . "\n";
		echo "Testing {$project->getName()} on {$containerName}\n";
		echo str_repeat('=', 70) . "\n";
		
		try {
			// 1. Build and run container
			$container->addVolume("{$this->volumeName}:/home/jenkins");
			$container->build();
			$container->run();
			
			// 2. Clean up any existing project directory
			$container->exec('rm -rf /home/jenkins/project');
			
			// 3. Copy project files
			$this->copyProjectFiles($project, $container);
			
			// 4. Run composer commands
			$this->runComposerCommands($container);
		} catch (RuntimeException $e) {
			echo "✗ Error: {$e->getMessage()}\n";
			$this->failedTests++;
		}
	}
	
	/**
	 * Run composer commands (install, test, psalm) in the container
	 * @param Container $container
	 */
	private function runComposerCommands(Container $container): void {
		$composer = ["install", "test", "psalm"];
		foreach($composer as $value) {
			$this->totalTests++;
			echo "Running composer ".$value."...";
			$result = $container->exec('cd /home/jenkins/project && composer '.$value);
			if(!$result->isSuccess()) {
				echo "FAIL".PHP_EOL;
				$this->failedTests++;
				continue;
			}
			echo "PASS".PHP_EOL;
			$this->passedTests++;
		}
	}
	
	/**
	 * Copy project files to container
	 * @param Project $project
	 * @param Container $container
	 * @throws RuntimeException
	 */
	private function copyProjectFiles(Project $project, Container $container): void {
		echo "Copying project files...\n";
		
		$tempDir = $this->createTempCopy($project->getPath());
		
		try {
			$container->copy($tempDir . '/.', '{container}:/home/jenkins/project/');
		} finally {
			$this->removeTempDir($tempDir);
		}
		
		echo "✓ Project files copied\n";
	}
	
	/**
	 * Create a temporary copy of the project excluding vendor/ and other directories
	 * @param string $projectPath
	 * @return string Path to temp directory
	 * @throws RuntimeException
	 */
	private function createTempCopy(string $projectPath): string {
		$tempDir = sys_get_temp_dir() . '/plibv4-build-' . uniqid();
		
		if (!mkdir($tempDir, 0755, true)) {
			throw new RuntimeException("Failed to create temp directory: {$tempDir}");
		}
		
		// Use rsync to copy excluding certain directories
		$excludes = [
			'--exclude=vendor/',
			'--exclude=.git/',
			'--exclude=.github/',
			'--exclude=doc/',
			'--exclude=.vscode/',
			'--exclude=*.log',
			'--exclude=.scannerwork/'
		];
		
		$cmd = 'rsync -a ' . implode(' ', $excludes) . " {$projectPath}/ {$tempDir}/ 2>&1";
		$output = [];
		exec($cmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			$this->removeTempDir($tempDir);
			throw new RuntimeException(
				"Failed to create temp copy: " . implode("\n", $output)
			);
		}
		
		return $tempDir;
	}
	
	/**
	 * Remove temporary directory
	 * @param string $tempDir
	 */
	private function removeTempDir(string $tempDir): void {
		if (is_dir($tempDir)) {
			exec("rm -rf " . escapeshellarg($tempDir));
		}
	}
	
	/**
	 * Create the volume if it doesn't exist
	 * @throws RuntimeException
	 */
	public function ensureVolumeExists(): void {
		$output = [];
		exec("docker volume inspect {$this->volumeName} 2>&1", $output, $exitCode);
		
		if ($exitCode !== 0) {
			echo "Creating volume {$this->volumeName}...\n";
			
			exec("docker volume create {$this->volumeName} 2>&1", $output, $exitCode);
			
			if ($exitCode !== 0) {
				throw new RuntimeException(
					"Failed to create volume {$this->volumeName}: " . implode("\n", $output)
				);
			}
		}
	}
	
	/**
	 * Get total number of tests run
	 * @return int
	 */
	public function getTotalTests(): int {
		return $this->totalTests;
	}
	
	/**
	 * Get number of passed tests
	 * @return int
	 */
	public function getPassedTests(): int {
		return $this->passedTests;
	}
	
	/**
	 * Get number of failed tests
	 * @return int
	 */
	public function getFailedTests(): int {
		return $this->failedTests;
	}
	
	/**
	 * Print test summary
	 */
	public function printSummary(): void {
		echo "\n" . str_repeat('=', 70) . "\n";
		echo "TEST SUMMARY\n";
		echo str_repeat('=', 70) . "\n";
		echo "Total:  {$this->totalTests}\n";
		echo "Passed: {$this->passedTests}\n";
		echo "Failed: {$this->failedTests}\n";
		
		if ($this->failedTests === 0 && $this->totalTests > 0) {
			echo "\n✓ All tests passed!\n";
		} elseif ($this->failedTests > 0) {
			echo "\n✗ Some tests failed\n";
		}
		echo str_repeat('=', 70) . "\n";
	}
}

// Made with Bob
