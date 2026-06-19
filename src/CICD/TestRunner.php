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
 * TestRunner orchestrates Docker-based testing of projects
 * 
 * Uses docker cp to copy project files and volume mounting for /home/jenkins
 */
class TestRunner {
	private string $volumeName = 'jenkins-workspace';
	private string $imagePrefix = 'plibv4-test';
	private bool $verbose = false;
	
	/**
	 * Set the volume name to use
	 * @param string $volumeName
	 */
	public function setVolumeName(string $volumeName): void {
		$this->volumeName = $volumeName;
	}
	
	/**
	 * Set the image prefix
	 * @param string $prefix
	 */
	public function setImagePrefix(string $prefix): void {
		$this->imagePrefix = $prefix;
	}
	
	/**
	 * Enable verbose output
	 * @param bool $verbose
	 */
	public function setVerbose(bool $verbose): void {
		$this->verbose = $verbose;
	}
	
	/**
	 * Run tests for a project on a specific Docker environment
	 * @param Project $project
	 * @param DockerBuild $dockerfile
	 * @return TestResult
	 */
	public function runTest(Project $project, DockerBuild $dockerfile): TestResult {
		$result = new TestResult($project, $dockerfile);
		$containerName = $this->generateContainerName($project, $dockerfile);
		$imageName = $this->imagePrefix . ':' . $dockerfile->getDistributionVersion();
		
		try {
			// 1. Ensure Docker image exists
			$this->ensureImageExists($dockerfile, $imageName);
			
			// 2. Start container with volume
			$this->startContainer($imageName, $containerName);
			
			// 3. Clean up any existing project directory
			$this->cleanProjectDirectory($containerName);
			
			// 4. Copy project files
			$this->copyProjectFiles($project, $containerName);
			
			// 5. Run composer install (with dev dependencies for testing)
			$installResult = $this->execInContainer(
				$containerName,
				'cd /home/jenkins/project && composer install'
			);
			$result->setComposerInstall($installResult);
			
			if (!$installResult->isSuccess()) {
				return $result;
			}
			
			// 6. Run tests
			$testResult = $this->execInContainer(
				$containerName,
				'cd /home/jenkins/project && composer test'
			);
			$result->setComposerTest($testResult);
			
			// 7. Run psalm
			$psalmResult = $this->execInContainer(
				$containerName,
				'cd /home/jenkins/project && composer psalm'
			);
			$result->setComposerPsalm($psalmResult);
			
		} catch (RuntimeException $e) {
			$result->setError($e->getMessage());
		} finally {
			// 8. Cleanup
			$this->stopAndRemoveContainer($containerName);
			$result->complete();
		}
		
		return $result;
	}
	
	/**
	 * Generate a unique container name
	 * @param Project $project
	 * @param DockerBuild $dockerfile
	 * @return string
	 */
	private function generateContainerName(Project $project, DockerBuild $dockerfile): string {
		$name = 'plibv4-test-' . $project->getName() . '-' . 
		        $dockerfile->getDistribution() . '-' . 
		        $dockerfile->getVersion() . '-' . 
		        uniqid();
		return $name;
	}
	
	/**
	 * Ensure Docker image exists, build if necessary
	 * @param DockerBuild $dockerfile
	 * @param string $imageName
	 * @throws RuntimeException
	 */
	private function ensureImageExists(DockerBuild $dockerfile, string $imageName): void {
		// Check if image exists
		$output = [];
		exec("docker images -q {$imageName} 2>&1", $output, $exitCode);
		
		if (!empty($output[0])) {
			if ($this->verbose) {
				echo "Image {$imageName} already exists\n";
			}
			return;
		}
		
		// Build image
		if ($this->verbose) {
			echo "Building image {$imageName}...\n";
		}
		
		$dockerfilePath = $dockerfile->getPath();
		$buildCmd = "docker build -t {$imageName} {$dockerfilePath} 2>&1";
		
		exec($buildCmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to build Docker image {$imageName}: " . implode("\n", $output)
			);
		}
	}
	
	/**
	 * Start a Docker container
	 * @param string $imageName
	 * @param string $containerName
	 * @throws RuntimeException
	 */
	private function startContainer(string $imageName, string $containerName): void {
		if ($this->verbose) {
			echo "Starting container {$containerName}...\n";
		}
		
		$cmd = "docker run -d --name {$containerName} " .
		       "-v {$this->volumeName}:/home/jenkins " .
		       "{$imageName} 2>&1";
		
		$output = [];
		exec($cmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to start container {$containerName}: " . implode("\n", $output)
			);
		}
		
		// Wait a moment for container to be ready
		sleep(1);
	}
	
	/**
	 * Clean up existing project directory in container
	 * @param string $containerName
	 */
	private function cleanProjectDirectory(string $containerName): void {
		if ($this->verbose) {
			echo "Cleaning project directory...\n";
		}
		
		// Remove existing project directory if it exists
		$this->execInContainer($containerName, 'rm -rf /home/jenkins/project');
	}
	
	/**
	 * Copy project files to container
	 * @param Project $project
	 * @param string $containerName
	 * @throws RuntimeException
	 */
	private function copyProjectFiles(Project $project, string $containerName): void {
		if ($this->verbose) {
			echo "Copying project files...\n";
		}
		
		$tempDir = $this->createTempCopy($project->getPath());
		
		try {
			$cmd = "docker cp {$tempDir}/. {$containerName}:/home/jenkins/project/ 2>&1";
			$output = [];
			exec($cmd, $output, $exitCode);
			
			if ($exitCode !== 0) {
				throw new RuntimeException(
					"Failed to copy files to container: " . implode("\n", $output)
				);
			}
		} finally {
			$this->removeTempDir($tempDir);
		}
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
	 * Execute a command in a container
	 * @param string $containerName
	 * @param string $command
	 * @return CommandResult
	 */
	private function execInContainer(string $containerName, string $command): CommandResult {
		if ($this->verbose) {
			echo "Executing: {$command}\n";
		}
		
		$escapedCommand = escapeshellarg($command);
		$cmd = "docker exec {$containerName} bash -c {$escapedCommand} 2>&1";
		
		$output = [];
		$exitCode = 0;
		exec($cmd, $output, $exitCode);
		
		$outputStr = implode("\n", $output);
		
		if ($this->verbose && !empty($outputStr)) {
			echo $outputStr . "\n";
		}
		
		return new CommandResult(
			$exitCode === 0,
			$exitCode,
			$outputStr,
			$command
		);
	}
	
	/**
	 * Stop and remove a container
	 * @param string $containerName
	 */
	private function stopAndRemoveContainer(string $containerName): void {
		if ($this->verbose) {
			echo "Cleaning up container {$containerName}...\n";
		}
		
		// Stop container
		exec("docker stop {$containerName} 2>&1", $output, $exitCode);
		
		// Remove container
		exec("docker rm {$containerName} 2>&1", $output, $exitCode);
	}
	
	/**
	 * Create the volume if it doesn't exist
	 * @throws RuntimeException
	 */
	public function ensureVolumeExists(): void {
		$output = [];
		exec("docker volume inspect {$this->volumeName} 2>&1", $output, $exitCode);
		
		if ($exitCode !== 0) {
			if ($this->verbose) {
				echo "Creating volume {$this->volumeName}...\n";
			}
			
			exec("docker volume create {$this->volumeName} 2>&1", $output, $exitCode);
			
			if ($exitCode !== 0) {
				throw new RuntimeException(
					"Failed to create volume {$this->volumeName}: " . implode("\n", $output)
				);
			}
		}
	}
}

// Made with Bob
