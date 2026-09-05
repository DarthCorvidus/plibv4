<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use plibv4\Projects;
use plibv4\Project;
use plibv4\argv\Argv;

/**
 * Main CICD checker class
 *
 * Enumerates all plibv-* projects and checks if they are complete
 * (have composer.json, phpunit.xml, and psalm.xml)
 */
class Main {
	private Projects $projects;
	private ?Containers $containers = null;
	private ?TestRunner $testRunner = null;
	private int $completeCount = 0;
	private int $incompleteCount = 0;
	private bool $runTests = false;
	private Argv $argv;
	private bool $noCleanup = false;
	
	/**
	 * Constructor
	 * @param string $basePath Base path to scan for projects
	 * @param array<int, string> $argv Command-line arguments
	 */
	public function __construct(string $basePath, array $argv) {
		$this->projects = Projects::fromDirectories($basePath);
		
		// Parse command-line arguments
		$model = new ArgvCICD();
		$this->argv = new Argv($argv, $model);
		
		// Check for no-cleanup flag
		$this->noCleanup = $this->argv->getBoolean('no-cleanup');
		if($this->argv->getBoolean("status")) {
			$this->printStatus();
			exit(0);
		}
	}
	
	/**
	 * Enable test execution
	 * @param string $dockerfilesPath Path to dockerfiles directory
	 * @param string $imagePrefix Image prefix for containers (default: 'plibv4-test')
	 */
	public function enableTests(string $dockerfilesPath, string $imagePrefix = 'plibv4-test'): void {
		$this->runTests = true;
		$this->containers = Containers::fromDistributions($dockerfilesPath, $imagePrefix);
		
		// Filter containers based on command-line arguments
		$this->containers = $this->filterContainers($this->containers);
		
		$this->testRunner = new TestRunner();
		$this->testRunner->ensureVolumeExists();
	}
	
	public function printStatus(): void {
		echo "Incomplete Projects:".PHP_EOL;
		$incomplete = $this->projects->getIncompleteProjects();
		for($i=0;$i<$incomplete->getCount();$i++) {
			echo "\t".$incomplete->getProject($i)->getName().PHP_EOL;
		}

		echo "Complete Projects:".PHP_EOL;
		$incomplete = $this->projects->getCompleteProjects();
		for($i=0;$i<$incomplete->getCount();$i++) {
			echo "\t".$incomplete->getProject($i)->getName().PHP_EOL;
		}

	}

	/**
	 * Filter containers based on command-line arguments
	 * @param Containers $containers
	 * @return Containers Filtered containers
	 */
	private function filterContainers(Containers $containers): Containers {
		// Handle --distro argument (can be comma-separated)
		if ($this->argv->hasValue('distros')) {
			$distros = array_map('trim', explode(',', $this->argv->getValue('distros')));
			$containers = $containers->getByAnnotation('distribution', $distros);
		}
		
		// Handle --version argument
		if ($this->argv->hasValue('versions')) {
			$versions = array_map('trim', explode(',', $this->argv->getValue('versions')));
			$containers = $containers->getByAnnotation('version', $versions);
		}
		
		return $containers;
	}
	
	/**
	 * Run the CICD check
	 * @return int Exit code (0 for success, 1 if incomplete projects found)
	 */
	public function run(): void {
		if ($this->runTests) {
			$this->runTests();
			$this->printSummary();
		return;
		}
		$this->checkProjects();
	}
	
	private function filterProjects(): Projects {
		if(!$this->argv->hasValue("projects")) {
			return $this->projects;
		}
		$projects = array_map('trim', explode(',', $this->argv->getValue('projects')));
		foreach ($projects as $projectName) {
			if (!$this->projects->hasProject($projectName)) {
				echo "Error: project '{$projectName}' not found\n";
				exit(1);
			}
		}
	return $this->projects->getByNames($projects);
	}

	/**
	 * Run tests on all projects
	 */
	private function runTests(): void {
		if ($this->containers === null || $this->testRunner === null) {
			return;
		}
		$projects = $this->filterProjects();
		$testable = $projects->getCompleteProjects();

		echo "Running tests on " . $testable->getCount() . " project(s) " .
		     "across " . $this->containers->getCount() . " environment(s)...\n\n";
		
		try {
			for($i = 0; $i<$testable->getCount();$i++) {
				$project = $testable->getProject($i);
				$this->testRunner->runTests($project, $this->containers);
			}
		} finally {
			// Cleanup: stop and delete all containers (unless --no-cleanup is set)
			if (!$this->noCleanup) {
				echo "\nCleaning up containers...\n";
				$this->containers->stopAll();
				$this->containers->deleteAll();
				echo "Cleanup complete.\n";
			} else {
				echo "\nSkipping cleanup (--no-cleanup flag set)\n";
			}
		}
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
		if ($this->runTests && $this->testRunner !== null) {
			$this->testRunner->printSummary();
		} else {
			echo "\n" . str_repeat("=", 70) . "\n";
			echo "Summary:\n";
			echo "  Complete projects:   {$this->completeCount}\n";
			echo "  Incomplete projects: {$this->incompleteCount}\n";
			echo "  Total projects:      " . $this->projects->getCount() . "\n";
			echo str_repeat("=", 70) . "\n";
		}
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
	
	/**
	 * Get the TestRunner instance
	 * @return TestRunner|null
	 */
	public function getTestRunner(): ?TestRunner {
		return $this->testRunner;
	}
}

