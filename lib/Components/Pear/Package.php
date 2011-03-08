<?php
/**
 * Components_Pear_Package:: provides package handling mechanisms.
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
 * Components_Pear_Package:: provides package handling mechanisms.
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
class Components_Pear_Package
{
    /**
     * The output handler.
     *
     * @param Component_Output
     */
    private $_output;

    /**
     * The PEAR environment for the package.
     *
     * @param Components_Pear_InstallLocation
     */
    private $_environment;

    /**
     * The factory for PEAR class instances.
     *
     * @param Components_Pear_Factory
     */
    private $_factory;

    /**
     * The path to the package XML file.
     *
     * @param string
     */
    private $_package_xml_path;

    /**
     * The path to the package *.tgz file.
     *
     * @param string
     */
    private $_package_tgz_path;

    /**
     * The package representation.
     *
     * @param PEAR_PackageFile_v2
     */
    private $_package_file;

    /**
     * The writeable package representation.
     *
     * @param PEAR_PackageFileManager2
     */
    private $_package_rw_file;

    /**
     * Constructor.
     *
     * @param Component_Output $output The output handler.
     */
    public function __construct(Components_Output $output)
    {
        $this->_output = $output;
    }

    /**
     * Define the surrounding PEAR environment for the package.
     *
     * @param Components_Pear_InstallLocation
     *
     * @return NULL
     */
    public function setEnvironment(Components_Pear_InstallLocation $environment)
    {
        $this->_environment = $environment;
    }

    /**
     * Define the factory that creates our PEAR dependencies.
     *
     * @param Components_Pear_Factory
     *
     * @return NULL
     */
    public function setFactory(Components_Pear_Factory $factory)
    {
        $this->_factory = $factory;
    }

    /**
     * Return the PEAR environment for this package.
     *
     * @return Components_Pear_InstallLocation
     */
    public function getEnvironment()
    {
        if ($this->_environment === null) {
            throw new Component_Exception('You need to set the environment first!');
        }
        return $this->_environment;
    }

    /**
     * Define the package to work on.
     *
     * @param string $package_xml_path Path to the package.xml file.
     *
     * @return NULL
     */
    public function setPackageXml($package_xml_path)
    {
        $this->_package_xml_path = $package_xml_path;
    }

    /**
     * Return the path to the package.xml.
     *
     * @return string
     */
    public function getPackageXml()
    {
        if ($this->_package_xml_path === null) {
            throw new Component_Exception('You need to set the package.xml path first!');
        }
        return $this->_package_xml_path;
    }

    /**
     * Define the package to work on.
     *
     * @param string $package_tgz_path Path to the *.tgz file.
     *
     * @return NULL
     */
    public function setPackageTgz($package_tgz_path)
    {
        $this->_package_tgz_path = $package_tgz_path;
    }

    /**
     * Return the package.xml handler.
     *
     * @return Horde_Pear_Package_Xml
     */
    private function _getPackageXml()
    {
        return $this->_factory->getPackageXml(
            $this->_package_xml_path
        );
    }

    /**
     * Return the PEAR Package representation.
     *
     * @return PEAR_PackageFile
     */
    private function _getPackageFile()
    {
        $this->_checkSetup();
        if ($this->_package_file === null) {
            if (!empty($this->_package_xml_path)) {
                $this->_package_file = $this->_factory->getPackageFile(
                    $this->_package_xml_path,
                    $this->getEnvironment()
                );
            } else {
                $this->_package_file = $this->_factory->getPackageFileFromTgz(
                    $this->_package_tgz_path,
                    $this->getEnvironment()
                );
            }
        }
        return $this->_package_file;
    }

    /**
     * Return a writeable PEAR Package representation.
     *
     * @return PEAR_PackageFileManager2
     */
    private function _getPackageRwFile()
    {
        $this->_checkSetup();
        if ($this->_package_rw_file === null) {
            $this->_package_rw_file = $this->_factory->getPackageRwFile(
                $this->_package_xml_path,
                $this->getEnvironment()
            );
        }
        return $this->_package_rw_file;
    }

    /**
     * Validate that the required parameters for providing the package definition are set.
     *
     * @return NULL
     *
     * @throws Components_Exception In case some settings are missing.
     */
    private function _checkSetup()
    {
        if ($this->_environment === null
            || ($this->_package_xml_path === null
                && $this->_package_tgz_path === null)
            || $this->_factory === null) {
            throw new Components_Exception('You need to set the factory, the environment and the path to the package file first!');
        }
    }

    /**
     * Return the name for this package.
     *
     * @return string The package name.
     */
    public function getName()
    {
        return $this->_getPackageFile()->getName();
    }

    /**
     * Return the channel for the package.
     *
     * @return string The package channel.
     */
    public function getChannel()
    {
        return $this->_getPackageFile()->getChannel();
    }

    /**
     * Return the description for this package.
     *
     * @return string The package description.
     */
    public function getDescription()
    {
        return $this->_getPackageFile()->getDescription();
    }

    /**
     * Return the version for this package.
     *
     * @return string The package version.
     */
    public function getVersion()
    {
        return $this->_getPackageFile()->getVersion();
    }

    /**
     * Return the license for this package.
     *
     * @return string The package license.
     */
    public function getLicense()
    {
        return $this->_getPackageFile()->getLicense();
    }

    /**
     * Return the license location for this package.
     *
     * @return string The package license location.
     */
    public function getLicenseLocation()
    {
        return $this->_getPackageFile()->getLicenseLocation();
    }

    /**
     * Return the summary for this package.
     *
     * @return string The package summary.
     */
    public function getSummary()
    {
        return $this->_getPackageFile()->getSummary();
    }

    /**
     * Return the leads for this package.
     *
     * @return string The package leads.
     */
    public function getLeads()
    {
        return $this->_getPackageFile()->getLeads();
    }

    /**
     * Return the maintainers for this package.
     *
     * @return string The package maintainers.
     */
    public function getMaintainers()
    {
        return $this->_getPackageFile()->getMaintainers();
    }

    /**
     * Return the list of files that should be installed for this package.
     *
     * @return array The file list.
     */
    public function getInstallationFilelist()
    {
        return $this->_getPackageFile()->getInstallationFilelist();
    }

    /**
     * Return an updated package description.
     *
     * @return PEAR_PackageFileManager2 The updated package.
     */
    private function _getUpdatedPackageFile()
    {
        $package = $this->_getPackageRwFile();
        $contents = $this->_factory->createContents($package);
        $contents->update();
        return $package;
    }

    /**
     * Return the updated package.xml file.
     *
     * @return NULL
     */
    public function _printUpdatedPackageFile()
    {
        ob_start();
        $this->_getUpdatedPackageFile()->debugPackageFile();
        $new = ob_get_contents();
        ob_end_clean();
        return $new;
    }    

    /**
     * Update the package.xml file.
     *
     * @param string $action The action to perform. Either "update", "diff", or "print".
     *
     * @return NULL
     */
    public function updatePackageFile($action = 'update')
    {
        switch($action) {
        case 'print':
            print $this->_printUpdatedPackageFile();
            break;
        case 'diff':
            if (!class_exists('Text_Diff')) {
                throw new Components_Exception('The "Text_Diff" package is missing!');
            }
            $new = $this->_printUpdatedPackageFile();
            $old = file_get_contents($this->_package_xml_path);
            $renderer = new Text_Diff_Renderer_unified();
            print $renderer->render(
                new Text_Diff(
                    'auto', array(explode("\n", $old), explode("\n", $new))
                )
            );
            break;
        default:
            $this->_getUpdatedPackageFile()->writePackageFile();
            $this->_output->ok('Successfully updated ' . $this->_package_xml_path);
            break;
        }
    }    

    /**
     * Return the dependencies for the package.
     *
     * @return array The list of dependencies.
     */
    public function getDependencies()
    {
        return $this->_getPackageFile()->getDeps();
    }

    /**
     * Return the dependency helper for the package.
     *
     * @return Components_Pear_Dependencies The dependency helper.
     */
    public function getDependencyHelper()
    {
        $this->_checkSetup();
        return $this->_factory->createDependencies($this);
    }

    /**
     * Generate a snapshot of the package using the provided version number.
     *
     * @param string $version     The snapshot version.
     * @param string $archive_dir The path where the snapshot should be placed.
     *
     * @return string The path to the snapshot.
     */
    public function generateSnapshot($version, $archive_dir)
    {
        $pkg = $this->_getPackageFile();
        $pkg->_packageInfo['version']['release'] = $version;
        $pkg->setDate(date('Y-m-d'));
        $pkg->setTime(date('H:i:s'));
        $pkg->setLogger($this->_output);
        $errors = array();
        ob_start();
        $old_dir = getcwd();
        chdir($archive_dir);
        try {
            $result = Components_Exception_Pear::catchError(
                $pkg->getDefaultGenerator()->toTgz(new PEAR_Common())
            );
        } catch (Components_Exception_Pear $e) {
            $errors[] = $e->getMessage();
            $errors[] = '';
            $result = false;
            foreach ($pkg->getValidationWarnings() as $error) {
                $errors[] = isset($error['message']) ? $error['message'] : 'Unknown Error';
            }
        }
        chdir($old_dir);
        $this->_output->pear(ob_get_clean());
        if ($result) {
            $this->_output->ok('Generated snapshot ' . $result);
        } else {
            $this->_output->fail(
                'Generating snapshot failed with:'. "\n\n" . join("\n", $errors)
            );
        }
        return $result;
    }

    /**
     * Generate a package package release.
     *
     * @param boolean $manual Avoid touching the package.xml.
     *
     * @return string The path to the release package.
     */
    public function generateRelease($manual = false)
    {
        if (!$manual) {
            $pkg = $this->updatePackageFile();
        }

        $pkg = $this->_getPackageFile();
        $pkg->setLogger($this->_output);
        $errors = array();
        ob_start();
        try {
            $result = Components_Exception_Pear::catchError(
                $pkg->getDefaultGenerator()->toTgz(new PEAR_Common())
            );
        } catch (Components_Exception_Pear $e) {
            $errors[] = $e->getMessage();
            $errors[] = '';
            $result = false;
            foreach ($pkg->getValidationWarnings() as $error) {
                $errors[] = isset($error['message']) ? $error['message'] : 'Unknown Error';
            }
        }
        $this->_output->pear(ob_get_clean());
        if ($result) {
            $this->_output->ok('Generated release package ' . $result);
        } else {
            $this->_output->fail(
                'Generating release package failed with:'. "\n\n" . join("\n", $errors)
            );
        }
        return $result;
    }

}
