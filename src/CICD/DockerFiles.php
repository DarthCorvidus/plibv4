<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use OutOfRangeException;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * DockerFiles manages a collection of DockerFile objects
 *
 * Provides methods to add, retrieve, and count DockerFile instances
 */
class DockerFiles {
	/** @var list<DockerFile> */
	private array $dockerfiles = [];
	/** @var array<string, int> Maps DockerFile name to its index in $dockerfiles */
	private array $map = [];

	/**
	 * Create a DockerFiles instance from a directory structure (distribution/version)
	 * @param string $path Base path containing distribution/version directories with Dockerfiles
	 * @return self DockerFiles instance with all found Dockerfiles
	 * @throws RuntimeException If path doesn't exist or is not a directory
	 */
	public static function fromDistributions(string $path): self {
		if (!file_exists($path)) {
			throw new RuntimeException("Path {$path} does not exist");
		}
		if (!is_dir($path)) {
			throw new RuntimeException("Path {$path} is not a directory");
		}

		$dockerfiles = new self();
		$basePath = rtrim($path, '/') . '/';
		$paths = self::getDockerfilePaths($basePath);
		foreach ($paths as $dockerfilePath) {
			$dockerfile = new DockerFile($dockerfilePath);
			$dockerfiles->addDockerFile($dockerfile);
		}
		return $dockerfiles;
	}

	/**
	 * @return list<string> List of paths to Dockerfiles
	 */
	public static function getDockerfilePaths(string $basePath): array {
		$paths = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getFilename() === 'Dockerfile') {
				$dockerfilePath = $file->getPath()."/".$file->getFilename();
				$paths[] = $dockerfilePath;
			}
		}
		return $paths;
	}

	/**
	 * Add a Dockerfile to the collection
	 * @param DockerFile $dockerfile DockerFile instance to add
	 */
	public function addDockerFile(DockerFile $dockerfile): void {
		$this->map[$dockerfile->getName()] = count($this->dockerfiles);
		$this->dockerfiles[] = $dockerfile;
	}

	/**
	 * Get the number of Dockerfiles in the collection
	 * @return int Number of Dockerfiles
	 */
	public function getCount(): int {
		return count($this->dockerfiles);
	}

	/**
	 * Get a specific Dockerfile by index
	 * @param int $index Zero-based index
	 * @return DockerFile DockerFile instance
	 * @throws OutOfRangeException If index is out of range
	 */
	public function getDockerFile(int $index): DockerFile {
		if (!isset($this->dockerfiles[$index])) {
			throw new OutOfRangeException("DockerFile index {$index} is out of range");
		}
		return $this->dockerfiles[$index];
	}

	/**
	 * Get all Dockerfiles
	 * @return list<DockerFile> Array of DockerFile objects
	 */
	public function getDockerFiles(): array {
		return $this->dockerfiles;
	}

	/**
	 * Get the names of all Dockerfiles in the collection
	 * @return list<string> Array of DockerFile names
	 */
	public function getNames(): array {
		$names = [];
		foreach ($this->dockerfiles as $dockerfile) {
			$names[] = $dockerfile->getName();
		}
		return $names;
	}

	/**
	 * Check if a Dockerfile with a certain name exists in the collection
	 * @param string $name Name to check for
	 * @return bool True if a Dockerfile with the given name exists
	 */
	public function hasName(string $name): bool {
		return isset($this->map[$name]);
	}

	/**
	 * Get a specific Dockerfile by name
	 * @param string $name Name to look up
	 * @return DockerFile DockerFile instance
	 * @throws OutOfRangeException If no Dockerfile with the given name exists
	 */
	public function getByName(string $name): DockerFile {
		if (!isset($this->map[$name])) {
			throw new OutOfRangeException("DockerFile with name {$name} does not exist");
		}
		return $this->dockerfiles[$this->map[$name]];
	}

	/**
	 * Get Dockerfiles matching any of the given names
	 * @param list<string> $names Names to filter by
	 * @return self New DockerFiles instance with only matching Dockerfiles
	 */
	public function getByNames(array $names): self {
		$filtered = new self();
		foreach ($names as $name) {
			if ($this->hasName($name)) {
				$filtered->addDockerFile($this->getByName($name));
			}
		}
		return $filtered;
	}
}
