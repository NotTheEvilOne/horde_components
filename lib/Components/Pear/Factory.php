<?php
/**
 * Components_Pear_Factory:: generates PEAR specific handlers.
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
 * Components_Pear_Factory:: generates PEAR specific handlers.
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
class Components_Pear_Factory
{
    /**
     * The dependency broker.
     *
     * @param Component_Dependencies
     */
    private $_dependencies;

    /**
     * Constructor.
     *
     * @param Component_Dependencies $dependencies The dependency broker.
     */
    public function __construct(Components_Dependencies $dependencies)
    {
        $this->_dependencies = $dependencies;
    }

    /**
     * Create a representation for a PEAR environment.
     *
     * @param string $config_file The path to the configuration file.
     *
     * @return Components_Pear_InstallLocation The PEAR environment
     */
    public function createInstallLocation($config_file)
    {
        $install_location = $this->_dependencies->createInstance('Components_Pear_InstallLocation');
        $install_location->setFactory($this);
        $install_location->setLocation(
            dirname($config_file),
            basename($config_file)
        );
        return $install_location;
    }

    /**
     * Create a package representation for a specific PEAR environment.
     *
     * @param string                          $package_file The path of the package XML file.
     * @param Components_Pear_InstallLocation $environment  The PEAR environment.
     *
     * @return Components_Pear_Package The PEAR package.
     */
    public function createPackageForEnvironment(
        $package_file,
        Components_Pear_InstallLocation $environment
    ) {
        $package = $this->_createPackage($environment);
        $package->setPackageXml($package_file);
        return $package;
    }

    /**
     * Create a package representation for a specific PEAR environment.
     *
     * @param string $package_file The path of the package XML file.
     * @param string $config_file  The path to the configuration file.
     *
     * @return Components_Pear_Package The PEAR package.
     */
    public function createPackageForInstallLocation($package_file, $config_file)
    {
        return $this->createPackageForEnvironment(
            $package_file, $this->createInstallLocation($config_file)
        );
    }

    /**
     * Create a package representation for the default PEAR environment.
     *
     * @param string $package_file The path of the package XML file.
     *
     * @return Components_Pear_Package The PEAR package.
     */
    public function createPackageForDefaultLocation($package_file)
    {
        return $this->createPackageForEnvironment(
            $package_file, $this->_dependencies->getInstance('Components_Pear_InstallLocation')
        );
    }

    /**
     * Create a package representation for a specific PEAR environment based on a *.tgz archive.
     *
     * @param string                          $package_file The path of the package *.tgz file.
     * @param Components_Pear_InstallLocation $environment  The environment for the package file.
     *
     * @return Components_Pear_Package The PEAR package.
     */
    public function createTgzPackageForInstallLocation(
        $package_file,
        Components_Pear_InstallLocation $environment
    ) {
        $package = $this->_createPackage($environment);
        $package->setPackageTgz($package_file);
        return $package;
    }

    /**
     * Create a generic package representation for a specific PEAR environment.
     *
     * @param Components_Pear_InstallLocation $environment  The PEAR environment.
     *
     * @return Components_Pear_Package The generic PEAR package.
     */
    private function _createPackage(Components_Pear_InstallLocation $environment)
    {
        $package = $this->_dependencies->createInstance('Components_Pear_Package');
        $package->setFactory($this);
        $package->setEnvironment($environment);
        return $package;
    }

    /**
     * Create a tree helper for a specific PEAR environment..
     *
     * @param string $config_file The path to the configuration file.
     * @param string $root_path   The basic root path for Horde packages.
     * @param array  $options The application options
     *
     * @return Components_Helper_Tree The tree helper.
     */
    public function createTreeHelper($config_file, $root_path, array $options)
    {
        $environment = $this->_dependencies->createInstance('Components_Pear_InstallLocation');
        $environment->setFactory($this);
        $environment->setLocation(
            dirname($config_file),
            basename($config_file)
        );
        $environment->setResourceDirectories($options);
        return new Components_Helper_Tree(
            $this, $environment, new Components_Helper_Root($root_path)
        );
    }

    /**
     * Create a tree helper for a specific PEAR environment..
     *
     * @param string $config_file The path to the configuration file.
     * @param string $root_path   The basic root path for Horde packages.
     * @param array  $options The application options
     *
     * @return Components_Helper_Tree The tree helper.
     */
    public function createSimpleTreeHelper($root_path)
    {
        return new Components_Helper_Tree(
            $this,
            $this->_dependencies->createInstance('Components_Pear_InstallLocation'),
            new Components_Helper_Root($root_path)
        );
    }

    /**
     * Return the PEAR Package representation.
     *
     * @param string                          $package_xml_path Path to the package.xml file.
     * @param Components_Pear_InstallLocation $environment      The PEAR environment.
     *
     * @return PEAR_PackageFile
     */
    public function getPackageFile(
        $package_xml_path,
        Components_Pear_InstallLocation $environment
    ) {
        $pkg = new PEAR_PackageFile($environment->getPearConfig());
        return Components_Exception_Pear::catchError(
            $pkg->fromPackageFile($package_xml_path, PEAR_VALIDATE_NORMAL)
        );
    }

    /**
     * Return the package.xml handler.
     *
     * @param string                          $package_xml_path Path to the package.xml file.
     *
     * @return Horde_Pear_Package_Xml
     */
    public function getPackageXml($package_xml_path)
    {
        return new Horde_Pear_Package_Xml(fopen($package_xml_path, 'r'));
    }

    /**
     * Return the PEAR Package representation based on a local *.tgz archive.
     *
     * @param string                          $package_tgz_path Path to the *.tgz file.
     * @param Components_Pear_InstallLocation $environment      The PEAR environment.
     *
     * @return PEAR_PackageFile
     */
    public function getPackageFileFromTgz(
        $package_tgz_path,
        Components_Pear_InstallLocation $environment
    ) {
        $pkg = new PEAR_PackageFile($environment->getPearConfig());
        return Components_Exception_Pear::catchError(
            $pkg->fromTgzFile($package_tgz_path, PEAR_VALIDATE_NORMAL)
        );
    }

    /**
     * Create a new PEAR Package representation.
     *
     * @param string                          $package_xml_dir Path to the parent directory of the package.xml file.
     * @param Components_Pear_InstallLocation $environment      The PEAR environment.
     *
     * @return PEAR_PackageFile
     */
    public function createPackageFile(
        $package_xml_dir
    ) {
        $environment = $this->_dependencies->getInstance('Components_Pear_InstallLocation');
        $pkg = new PEAR_PackageFile_v2_rw();
        $pkg->setPackage(basename($package_xml_dir));
        $pkg->setDescription('TODO');
        $pkg->setSummary('TODO');
        $pkg->setReleaseVersion('1.0.0alpha1');
        $pkg->setApiVersion('1.0.0alpha1');
        $pkg->setReleaseStability('alpha');
        $pkg->setApiStability('alpha');
        $pkg->setChannel('pear.horde.org');
        $pkg->addMaintainer(
            'lead',
            'chuck',
            'Chuck Hagenbuch',
            'chuck@horde.org'
        );
        $pkg->addMaintainer(
            'lead',
            'jan',
            'Jan Schneider',
            'jan@horde.org'
        );
        $pkg->setLicense('TODO', 'TODO');
        $pkg->setNotes('* Initial release.');
        $pkg->clearContents(true);
        $pkg->clearDeps();
        $pkg->setPhpDep('5.2.0');
        $pkg->setPearinstallerDep('1.7.0');
        $pkg->setPackageType('php');
        $pkg->addFile('', 'something', array('role' => 'php'));
        new PEAR_Validate();
        return Components_Exception_Pear::catchError(
            $pkg->getDefaultGenerator()->toPackageFile($package_xml_dir, 0)
        );
    }

    /**
     * Create a package dependency helper.
     *
     * @param Components_Pear_Package $package The package.
     *
     * @return Components_Pear_Dependencies The dependency helper.
     */
    public function createDependencies(Components_Pear_Package $package)
    {
        $dependencies = $this->_dependencies->createInstance('Components_Pear_Dependencies');
        $dependencies->setPackage($package);
        return $dependencies;
    }
}