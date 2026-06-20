<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4;

use OutOfRangeException;
use RuntimeException;

/**
 * Projects enumerates all folders beginning with plibv- in the NetBeansProjects directory
 */
class Projects {
	private string $basePath;
	/** @var list<string> */
	private array $projectNames = [];
	/** @var list<Project> */
	private array $projects = [];
	
	/**
	 * Constructor
	 * @param string $basePath Base path to scan for plibv-* folders (default: /home/hm/NetBeansProjects/)
	 */
	public function __construct(string $basePath) {
		if(!file_exists($basePath)) {
			throw new RuntimeException("base path {$basePath} does not exist");
		}
		if(!is_dir($basePath)) {
			throw new RuntimeException("base path {$basePath} is not a directory");
		}
		$this->basePath = rtrim($basePath, '/') . '/';
		$this->scanProjects();
	}
	
	/**
	 * Scan the base path for directories beginning with plibv-
	 */
	private function scanProjects(): void {
		$items = scandir($this->basePath);
		if ($items === false) {
			return;
		}
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			
			$fullPath = $this->basePath . $item;
			// Check if it's a directory and starts with "plibv4-"
			if (is_dir($fullPath) && str_starts_with($item, 'plibv4-')) {
				$this->projectNames[] = $item;
				$this->projects[] = new Project($fullPath);
			}
		}
		sort($this->projectNames);
		// Sort projects array by the same order
		array_multisort($this->projectNames, $this->projects);
	}
	
	/**
	 * Get all project folder names
	 * @return list<string> Array of folder names (without path)
	 */
	public function getProjects(): array {
		return $this->projectNames;
	}
	
	/**
	 * Get a specific project by index
	 * @param int $i Zero-based index
	 * @return Project Project instance
	 * @throws OutOfRangeException If index is out of range
	 */
	public function getProject(int $i): Project {
		if (!isset($this->projects[$i])) {
			throw new OutOfRangeException("Project index {$i} is out of range");
		}
		return $this->projects[$i];
	}
	
	/**
	 * Get full paths to all projects
	 * @return list<string> Array of full paths
	 */
	public function getProjectPaths(): array {
		$paths = [];
		foreach ($this->projectNames as $projectName) {
			$paths[] = $this->basePath . $projectName;
		}
		return $paths;
	}
	
	/**
	 * Get the number of projects found
	 * @return int Number of projects
	 */
	public function getCount(): int {
		return count($this->projects);
	}
	
	/**
	 * Check if a specific project exists
	 * @param string $projectName Project folder name
	 * @return bool True if project exists
	 */
	public function hasProject(string $projectName): bool {
		return in_array($projectName, $this->projectNames, true);
	}
	
	/**
	 * Get a project by name
	 * @param string $projectName Project folder name
	 * @return Project Project instance
	 * @throws OutOfRangeException If project doesn't exist
	 */
	public function getByName(string $projectName): Project {
		$index = array_search($projectName, $this->projectNames, true);
		if ($index === false) {
			throw new OutOfRangeException("Project '{$projectName}' not found");
		}
		return $this->projects[$index];
	}
	
	/**
	 * Remove all incomplete projects from the collection
	 *
	 * This method filters out projects that don't have all three required files
	 * (composer.json, phpunit.xml, and psalm.xml), keeping only complete projects.
	 *
	 * @return int Number of projects removed
	 */
	public function prune(): int {
		$originalCount = count($this->projects);
		$newProjectNames = [];
		$newProjects = [];
		
		foreach ($this->projects as $i => $project) {
			if ($project->isComplete()) {
				$newProjectNames[] = $this->projectNames[$i];
				$newProjects[] = $project;
			}
		}
		
		$this->projectNames = $newProjectNames;
		$this->projects = array_slice($newProjects, 0, 1);
		
		return $originalCount - count($this->projects);
	}
}
