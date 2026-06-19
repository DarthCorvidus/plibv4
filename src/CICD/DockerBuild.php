<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use InvalidArgumentException;

/**
 * DockerBuild represents a Dockerfile path and parses distribution and version
 *
 * Expects paths in the format: .../distribution/version
 * Example: cicd/dockerfiles/centos/9 -> distribution: centos, version: 9
 */
class DockerBuild {
	private string $path;
	private string $distribution;
	private string $version;
	
	/**
	 * Constructor
	 * @param string $path Path to Dockerfile directory (e.g., "cicd/dockerfiles/centos/9")
	 * @throws InvalidArgumentException If path format is invalid
	 */
	public function __construct(string $path) {
		$this->path = rtrim($path, '/');
		$this->parse();
	}
	
	/**
	 * Parse the path to extract distribution and version
	 * @throws InvalidArgumentException If path cannot be parsed
	 */
	private function parse(): void {
		$parts = explode('/', $this->path);
		
		if (count($parts) < 2) {
			throw new InvalidArgumentException(
				"Invalid path format: '{$this->path}'. Expected format: .../distribution/version"
			);
		}
		
		// Get the last two parts: distribution and version
		$this->version = array_pop($parts);
		$this->distribution = array_pop($parts);
		
		// Validate that version and distribution are not empty
		if (empty($this->distribution) || empty($this->version)) {
			throw new InvalidArgumentException(
				"Invalid path format: '{$this->path}'. Distribution or version is empty."
			);
		}
	}
	
	/**
	 * Get the full path
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}
	
	/**
	 * Get the distribution name
	 * @return string Distribution name (e.g., "centos", "debian", "fedora")
	 */
	public function getDistribution(): string {
		return $this->distribution;
	}
	
	/**
	 * Get the version
	 * @return string Version (e.g., "9", "12", "43")
	 */
	public function getVersion(): string {
		return $this->version;
	}
	
	/**
	 * Get distribution and version as a combined string
	 * @param string $separator Separator between distribution and version (default: "-")
	 * @return string Combined string (e.g., "centos-9")
	 */
	public function getDistributionVersion(string $separator = '-'): string {
		return $this->distribution . $separator . $this->version;
	}
	
	/**
	 * Get the Dockerfile path (assumes Dockerfile is in the directory)
	 * @return string Full path to Dockerfile
	 */
	public function getDockerfilePath(): string {
		return $this->path . '/Dockerfile';
	}
	
	/**
	 * Check if the Dockerfile exists
	 * @return bool True if Dockerfile exists
	 */
	public function exists(): bool {
		return file_exists($this->getDockerfilePath());
	}
	
	/**
	 * String representation
	 * @return string
	 */
	public function __toString(): string {
		return $this->getDistributionVersion();
	}
}

# Made with Bob
