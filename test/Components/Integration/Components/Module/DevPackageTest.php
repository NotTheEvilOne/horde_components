<?php
/**
 * Test the DevPackage module.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Components
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Components
 */

/**
 * Prepare the test setup.
 */
require_once dirname(__FILE__) . '/../../../Autoload.php';

/**
 * Test the DevPackage module.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category   Horde
 * @package    Components
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @license    http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link       http://pear.horde.org/index.php?package=Components
 */
class Components_Integration_Components_Module_DevPackageTest
extends Components_StoryTestCase
{
    /**
     * @scenario
     */
    public function testOption()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the help option')
            ->then('the help will contain the option', '-d,\s*--devpackage');
    }

    /**
     * @scenario
     */
    public function testSnapshotGeneration()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the devpackage option, the archive directory option and a path to a Horde framework component')
            ->then('a package snapshot will be generated at the indicated archive directory');
    }

    /**
     * @scenario
     */
    public function testErrorHandling()
    {
        $this->given('the default Components setup')
            ->when('calling the package with the devpackage option, the archive directory option and a path to an invalid Horde framework component')
            ->then('the output should indicate an invalid package.xml');
    }
}