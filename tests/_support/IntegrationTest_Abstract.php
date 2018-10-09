<?php

namespace Pods\GF\Tests;

use Codeception\TestCase\WPTestCase;
use Pods\GF\Tests\Test_Helper;

abstract class IntegrationTest_Abstract extends WPTestCase {

	protected $backupGlobals = false;

	/**
	 * Code to run before tests.
	 */
	public function setUp() {

		Test_Helper::before_test();

	}

	/**
	 * Code to run after tests.
	 */
	public function tearDown() {

		Test_Helper::after_test();

	}

}
