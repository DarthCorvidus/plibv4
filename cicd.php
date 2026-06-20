#!/usr/bin/env php
<?php
/**
 * CICD Project Checker
 *
 * Enumerates all plibv-* projects and checks if they are complete
 * (have composer.json, phpunit.xml, and psalm.xml)
 */

require_once __DIR__ . '/vendor/autoload.php';

use plibv4\CICD\Main;

// Create and run CICD checker
$main = new Main(__DIR__);
$main->enableTests('cicd/dockerfiles', 'plibv4-test');
$exitCode = $main->run();

exit($exitCode);
