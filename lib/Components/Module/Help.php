<?php
/**
 * Components_Module_Help:: provides information for a single action.
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
 * Components_Module_Help:: provides information for a single action.
 *
 * Copyright 2011 The Horde Project (http://www.horde.org/)
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
class Components_Module_Help
extends Components_Module_Base
{
    /**
     * Indicate if the module provides an option group.
     *
     * @return boolean True if an option group should be added.
     */
    public function hasOptionGroup()
    {
        return false;
    }

    public function getOptionGroupTitle()
    {
        return '';
    }

    public function getOptionGroupDescription()
    {
        return '';
    }

    public function getOptionGroupOptions()
    {
        return array();
    }

    /**
     * Get the usage description for this module.
     *
     * @return string The description.
     */
    public function getUsage()
    {
        return '  help ACTION - Provide information about the specified ACTION.
';
    }

    /**
     * Return the action arguments supported by this module.
     *
     * @return array A list of supported action arguments.
     */
    public function getActions()
    {
        return array('help');
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
        $arguments = $config->getArguments();
        if (isset($arguments[0]) && $arguments[0] == 'help') {
            $action = $arguments[1];
            $modules = $this->_dependencies->getModules();
            foreach ($modules->getModules() as $module) {
                $element = $modules->getProvider()->getModule($module);
                if (in_array($action, $element->getActions())) {
                    $this->_dependencies->getOutput()->help(
                        $element->getHelp($action)
                    );
                }
            }
            return true;
        }
    }
}