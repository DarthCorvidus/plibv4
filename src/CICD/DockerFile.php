<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

/**
 * DockerFile represents a Dockerfile with its path, distribution, and version
 */
class DockerFile {
	private string $dockerfilePath;
	private string $distribution;
	private int $version;
	private string $name;

	/**
	 * Constructor
	 * @param string $dockerfilePath Path to Dockerfile
	 */
	public function __construct(string $dockerfilePath) {
		$this->dockerfilePath = $dockerfilePath;
		$dirname = dirname($dockerfilePath);
		$parts = explode("/", $dirname);
		$this->version = array_pop($parts);
		$this->distribution = array_pop($parts);
		$this->name = $this->distribution.$this->version;
	}

	/**
	 * Get the path
	 * @return string
	 */
	public function getDockerfilePath(): string {
		return $this->dockerfilePath;
	}

	/**
	 * Get the distribution
	 * @return string
	 */
	public function getDistribution(): string {
		return $this->distribution;
	}

	/**
	 * Get the version
	 * @return int
	 */
	public function getVersion(): int {
		return $this->version;
	}

	/**
	 * Get the name, distribution and version conjoined, e.g. "debian12"
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}
}
