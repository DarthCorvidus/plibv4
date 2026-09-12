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
	private string $dockerfilePath;
	private string $containerName;
	/** @var list<string> */
	private array $volumes = [];
	/** @var array<string, string> */
	private array $annotations = [];
	private string $distribution;
	private int $version;
	private string $imageName;
	/**
	 * Constructor
	 * @param string $dockerfilePath Path to Dockerfile
	 * @throws InvalidArgumentException If no Dockerfile exists in path
	 */
	public function __construct(string $dockerfilePath) {
		$this->dockerfilePath = $dockerfilePath;	
		$dirname = dirname($dockerfilePath);
		$parts = explode("/", $dirname);
		$this->version = array_pop($parts);
		$this->distribution = array_pop($parts);
		$this->containerName = "plibv4-test-".$this->distribution.$this->version;
		$this->addAnnotation("distribution", $this->distribution);
		$this->addAnnotation("version", $this->version);
		$this->build();
	}

	static function extractNameFromDockerpath(string $dockerfilePath): string {
		$dirname = dirname($dockerfilePath);
		$parts = explode("/", $dirname);
		$version = array_pop($parts);
		$distribution = array_pop($parts);
	return $distribution.$version;
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
	 * Get the path
	 * @return string
	 */
	public function getDockerfilePath(): string {
		return $this->dockerfilePath;
	}
	
	/**
	 * Get the container name
	 * @return string
	 */
	public function getName(): string {
		return $this->containerName;
	}
	
	/**
	 * Build the Docker image
	 * @throws RuntimeException If build fails
	 */
	public function build(): void {
		$context = dirname($this->dockerfilePath);
		$this->imageName = $this->distribution.$this->version;
		echo "Building image {$this->containerName}...";
		$buildCmd = "docker build -t {$this->imageName} {$context} 2>&1";
		
		$output = [];
		exec($buildCmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			echo PHP_EOL;
			throw new RuntimeException("Failed to build Docker image {$this->imageName}: " . implode("\n", $output));
		}
		echo "...successful!".PHP_EOL;
	}
	
	/**
	 * Run the Docker container or reuse existing one
	 * @throws RuntimeException If container fails to start
	 */
	public function run(): void {
		// Check if container already exists
		$output = [];
		exec("docker ps -a --filter name=^{$this->containerName}$ --format '{{.Names}}' 2>&1", $output, $exitCode);
		
		if (!empty($output) && trim($output[0]) === $this->containerName) {
			// Container exists, check if it's running
			$output = [];
			exec("docker ps --filter name=^{$this->containerName}$ --format '{{.Names}}' 2>&1", $output, $exitCode);
			
			if (!empty($output) && trim($output[0]) === $this->containerName) {
				// Container is already running
				echo "Reusing running container {$this->containerName}\n";
				return;
			}
			
			// Container exists but is stopped, start it
			echo "Starting existing container {$this->containerName}...\n";
			
			$output = [];
			exec("docker start {$this->containerName} 2>&1", $output, $exitCode);
			
			if ($exitCode !== 0) {
				throw new RuntimeException("Failed to start existing container {$this->containerName}: " . implode("\n", $output));
			}
			
			// Wait a moment for container to be ready
			sleep(1);
			return;
		}
		
		// Container doesn't exist, create it
		echo "Creating new container {$this->containerName}...\n";
		
		// Build volume mounts
		$volumeArgs = '';
		foreach ($this->volumes as $volume) {
			$volumeArgs .= " -v {$volume}";
		}
		
		$cmd = "docker run -d --name {$this->containerName}{$volumeArgs} {$this->imageName} 2>&1";
		
		$output = [];
		exec($cmd, $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException("Failed to start container {$this->containerName}: " . implode("\n", $output));
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
		$source = str_replace('{container}', $this->containerName, $source);
		$destination = str_replace('{container}', $this->containerName, $destination);
		
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
	 * @return void
	 */
	public function exec(string $command): void {
		$escapedCommand = escapeshellarg($command);
		$cmd = "docker exec {$this->containerName} bash -c {$escapedCommand} 2>&1";
		
		$output = [];
		$ph = popen($cmd, "r");
		while($line = fgets($ph)) {
			$output[] = $line;
		}
		$exitCode = pclose($ph);
		$outputStr = implode("\n", $output);
		if($exitCode !== 0)	{
			$e = new ExecException($cmd, $exitCode);
			$e->setOutput($outputStr);
			throw $e;
		}
	}
	
	/**
	 * Stop the container
	 * @throws RuntimeException If container fails to stop
	 */
	public function stop(): void {
		echo "Stopping container {$this->containerName}...\n";
		
		$output = [];
		exec("docker stop {$this->containerName} 2>&1", $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to stop container {$this->containerName}: " . implode("\n", $output)
			);
		}
	}
	
	/**
	 * Delete the container
	 * @throws RuntimeException If container fails to be removed
	 */
	public function delete(): void {
		echo "Deleting container {$this->containerName}...\n";
		
		$output = [];
		exec("docker rm {$this->containerName} 2>&1", $output, $exitCode);
		
		if ($exitCode !== 0) {
			throw new RuntimeException(
				"Failed to delete container {$this->containerName}: " . implode("\n", $output)
			);
		}
	}
}

// Made with Bob