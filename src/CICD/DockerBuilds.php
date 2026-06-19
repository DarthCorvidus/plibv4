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
 * DockerBuilds enumerates all Dockerfile directories in a base path
 *
 * Scans for directories containing Dockerfiles and creates DockerBuild instances
 * for each one found.
 */
class DockerBuilds {
	private string $basePath;
	/** @var list<string> */
	private array $dockerfilePaths = [];
	/** @var list<DockerBuild> */
	private array $dockerfiles = [];
	
	/**
	 * Constructor
	 * @param string $basePath Base path to scan for Dockerfiles (e.g., "cicd/dockerfiles")
	 * @throws RuntimeException If base path doesn't exist or is not a directory
	 */
	public function __construct(string $basePath) {
		if (!file_exists($basePath)) {
			throw new RuntimeException("base path {$basePath} does not exist");
		}
		if (!is_dir($basePath)) {
			throw new RuntimeException("base path {$basePath} is not a directory");
		}
		$this->basePath = rtrim($basePath, '/') . '/';
		$this->scanDockerfiles();
	}
	
	/**
	 * Scan the base path for directories containing Dockerfiles
	 */
	private function scanDockerfiles(): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getFilename() === 'Dockerfile') {
				$dockerfilePath = $file->getPath();
				// Store the relative path from basePath
				$relativePath = substr($dockerfilePath, strlen($this->basePath));
				
				try {
					$dockerfile = new DockerBuild($dockerfilePath);
					$this->dockerfilePaths[] = $relativePath;
					$this->dockerfiles[] = $dockerfile;
				} catch (\InvalidArgumentException $e) {
					// Skip invalid paths
					continue;
				}
			}
		}
		
		// Sort by path
		array_multisort($this->dockerfilePaths, $this->dockerfiles);
	}
	
	/**
	 * Get all Dockerfile paths (relative to base path)
	 * @return list<string> Array of relative paths
	 */
	public function getDockerfilePaths(): array {
		return $this->dockerfilePaths;
	}
	
	/**
	 * Get all DockerBuild instances
	 * @return list<DockerBuild> Array of DockerBuild objects
	 */
	public function getDockerfiles(): array {
		return $this->dockerfiles;
	}
	
	/**
	 * Get a specific DockerBuild by index
	 * @param int $i Zero-based index
	 * @return DockerBuild DockerBuild instance
	 * @throws OutOfRangeException If index is out of range
	 */
	public function getDockerfile(int $i): DockerBuild {
		if (!isset($this->dockerfiles[$i])) {
			throw new OutOfRangeException("Dockerfile index {$i} is out of range");
		}
		return $this->dockerfiles[$i];
	}
	
	/**
	 * Get the number of Dockerfiles found
	 * @return int Number of Dockerfiles
	 */
	public function getCount(): int {
		return count($this->dockerfiles);
	}
	
	/**
	 * Check if a specific Dockerfile exists by distribution and version
	 * @param string $distribution Distribution name (e.g., "centos")
	 * @param string $version Version (e.g., "9")
	 * @return bool True if Dockerfile exists
	 */
	public function hasDockerfile(string $distribution, string $version): bool {
		foreach ($this->dockerfiles as $dockerfile) {
			if ($dockerfile->getDistribution() === $distribution && 
				$dockerfile->getVersion() === $version) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Get a DockerBuild by distribution and version
	 * @param string $distribution Distribution name (e.g., "centos")
	 * @param string $version Version (e.g., "9")
	 * @return DockerBuild DockerBuild instance
	 * @throws OutOfRangeException If Dockerfile doesn't exist
	 */
	public function getByDistributionVersion(string $distribution, string $version): DockerBuild {
		foreach ($this->dockerfiles as $dockerfile) {
			if ($dockerfile->getDistribution() === $distribution && 
				$dockerfile->getVersion() === $version) {
				return $dockerfile;
			}
		}
		throw new OutOfRangeException("Dockerfile for {$distribution}/{$version} not found");
	}
	
	/**
	 * Get all unique distributions
	 * @return list<string> Array of distribution names
	 */
	public function getDistributions(): array {
		$distributions = [];
		foreach ($this->dockerfiles as $dockerfile) {
			$dist = $dockerfile->getDistribution();
			if (!in_array($dist, $distributions, true)) {
				$distributions[] = $dist;
			}
		}
		sort($distributions);
		return $distributions;
	}
	
	/**
	 * Get all Dockerfiles for a specific distribution
	 * @param string $distribution Distribution name
	 * @return list<DockerBuild> Array of DockerBuild objects
	 */
	public function getByDistribution(string $distribution): array {
		$result = [];
		foreach ($this->dockerfiles as $dockerfile) {
			if ($dockerfile->getDistribution() === $distribution) {
				$result[] = $dockerfile;
			}
		}
		return $result;
	}
	
	/**
	 * Get the base path
	 * @return string Base path
	 */
	public function getBasePath(): string {
		return $this->basePath;
	}
}

// Made with Bob
