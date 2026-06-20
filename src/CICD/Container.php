<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use InvalidArgumentException;
use RuntimeException;

/**
 * Container represents a Docker container with its image path, name, and tag
 *
 * Validates that a Dockerfile exists in the specified path
 */
class Container {
	private string $path;
	private string $name;
	private string $tag;
	/** @var list<string> */
	private array $volumes = [];
	/** @var array<string, string> */
	private array $annotations = [];
	
	/**
	 * Constructor
	 * @param string $path Path to Dockerfile directory
	 * @param string $name Container name
	 * @param string $tag Image tag
	 * @throws InvalidArgumentException If no Dockerfile exists in path
	 */
	public function __construct(string $path, string $name, string $tag) {
		$this->path = rtrim($path, '/');
		$this->name = $name;
		$this->tag = $tag;
		
		$this->validateDockerfile();
	}
	
	/**
	 * Add a volume mount for the container
	 * @param string $volume Volume mount in format "source:destination" or "volumeName:destination"
	 */
	public function addVolume(string $volume): void {
		$this->volumes[] = $volume;
	}
	
	/**
	 * Add an annotation (key-value metadata)
	 * @param string $key Annotation key
	 * @param string $value Annotation value
	 */
	public function addAnnotation(string $key, string $value): void {
		$this->annotations[$key] = $value;
	}
	
	/**
	 * Get an annotation value by key
	 * @param string $key Annotation key
	 * @return string|null Annotation value or null if not found
	 */
	public function getAnnotation(string $key): ?string {
		return $this->annotations[$key] ?? null;
	}
	
	/**
	 * Check if an annotation exists
	 * @param string $key Annotation key
	 * @return bool True if annotation exists
	 */
	public function hasAnnotation(string $key): bool {
		return isset($this->annotations[$key]);
	}
	
	/**
	 * Validate that Dockerfile exists in the path
	 * @throws InvalidArgumentException If Dockerfile does not exist
	 */
	private function validateDockerfile(): void {
		$dockerfilePath = $this->path . '/Dockerfile';
		
		if (!file_exists($dockerfilePath)) {
			throw new InvalidArgumentException(
				"No Dockerfile found in path: '{$this->path}'"
			);
		}
	}
	
	/**
	 * Get the path
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}
	
	/**
	 * Get the container name
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}
	
	/**
	 * Get the image tag
	 * @return string
	 */
	public function getTag(): string {
		return $this->tag;
	}
	
	/**
	 * Build the Docker image
	 * @throws RuntimeException If build fails
	 */
	public function build(): void {
		$imageName = $this->name . ':' . $this->tag;
		echo "Building image {$imageName}...";
		$buildCmd = "docker build -t {$imageName} {$this->path} 2>&1";
		
		$output = [];
		exec($buildCmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			echo PHP_EOL;
			throw new RuntimeException("Failed to build Docker image {$imageName}: " . implode("\n", $output));
		}
		echo "...successful!".PHP_EOL;
	}
	
	/**
	 * Run the Docker container or reuse existing one
	 * @throws RuntimeException If container fails to start
	 */
	public function run(): void {
		$imageName = $this->name . ':' . $this->tag;
		
		// Check if container already exists
		$output = [];
		exec("docker ps -a --filter name=^{$this->name}$ --format '{{.Names}}' 2>&1", $output, $exitCode);
		
		if (!empty($output) && trim($output[0]) === $this->name) {
			// Container exists, check if it's running
			$output = [];
			exec("docker ps --filter name=^{$this->name}$ --format '{{.Names}}' 2>&1", $output, $exitCode);
			
			if (!empty($output) && trim($output[0]) === $this->name) {
				// Container is already running
				echo "Reusing running container {$this->name}\n";
				return;
			}
			
			// Container exists but is stopped, start it
			echo "Starting existing container {$this->name}...\n";
			
			$output = [];
			exec("docker start {$this->name} 2>&1", $output, $exitCode);
			
			if ($exitCode !== 0) {
				throw new RuntimeException("Failed to start existing container {$this->name}: " . implode("\n", $output));
			}
			
			// Wait a moment for container to be ready
			sleep(1);
			return;
		}
		
		// Container doesn't exist, create it
		echo "Creating new container {$this->name}...\n";
		
		// Build volume mounts
		$volumeArgs = '';
		foreach ($this->volumes as $volume) {
			$volumeArgs .= " -v {$volume}";
		}
		
		$cmd = "docker run -d --name {$this->name}{$volumeArgs} {$imageName} 2>&1";
		
		$output = [];
		exec($cmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException("Failed to start container {$this->name}: " . implode("\n", $output));
		}
		
		// Wait a moment for container to be ready
		sleep(1);
	}
	
	/**
	 * Copy files to or from the container using docker cp
	 * @param string $source Source path (local path or container:path)
	 * @param string $destination Destination path (local path or container:path)
	 * @throws RuntimeException If copy fails
	 */
	public function copy(string $source, string $destination): void {
		// Replace container placeholder with actual container name
		$source = str_replace('{container}', $this->name, $source);
		$destination = str_replace('{container}', $this->name, $destination);
		
		$cmd = "docker cp {$source} {$destination} 2>&1";
		
		$output = [];
		exec($cmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to copy from '{$source}' to '{$destination}': " . implode("\n", $output)
			);
		}
	}
	
	/**
	 * Execute a command in the container
	 * @param string $command Command to execute
	 * @return CommandResult Result of the command execution
	 */
	public function exec(string $command): CommandResult {
		$escapedCommand = escapeshellarg($command);
		$cmd = "docker exec {$this->name} bash -c {$escapedCommand} 2>&1";
		
		$output = [];
		$exitCode = 0;
		exec($cmd, $output, $exitCode);
		
		$outputStr = implode("\n", $output);
		
		return new CommandResult(
			$exitCode === 0,
			$exitCode,
			$outputStr,
			$command
		);
	}
	
	/**
	 * Stop the container
	 * @throws RuntimeException If container fails to stop
	 */
	public function stop(): void {
		echo "Stopping container {$this->name}...\n";
		
		$output = [];
		exec("docker stop {$this->name} 2>&1", $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to stop container {$this->name}: " . implode("\n", $output)
			);
		}
	}
	
	/**
	 * Delete the container
	 * @throws RuntimeException If container fails to be removed
	 */
	public function delete(): void {
		echo "Deleting container {$this->name}...\n";
		
		$output = [];
		exec("docker rm {$this->name} 2>&1", $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to delete container {$this->name}: " . implode("\n", $output)
			);
		}
	}
}

// Made with Bob