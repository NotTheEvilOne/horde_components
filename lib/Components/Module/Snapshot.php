<?php
/**
 * Components_Module_Snapshot:: generates a development snapshot for the
 * specified package.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Components
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Components
 */

/**
 * Components_Module_DevPackage:: generates a development snapshot for the
 * specified package.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Components
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Components
 */
class Components_Module_Snapshot
extends Components_Module_Base
{
    public function getOptionGroupTitle()
    {
        return 'Package snapshot';
    }

    public function getOptionGroupDescription()
    {
        return 'This module generates a development snapshot for the specified package';
    }

    public function getOptionGroupOptions()
    {
        return array(
            new Horde_Argv_Option(
                '-z',
                '--snapshot',
                array(
                    'action' => 'store_true',
                    'help'   => 'generate a development snapshot'
                )
            ),
            new Horde_Argv_Option(
                '-Z',
                '--archivedir',
                array(
                    'action' => 'store',
                    'help'   => 'the path to the directory where any resulting source archives will be placed.'
                )
            )
        );
    }

    /**
     * Determine if this module should act. Run all required actions if it has
     * been instructed to do so.
     *
     * @param Components_Config $config The configuration.
     *
     * @return boolean True if the module performed some action.
     */
    public function handle(Components_Config $config)
    {
        $options = $config->getOptions();
        if (!empty($options['snapshot'])) {
            $this->requirePackageXml($config->getComponentDirectory());
            $this->_dependencies->getRunnerSnapshot()->run();
            return true;
        }
    }
}
