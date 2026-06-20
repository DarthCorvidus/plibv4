<?php
/**
 * @copyright (c) 2026, Claus-Christoph Küthe
 * @author Claus-Christoph Küthe <floss@vm01.telton.de>
 * @license LGPL
 */

namespace plibv4\CICD;

use plibv4\argv\ArgvGeneric;
use plibv4\uservalue\UserValue;

/**
 * ArgvCICD defines command-line arguments for the CICD test runner
 *
 * Supports:
 * - --no-cleanup: Boolean flag to skip container cleanup
 * - --distro=<name>: Named argument to filter by distribution
 * - --version=<number>: Named argument to filter by version
 */
class ArgvCICD extends ArgvGeneric {
	/**
	 * Constructor - initializes arguments
	 */
	public function __construct() {
		// Add boolean flag
		$this->addBooleanArg('no-cleanup');
		
		// Add optional distro filter
		$distro = UserValue::asOptional();
		$this->addNamedArg('distros', $distro);
		
		// Add optional version filter
		$version = UserValue::asOptional();
		$this->addNamedArg('versions', $version);

		// Add optional project filter
		$version = UserValue::asOptional();
		$this->addNamedArg('projects', $version);

	}
}

// Made with Bob