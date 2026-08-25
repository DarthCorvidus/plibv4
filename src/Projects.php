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
final class Projects {
	/** @var list<string> */
	private array $projectNames = [];
	/** @var list<Project> */
	private array $projects = [];
	
	public function __construct() {
	}

	/**
	 * Build a Projects instance by scanning a directory for plibv4-* folders
	 * @param string $basePath Base path to scan for plibv-* folders (default: /home/hm/NetBeansProjects/)
	 */
	public static function fromDirectories(string $basePath): self {
		if(!file_exists($basePath)) {
			throw new RuntimeException("base path {$basePath} does not exist");
		}
		if(!is_dir($basePath)) {
			throw new RuntimeException("base path {$basePath} is not a directory");
		}
		$instance = new self();

		$items = scandir($basePath);
		if ($items === false) {
			return $instance;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$fullPath = $basePath."/".$item;
			// Check if it's a directory and starts with "plibv4-"
			if(!is_dir($fullPath)) {
				continue;
			}
			if(!str_starts_with($item, 'plibv4-')) {
				continue;
			}
			$instance->add(new Project($fullPath));
		}
		sort($instance->projectNames);
		// Sort projects array by the same order
		array_multisort($instance->projectNames, $instance->projects);

		return $instance;
	}

	function add(Project $project): void {
		$this->projects[] = $project;
		$this->projectNames[] = $project->getName();
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
		foreach ($this->projects as $project) {
			$paths[] = $project->getPath();
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
	 * Filter projects by name (supports comma-separated list)
	 *
	 * Filters the project collection to only include projects whose names
	 * match any of the provided names. Modifies the collection in place.
	 *
	 * @param list<string> $names Array of project names to keep
	 * @return int Number of projects removed
	 */
	public function filterProjects(array $names): int {
		$originalCount = count($this->projects);
		$newProjectNames = [];
		$newProjects = [];
		
		foreach ($this->projects as $i => $project) {
			if (in_array($this->projectNames[$i], $names, true)) {
				$newProjectNames[] = $this->projectNames[$i];
				$newProjects[] = $project;
			}
		}
		
		$this->projectNames = $newProjectNames;
		$this->projects = $newProjects;
		
		return $originalCount - count($this->projects);
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
		$this->projects = $newProjects;
		
		return $originalCount - count($this->projects);
	}

	function getIncompleteProjects(): Projects {
		$projects = new Projects();
		foreach ($this->projects as $i => $project) {
			if (!$project->isComplete()) {
				$projects->add($project);
			}
		}
	return $projects;
	}

	function getCompleteProjects(): Projects {
		$projects = new Projects();
		foreach ($this->projects as $i => $project) {
			if ($project->isComplete()) {
				$projects->add($project);
			}
		}
	return $projects;
	}

}
