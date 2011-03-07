<?php
/**
 * Components_Config:: interface represents a configuration type for the Horde
 * element tool.
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
 * Components_Config:: interface represents a configuration type for the Horde
 * element tool.
 *
 * Copyright 2009-2011 The Horde Project (http://www.horde.org/)
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
interface Components_Config
{
    /**
     * Set an additional option value.
     *
     * @param string $key   The option to set.
     * @param string $value The value of the option.
     *
     * @return NULL
     */
    public function setOption($key, $value);

    /**
     * Return the options provided by the configuration handlers.
     *
     * @return array An array of options.
     */
    public function getOptions();

    /**
     * Unshift an element to the argument list.
     *
     * @param string $element The element to unshift.
     *
     * @return NULL
     */
    public function unshiftArgument($element);

    /**
     * Return the arguments provided by the configuration handlers.
     *
     * @return array An array of arguments.
     */
    public function getArguments();

    /**
     * Return the first argument - the package directory - provided by the
     * configuration handlers.
     *
     * @return string The package directory.
     */
    public function getPackageDirectory();
}