#!/usr/bin/env php
<?php
class ComposerFile {
	private array $json;
	function __construct(string $composer) {
		$jsonString = file_get_contents($composer);
		$this->json = json_decode($jsonString, true);
	}
	
	public function getArray(): array {
		return $this->json;
	}

	private function getSub(array $search, array $data) {
		$key = array_shift($search);
		if(isset($data[$key]) && $search == array()) {
			return $data[$key];
		}
		if(!isset($data[$key])) {
			return "";
		}
	return $this->getSub($search, $data[$key]);
	}
	
	function getValue(string $search): mixed {
		if($search=="") {
			return $this->json;
		}
		$split = explode(".", $search);
	return $this->getSub($split, $this->json);
	}
}
class Main {
	private array $argv;
	private array $projects;
	private string $search;
	function __construct(array $argv) {
		$this->argv = $argv;
		$this->search = "";
		if(isset($this->argv[1])) {
			$this->search = $this->argv[1];
		}
		foreach(glob("plibv4-*") as $project) {
			if(file_exists($project."/composer.json")) {
				$this->projects[] = new ComposerFile($project."/composer.json");
			}
		}
	}
	
	function run() {
		foreach($this->projects as $value) {
			print_r($value);
			$array = $value->getArray();
			echo $array["name"].":".PHP_EOL;
			$result = $value->getValue($this->search);
			if($result==NULL) {
				continue;
			}
			echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
		}
	}
}

$main = new Main($argv);
$main->run();