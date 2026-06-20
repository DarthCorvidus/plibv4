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
use InvalidArgumentException;

/**
 * Containers manages a collection of Container objects
 *
 * Provides methods to add, retrieve, and count Container instances
 */
class Containers {
	/** @var list<Container> */
	private array $containers = [];
	
	/**
	 * Create a Containers instance from a directory structure (distribution/version)
	 * @param string $path Base path containing distribution/version directories with Dockerfiles
	 * @param string $imagePrefix Prefix for container names (default: 'container')
	 * @return self Containers instance with all found containers
	 * @throws RuntimeException If path doesn't exist or is not a directory
	 */
	public static function fromDistributions(string $path, string $imagePrefix = 'container'): self {
		if (!file_exists($path)) {
			throw new RuntimeException("Path {$path} does not exist");
		}
		if (!is_dir($path)) {
			throw new RuntimeException("Path {$path} is not a directory");
		}
		
		$containers = new self();
		$basePath = rtrim($path, '/') . '/';
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getFilename() === 'Dockerfile') {
				$dockerfilePath = $file->getPath();
				
				// Extract distribution and version from path
				$relativePath = substr($dockerfilePath, strlen($basePath));
				$parts = explode('/', $relativePath);
				
				if (count($parts) >= 2) {
					$version = array_pop($parts);
					$distribution = array_pop($parts);
					
					$containerName = $imagePrefix . '-' . $distribution . '-' . $version;
					$tag = $distribution . '-' . $version;
					
					try {
						$container = new Container($dockerfilePath, $containerName, $tag);
						$container->addAnnotation('distribution', $distribution);
						$container->addAnnotation('version', $version);
						$containers->addContainer($container);
					} catch (InvalidArgumentException $e) {
						// Skip invalid containers
						continue;
					}
				}
			}
		}
		
		return $containers;
	}
	
	/**
	 * Add a container to the collection
	 * @param Container $container Container instance to add
	 */
	public function addContainer(Container $container): void {
		$this->containers[] = $container;
	}
	
	/**
	 * Get the number of containers in the collection
	 * @return int Number of containers
	 */
	public function getCount(): int {
		return count($this->containers);
	}
	
	/**
	 * Get a specific container by index
	 * @param int $index Zero-based index
	 * @return Container Container instance
	 * @throws OutOfRangeException If index is out of range
	 */
	public function getContainer(int $index): Container {
		if (!isset($this->containers[$index])) {
			throw new OutOfRangeException("Container index {$index} is out of range");
		}
		return $this->containers[$index];
	}
	
	/**
	 * Get all containers
	 * @return list<Container> Array of Container objects
	 */
	public function getContainers(): array {
		return $this->containers;
	}
	
	/**
	 * Stop all containers in the collection
	 * Continues even if individual containers fail to stop
	 */
	public function stopAll(): void {
		foreach ($this->containers as $container) {
			try {
				$container->stop();
			} catch (RuntimeException $e) {
				echo "Warning: " . $e->getMessage() . "\n";
				continue;
			}
		}
	}
	
	/**
	 * Delete all containers in the collection
	 * Continues even if individual containers fail to be deleted
	 */
	public function deleteAll(): void {
		foreach ($this->containers as $container) {
			try {
				$container->delete();
			} catch (RuntimeException $e) {
				echo "Warning: " . $e->getMessage() . "\n";
				continue;
			}
		}
	}
}

// Made with Bob