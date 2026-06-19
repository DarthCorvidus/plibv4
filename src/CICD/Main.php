<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use plibv4\Projects;
use plibv4\Project;

/**
 * Main CICD checker class
 * 
 * Enumerates all plibv-* projects and checks if they are complete
 * (have composer.json, phpunit.xml, and psalm.xml)
 */
class Main {
	private Projects $projects;
	private int $completeCount = 0;
	private int $incompleteCount = 0;
	
	/**
	 * Constructor
	 * @param string $basePath Base path to scan for projects
	 */
	public function __construct(string $basePath) {
		$this->projects = new Projects($basePath);
	}
	
	/**
	 * Run the CICD check
	 * @return int Exit code (0 for success, 1 if incomplete projects found)
	 */
	public function run(): int {
		$this->printHeader();
		$this->checkProjects();
		$this->printSummary();
		return $this->incompleteCount > 0 ? 1 : 0;
	}
	
	/**
	 * Print the header
	 */
	private function printHeader(): void {
		echo "Checking plibv4-* projects for completeness...\n";
		echo str_repeat("=", 70) . "\n\n";
	}
	
	/**
	 * Check all projects and display results
	 */
	private function checkProjects(): void {
		foreach ($this->projects->getProjects() as $i => $projectName) {
			$project = $this->projects->getProject($i);
			
			if ($project->isComplete()) {
				$this->completeCount++;
				$this->printCompleteProject($projectName);
			} else {
				$this->incompleteCount++;
				$this->printIncompleteProject($projectName, $project);
			}
		}
	}
	
	/**
	 * Print a complete project
	 * @param string $projectName
	 */
	private function printCompleteProject(string $projectName): void {
		echo "✓ {$projectName}\n";
	}
	
	/**
	 * Print an incomplete project with missing files
	 * @param string $projectName
	 * @param Project $project
	 */
	private function printIncompleteProject(string $projectName, Project $project): void {
		echo "✗ {$projectName}\n";
		
		$missing = [];
		if (!$project->hasComposer()) {
			$missing[] = "composer.json";
		}
		if (!$project->hasPHPUnit()) {
			$missing[] = "phpunit.xml";
		}
		if (!$project->hasPsalm()) {
			$missing[] = "psalm.xml";
		}
		
		echo "  Missing: " . implode(", ", $missing) . "\n";
	}
	
	/**
	 * Print the summary
	 */
	private function printSummary(): void {
		echo "\n" . str_repeat("=", 70) . "\n";
		echo "Summary:\n";
		echo "  Complete projects:   {$this->completeCount}\n";
		echo "  Incomplete projects: {$this->incompleteCount}\n";
		echo "  Total projects:      " . $this->projects->getCount() . "\n";
	}
	
	/**
	 * Get the number of complete projects
	 * @return int
	 */
	public function getCompleteCount(): int {
		return $this->completeCount;
	}
	
	/**
	 * Get the number of incomplete projects
	 * @return int
	 */
	public function getIncompleteCount(): int {
		return $this->incompleteCount;
	}
	
	/**
	 * Get the Projects instance
	 * @return Projects
	 */
	public function getProjects(): Projects {
		return $this->projects;
	}
}

// Made with Bob
