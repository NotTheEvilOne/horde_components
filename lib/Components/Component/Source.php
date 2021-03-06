<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Components
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @author   Jan Schneider <jan@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * Represents a source component.
 *
 * @category Horde
 * @package  Components
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @author   Jan Schneider <jan@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Components_Component_Source extends Components_Component_Base
{
    /**
     * Path to the source directory.
     *
     * @var string
     */
    protected $_directory;

    /**
     * The release notes handler.
     *
     * @var Components_Release_Notes
     */
    protected $_notes;

    /**
     * The PEAR package file representing the component.
     *
     * @var PEAR_PackageFile
     */
    protected $_package_file;

    /**
     * Cached file wrappers.
     *
     * @var Components_Wrapper[]
     */
    protected $_wrappers = array();

    /**
     * Constructor.
     *
     * @param string $directory                     Path to the source
     *                                              directory.
     * @param Components_Config $config             The configuration for the
     *                                              current job.
     * @param Components_Release_Notes $notes       The release notes.
     * @param Components_Component_Factory $factory Generator for additional
     *                                              helpers.
     */
    public function __construct(
        $directory,
        Components_Config $config,
        Components_Release_Notes $notes,
        Components_Component_Factory $factory
    )
    {
        $this->_directory = realpath($directory);
        $this->_notes = $notes;
        parent::__construct($config, $factory);
    }

    /**
     * Return a data array with the most relevant information about this
     * component.
     *
     * @return array Information about this component.
     */
    public function getData()
    {
        $data = new stdClass;
        $package = $this->getPackageXml();
        $data->name = $package->getName();
        $data->summary = $package->getSummary();
        $data->description = $package->getDescription();
        $data->version = $package->getVersion();
        $data->releaseDate = $package->getDate();
        $data->download = sprintf('https://pear.horde.org/get/%s-%s.tgz',
                                  $data->name, $data->version);
        $data->hasCi = $this->_hasCi();
        return $data;
    }

    /**
     * Indicate if the component has a local package.xml.
     *
     * @return boolean True if a package.xml exists.
     */
    public function hasLocalPackageXml()
    {
        return $this->getPackageXml()->exists();
    }

    /**
     * Returns the link to the change log.
     *
     * @return string The link to the change log.
     */
    public function getChangelogLink()
    {
        $base = $this->getFactory()->getGitRoot()->getRoot();
        return $this->getFactory()->createChangelog($this)->getChangelogLink(
            preg_replace(
                '#^' . $base . '#', '', $this->_directory
            )
        );
    }

    /**
     * Return the path to the release notes.
     *
     * @return string|boolean The path to the release notes or false.
     */
    public function getReleaseNotesPath()
    {
        foreach (array('release.yml', 'RELEASE_NOTES') as $file) {
            foreach (array('docs', 'doc') as $directory) {
                $path = $this->_directory . '/' . $directory . '/' . $file;
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        return false;
    }

    /**
     * Return the path to a DOCS_ORIGIN file within the component.
     *
     * @return array|NULL An array containing the path name and the component
     *                    base directory or NULL if there is no DOCS_ORIGIN
     *                    file.
     */
    public function getDocumentOrigin()
    {
        foreach (array('doc', 'docs') as $doc_dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->_directory . '/' . $doc_dir)) as $file) {
                if ($file->isFile() &&
                    $file->getFilename() == 'DOCS_ORIGIN') {
                    return array($file->getPathname(), $this->_directory);
                }
            }
        }
    }

    /**
     * Updates the information files for this component.
     *
     * @param string $action   The action to perform. Either "update", "diff",
     *                         or "print".
     * @param array  $options  Options for this operation.
     *
     * @return string|boolean  The result of the action.
     */
    public function updatePackage($action, $options)
    {
        if (!$this->getPackageXml()->exists()) {
            if (!empty($options['theme'])) {
                $this->getFactory()->createThemePackageFile($this->_directory);
            } else {
                $this->getFactory()->createPackageFile($this->_directory);
            }
            unset($this->_wrappers['PackageXml']);
        }

        $package_xml = $this->updatePackageFromHordeYml();

        /* Skip updating if this is a PECL package. */
        $composer_json = null;
        if (!$package_xml->findNode('/p:package/p:providesextension')) {
            $package_xml->updateContents(
                !empty($options['theme'])
                    ? $this->getFactory()->createThemeContentList($this->_directory)
                    : $this->getFactory()->createContentList($this->_directory),
                $options
            );

            $composer_json = $this->updateComposerFromHordeYml();
        }

        switch($action) {
        case 'print':
            return implode("===\n", $this->_wrappers);
        case 'diff':
            $diff = '';
            foreach ($this->_wrappers as $wrapper) {
                $diff_tmp = $wrapper->diff();
                if (!empty($diff_tmp)) {
                    $path = $wrapper->getLocalPath($this->_directory);
                    $diff .= '--- a/' . $path . "\n"
                        . '--- b/' . $path . "\n"
                        . $wrapper->diff();
                }
            }
            return $diff;
        default:
            foreach ($this->_wrappers as $wrapper) {
                $wrapper->save();
                if (!empty($options['commit'])) {
                    $options['commit']->add($wrapper, $this->_directory);
                }
            }
            return true;
        }
    }

    /**
     * Rebuilds the basic information in a package.xml file from the .horde.yml
     * definition.
     *
     * @return Horde_Pear_Package_Xml  The updated package.xml handler.
     */
    public function updatePackageFromHordeYml()
    {
        $xml = $this->getPackageXml();
        $yaml = $this->getWrapper('HordeYml');

        // Update texts.
        $name = $yaml['id'];
        if ($yaml['type'] == 'library') {
            $name = 'Horde_' . $name;
        }
        $xml->replaceTextNode('/p:package/p:name', $name);
        $xml->replaceTextNode('/p:package/p:summary', $yaml['full']);
        $xml->replaceTextNode('/p:package/p:description', $yaml['description']);

        // Update versions.
        $xml->setVersion(
            Components_Helper_Version::validatePear($yaml['version']['release']),
            Components_Helper_Version::validatePear($yaml['version']['api'])
        );
        $xml->setState($yaml['state']['release'], $yaml['state']['api']);

        // Update date.
        $changelog = $this->getFactory()->createChangelog($this);
        if ($changelog->changelogFileExists()) {
            $xml->replaceTextNode(
                '/p:package/p:date',
                $this->getWrapper('ChangelogYml')[$yaml['version']['release']]['date']
            );
        }

        // Update license.
        $xml->replaceTextNode(
            '/p:package/p:license',
            $yaml['license']['identifier']
        );
        if ($yaml['license']['uri']) {
            $node = $xml->findNode('/p:package/p:license');
            $node->setAttribute('uri', $yaml['license']['uri']);
        }

        // Update authors.
        while ($node = $xml->findNode('/p:package/p:lead|p:developer')) {
            $xml->removeWhitespace($node->previousSibling);
            $node->parentNode->removeChild($node);
        }
        if (!empty($yaml['authors'])) {
            foreach ($yaml['authors'] as $author) {
                $xml->addAuthor(
                    $author['name'],
                    $author['user'],
                    $author['email'],
                    $author['active'],
                    $author['role']
                );
            }
        }

        // Update dependencies.
        if (!empty($yaml['dependencies'])) {
            $this->_updateDependencies($xml, $yaml['dependencies']);
        }

        return $xml;
    }

    /**
     * Update dependencies.
     *
     * @param Horde_Pear_Package_Xml $xml  A package.xml handler.
     * @param array $dependencies          A list of dependencies.
     */
    protected function _updateDependencies($xml, $dependencies)
    {
        foreach (array('package', 'extension') as $type) {
            while ($node = $xml->findNode('/p:package/p:dependencies/p:required/p:' . $type)) {
                $xml->removeWhitespace($node->previousSibling->previousSibling);
                $xml->removeWhitespace($node->previousSibling);
                $node->parentNode->removeChild($node);
            }
        }
        if ($node = $xml->findNode('/p:package/p:dependencies/p:optional')) {
            $xml->removeWhitespace($node->previousSibling->previousSibling);
            $xml->removeWhitespace($node->previousSibling);
            $node->parentNode->removeChild($node);
        }
        $php = Components_Helper_Version::composerToPear(
            $dependencies['required']['php']
        );
        foreach ($php as $tag => $version) {
            $xml->replaceTextNode(
                '/p:package/p:dependencies/p:required/p:php/p:' . $tag,
                $version
            );
        }
        foreach ($dependencies as $required => $dependencyTypes) {
            foreach ($dependencyTypes as $type => $deps) {
                $this->_addDependency($xml, $required, $type, $deps);
            }
        }
    }

    /**
     * Adds a number of dependencies of the same kind.
     *
     * @param Horde_Pear_Package_Xml $xml  A package.xml handler.
     * @param string $required             A required dependency? Either
     *                                     'required' or 'optional'.
     * @param string $type                 A dependency type from .horde.yml.
     * @param array $dependencies          A list of dependency names and
     *                                     versions.
     */
    protected function _addDependency($xml, $required, $type, $dependencies)
    {
        switch ($type) {
        case 'php':
            return;
        case 'pear':
            $type = 'package';
            break;
        case 'ext':
            $type = 'extension';
            break;
        default:
            throw new Components_Exception(
                'Unknown dependency type: ' . $type
            );
        }
        foreach ($dependencies as $dependency => $version) {
            if (is_array($version)) {
                $constraints = $version;
                unset($constraints['version']);
                $version = $version['version'];
            } else {
                $constraints = array();
            }
            switch ($type) {
            case 'package':
                list($channel, $name) = explode('/', $dependency);
                $constraints = array_merge(
                    array('name' => $name, 'channel' => $channel),
                    Components_Helper_Version::composerToPear($version),
                    $constraints
                );
                break;
            case 'extension':
                $constraints = array_merge(
                    array('name' => $dependency),
                    Components_Helper_Version::composerToPear($version),
                    $constraints
                );
                break;
            }
            $xml->addDependency($required, $type, $constraints);
        }
    }

    /**
     * Rebuilds the basic information in a composer.json file from the
     * .horde.yml definition.
     *
     * @return string  The updated composer.json content.
     */
    public function updateComposerFromHordeYml()
    {
        $yaml = $this->getWrapper('HordeYml');
        $name = 'horde/'
            . str_replace('_', '-', Horde_String::lower($yaml['id']));
        $replaceVersion = preg_replace(
            '/^(\d+)\..*/',
            '$1.*',
            $yaml['version']['release']
        );
        $replacePrefix = $yaml['type'] == 'library' ? 'Horde_' : '';
        $dependencies = array('required' => array(), 'optional' => array());
        foreach ($yaml['dependencies'] as $required => $dependencyTypes) {
            foreach ($dependencyTypes as $type => $packages) {
                if (!is_array($packages)) {
                    $dependencies[$required][$type] = $packages;
                    continue;
                }
                foreach ($packages as $package => $version) {
                    if (is_array($version)) {
                        $version = $version['version'];
                    }
                    $dependencies[$required][$type . '-' . $package] = $version;
                }
            }
        }
        $authors = array();
        foreach ($yaml['authors'] as $author) {
            $authors[] = array(
                'name' => $author['name'],
                'email' => $author['email'],
                'role' => $author['role'],
            );
        }
        if ($yaml['name'] == 'Core' ||
            strpos($yaml['name'], 'Horde Groupware') === 0) {
            $prefix = 'Horde';
        } elseif ($yaml['type'] == 'library') {
            $prefix = 'Horde_' . $yaml['name'];
        } else {
            $prefix = $yaml['name'];
        }
        $autoload = array('psr-0' => array($prefix => 'lib/'));
        $type = $yaml['type'] == 'library' ? 'library' : 'project';
        $homepage = isset($yaml['homepage'])
            ? $yaml['homepage']
            : 'https://www.horde.org';

        $json = $this->getWrapper('ComposerJson');
        $json->exchangeArray(array_filter(array(
            'name' => $name,
            'description' => $yaml['full'],
            'type' => $type,
            'homepage' => $homepage,
            'license' => $yaml['license']['identifier'],
            'authors' => $authors,
            'version' => $yaml['version']['release'],
            'time' => gmdate('Y-m-d'),
            'repositories' => array(
                array(
                    'type' => 'pear',
                    'url' => 'https://pear.horde.org',
                ),
            ),
            'require' => $dependencies['required'],
            'suggest' => $dependencies['optional'],
            'replace' => array(
                'pear-pear.horde.org/' . $replacePrefix . $yaml['id'] => $replaceVersion,
                'pear-horde/' . $replacePrefix . $yaml['id'] => $replaceVersion,
            ),
            'autoload' => $autoload,
        )));

        return $json;
    }

    /**
     * Update the component changelog.
     *
     * @param string $log     The log entry.
     * @param array $options  Options for the operation.
     *
     * @return string[]  Output messages.
     */
    public function changed($log, $options)
    {
        $output = array();

        // Create changelog.yml
        $helper = $this->getFactory()->createChangelog($this);
        if (!$helper->changelogFileExists() &&
            $this->getPackageXml()->exists()) {
            $helper->migrateToChangelogYml($this->getPackageXml());
            if (empty($options['pretend'])) {
                $output[] = sprintf(
                    'Created %s.',
                    $helper->changelogFileExists()
                );
            } else {
                $output[] = sprintf(
                    'Would create %s now.',
                    $helper->changelogFileExists()
                );
            }
        }

        // Update changelog.yml
        $file = $helper->changelogYml($log, $options);
        if ($file) {
            if (empty($options['pretend'])) {
                $this->getWrapper('ChangelogYml')->save();
                $output[] = sprintf(
                    'Added new note to version %s of %s.',
                    $this->getWrapper('HordeYml')['version']['release'],
                    $file
                );
            } else {
                $output[] = sprintf(
                    'Would add change log entry to %s now.',
                    $file
                );
            }
            if (!empty($options['commit'])) {
                $options['commit']->add($file, $this->_directory);
            }
        }

        // Update package.xml
        if (empty($options['nopackage'])) {
            $xml = $this->getPackageXml();
            if ($helper->changelogFileExists()) {
                $file = $helper->updatePackage($xml);
                if (empty($options['pretend'])) {
                    $xml->save();
                    $output[] = sprintf('Updated %s.', $xml->getFileName());
                } else {
                    $output[] = sprintf('Would update %s now.', $xml->getFileName());
                }
            } else {
                $file = $helper->packageXml($log, $xml);
                if (empty($options['pretend'])) {
                    $xml->save();
                    $output[] = sprintf(
                        'Added new note to version %s of %s.',
                        $xml->getVersion(),
                        $xml->getFileName()
                    );
                } else {
                    $output[] = sprintf(
                        'Would add change log entry to %s now.',
                        $xml->getFileName()
                    );
                }
            }
            if ($file && !empty($options['commit2'])) {
                $options['commit2']->add($file, $this->_directory);
            }
        }

        // Update CHANGES
        if (empty($options['nochanges'])) {
            $file = $helper->updateChanges();
            if ($file) {
                if (empty($options['pretend'])) {
                    $this->getWrapper('Changes')->save();
                    $output[] = sprintf('Updated %s.', $file);
                } else {
                    $output[] = sprintf('Would update %s now.', $file);
                }
                if (!empty($options['commit2'])) {
                    $options['commit2']->add($file, $this->_directory);
                }
            }
        }

        return $output;
    }

    /**
     * Timestamp the package files with the current time.
     *
     * @param array $options  Options for the operation.
     *
     * @return string The success message.
     */
    public function timestampAndSync($options)
    {
        $helper = $this->getFactory()->createChangelog($this);
        if (empty($options['pretend'])) {
            $helper->timestamp();
            if (empty($options['pretend'])) {
                $this->getWrapper('ChangelogYml')->save();
            }
            if (!empty($options['commit'])) {
                $options['commit']->add(
                    $helper->changelogFileExists(), $this->_directory
                );
            }
            $xml = $this->updatePackageFromHordeYml();
            $xml = $this->getPackageXml();
            $xml->syncCurrentVersion();
            $xml->save();
            if (!empty($options['commit'])) {
                $options['commit']->add($xml, $this->_directory);
            }
            $result = sprintf(
                'Marked %s and %s with current timestamp and synchronized the change log.',
                $helper->changelogFileExists(),
                $this->getPackageXmlPath()
            );
        } else {
            $result = sprintf(
                'Would timestamp %s and %s now and synchronize its change log.',
                $helper->changelogFileExists(),
                $this->getPackageXmlPath()
            );
        }
        return $result;
    }

    /**
     * Sets the version in the component.
     *
     * @param string $rel_version  The new release version number.
     * @param string $api_version  The new api version number.
     * @param array  $options      Options for the operation.
     *
     * @return string  Result message.
     */
    public function setVersion(
        $rel_version = null, $api_version = null, $options = array()
    )
    {
        $changelog = $this->getWrapper('ChangelogYml');
        $updated = array();
        if ($changelog->exists()) {
            $this->getFactory()
                ->createChangelog($this)
                ->setVersion($rel_version, $api_version);
            $updated[] = $changelog;
        }
        $updated = array_merge(
            $updated,
            $this->_setVersion($rel_version, $api_version)
        );

        if (!empty($options['commit'])) {
            foreach ($updated as $wrapper) {
                $options['commit']->add($wrapper, $this->_directory);
            }
        }
        $list = $this->_getWrapperNames($updated);
        if (empty($options['pretend'])) {
            $result = sprintf(
                'Set release version "%s" and api version "%s" in %s.',
                $rel_version,
                $api_version,
                $list
            );
        } else {
            $result = sprintf(
                'Would set release version "%s" and api version "%s" in %s now.',
                $rel_version,
                $api_version,
                $list
            );
        }

        return $result;
    }

    /**
     * Sets the version in .horde.yml, package.xml and CHANGES.
     *
     * @param string $rel_version  The new release version number.
     * @param string $api_version  The new api version number.
     *
     * @return Components_Wrapper[]  Wrappers of updated files.
     */
    public function _setVersion($rel_version = null, $api_version = null)
    {
        // Update .horde.yml.
        $yaml = $this->getWrapper('HordeYml');
        if ($rel_version) {
            $yaml['version']['release'] = $rel_version;
        }
        if ($api_version) {
            $yaml['version']['api'] = $api_version;
        }
        $updated = array($yaml);

        // Update package.xml
        $package_xml = $this->updatePackageFromHordeYml();
        $updated[] = $package_xml;

        // Update CHANGES.
        $changes = $this->getWrapper('Changes');
        if ($changes->exists()) {
            $this->getFactory()
                ->createChangelog($this)
                ->updateChanges();
            $updated[] = $changes;
        }

        // Update Application.php/Bundle.php.
        $application = $this->getWrapper('ApplicationPhp');
        if ($application->exists()) {
            $application->setVersion(
                Components_Helper_Version::pearToHordeWithBranch(
                    $rel_version,
                    $this->_notes->getBranch()
                )
            );
            $updated[] = $application;
        }

        return $updated;
    }

    /**
     * Sets the state in the package.xml
     *
     * @param string $rel_state  The new release state.
     * @param string $api_state  The new api state.
     *
     * @return string The success message.
     */
    public function setState(
        $rel_state = null, $api_state = null, $options = array()
    )
    {
        $package = $this->getPackageXml();
        $package->setState($rel_state, $api_state);
        if (empty($options['pretend'])) {
            if (!empty($options['commit'])) {
                $options['commit']->add($package, $this->_directory);
            }
            $result = sprintf(
                'Set release state "%s" and api state "%s" in %s.',
                $rel_state,
                $api_state,
                $this->getPackageXmlPath()
            );
        } else {
            $result = sprintf(
                'Would set release state "%s" and api state "%s" in %s now.',
                $rel_state,
                $api_state,
                $this->getPackageXmlPath()
            );
        }
        return $result;
    }

    /**
     * Add the next version to the component files.
     *
     * @param string $version           The new version number.
     * @param string $initial_note      The text for the initial note.
     * @param string $stability_api     The API stability for the next release.
     * @param string $stability_release The stability for the next release.
     * @param array $options            Options for the operation.
     *
     * @return string The success message.
     */
    public function nextVersion(
        $version,
        $initial_note,
        $stability_api = null,
        $stability_release = null,
        $options = array()
    )
    {
        $changelog = $this->getWrapper('ChangelogYml');
        $currentVersion = $this->getWrapper('HordeYml')['version']['release'];
        if (!isset($changelog[$currentVersion])) {
            throw new Components_Exception(
                sprintf(
                    'Current version %s not found in %s',
                    $currentVersion,
                    $changelog->getFileName()
                )
            );
        }
        $nextVersion = $changelog[$currentVersion];
        $nextVersion['notes'] = "\n" . $initial_note;
        if ($stability_release) {
            $nextVersion['state']['release'] = $stability_release;
        }
        if ($stability_api) {
            $nextVersion['state']['api'] = $stability_api;
        }
        $changelog[$version] = $nextVersion;
        $changelog->uksort(
            function($a, $b)
            {
                return version_compare($b, $a);
            }
        );

        $updated = $this->_setVersion($version);
        $updated[] = $changelog;

        $helper = $this->getFactory()->createChangelog($this);
        $helper->updatePackage($this->getWrapper('PackageXml'));

        if (!empty($options['commit'])) {
            foreach ($updated as $wrapper) {
                $options['commit']->add($wrapper, $this->_directory);
            }
        }

        $list = $this->_getWrapperNames($updated);
        if (empty($options['pretend'])) {
            foreach ($updated as $wrapper) {
                $wrapper->save();
            }
            $result = sprintf(
                'Added next version "%s" with the initial note "%s" to %s.',
                $version,
                $initial_note,
                $list
            );
        } else {
            $result = sprintf(
                'Would add next version "%s" with the initial note "%s" to %s now.',
                $version,
                $initial_note,
                $list
            );
        }
        if ($stability_release !== null) {
            $result .= ' Release stability: "' . $stability_release . '".';
        }
        if ($stability_api !== null) {
            $result .= ' API stability: "' . $stability_api . '".';
        }

        return $result;
    }

    /**
     * Replace the current sentinel.
     *
     * @param string $changes New version for the CHANGES file.
     * @param string $app     New version for the Application.php file.
     * @param array  $options Options for the operation.
     *
     * @return string The success message.
     */
    public function currentSentinel($changes, $app, $options)
    {
        $sentinel = $this->getFactory()->createSentinel($this->_directory);
        if (empty($options['pretend'])) {
            $sentinel->replaceChanges($changes);
            $sentinel->updateApplication($app);
            $action = 'Did';
        } else {
            $action = 'Would';
        }
        $files = array(
            'changes' => $sentinel->changesFileExists(),
            'app'     => $sentinel->applicationFileExists(),
            'bundle'  => $sentinel->bundleFileExists()
        );
        $result = array();
        foreach ($files as $key => $file) {
            if (empty($file)) {
                continue;
            }
            if (!empty($options['commit'])) {
                $options['commit']->add($file, $this->_directory);
            }
            $version = ($key == 'changes') ? $changes : $app;
            $result[] = sprintf(
                '%s replace sentinel in %s with "%s" now.',
                $action,
                $file,
                $version
            );
        }
        return $result;
    }

    /**
     * Tag the component.
     *
     * @param string                   $tag     Tag name.
     * @param string                   $message Tag message.
     * @param Components_Helper_Commit $commit  The commit helper.
     */
    public function tag($tag, $message, $commit)
    {
        $commit->tag($tag, $message, $this->_directory);
    }

    /**
     * Place the component source archive at the specified location.
     *
     * @param string $destination The path to write the archive to.
     * @param array  $options     Options for the operation.
     *
     * @return array An array with at least [0] the path to the resulting
     *               archive, optionally [1] an array of error strings, and [2]
     *               PEAR output.
     */
    public function placeArchive($destination, $options = array())
    {
        if (!$this->getPackageXml()->exists()) {
            throw new Components_Exception(
                sprintf(
                    'The component "%s" still lacks a package.xml file at "%s"!',
                    $this->getName(),
                    $this->getPackageXmlPath()
                )
            );
        }

        if (empty($options['keep_version'])) {
            $version = preg_replace(
                '/([.0-9]+).*/',
                '\1dev' . strftime('%Y%m%d%H%M'),
                $this->getVersion()
            );
        } else {
            $version = $this->getVersion();
        }

        $this->createDestination($destination);

        $package = $this->_getPackageFile();
        $pkg = $this->getFactory()->pear()->getPackageFile(
            $this->getPackageXmlPath(),
            $package->getEnvironment()
        );
        $pkg->_packageInfo['version']['release'] = $version;
        $pkg->setDate(date('Y-m-d'));
        $pkg->setTime(date('H:i:s'));
        if (isset($options['logger'])) {
            $pkg->setLogger($options['logger']);
        }
        $errors = array();
        ob_start();
        $old_dir = getcwd();
        chdir($destination);
        try {
            $pear_common = new PEAR_Common();
            $result = Components_Exception_Pear::catchError(
                $pkg->getDefaultGenerator()->toTgz($pear_common)
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
        $output = array($result, $errors);
        $output[] = ob_get_clean();
        return $output;
    }

    /**
     * Identify the repository root.
     *
     * @param Components_Helper_Root $helper The root helper.
     *
     * @return string  The repository root.
     */
    public function repositoryRoot(Components_Helper_Root $helper)
    {
        if (($result = $helper->traverseHierarchy($this->_directory)) === false) {
            $this->_errors[] = sprintf(
                'Unable to determine Horde repository root from component path "%s"!',
                $this->_directory
            );
        }
        return $result;
    }

    /**
     * Install a component.
     *
     * @param Components_Pear_Environment $env The environment to install
     *                                         into.
     * @param array                 $options   Install options.
     * @param string                $reason    Optional reason for adding the
     *                                         package.
     */
    public function install(
        Components_Pear_Environment $env, $options = array(), $reason = ''
    )
    {
        $this->installChannel($env, $options);
        if (!empty($options['symlink'])) {
            $env->linkPackageFromSource(
                $this->getPackageXmlPath(), $reason
            );
        } else {
            $env->addComponent(
                $this->getName(),
                array($this->getPackageXmlPath()),
                $this->getBaseInstallationOptions($options),
                ' from source in ' . dirname($this->getPackageXmlPath()),
                $reason
            );
        }
    }

    /**
     * Return a PEAR package representation for the component.
     *
     * @return Horde_Pear_Package_Xml The package representation.
     */
    protected function getPackageXml()
    {
        return $this->getWrapper('PackageXml');
    }

    /**
     * Return a PEAR PackageFile representation for the component.
     *
     * @return Components_Pear_Package The package representation.
     */
    private function _getPackageFile()
    {
        $options = $this->getOptions();
        if (isset($options['pearrc'])) {
            return $this->getFactory()->pear()
                ->createPackageForPearConfig(
                    $this->getPackageXmlPath(), $options['pearrc']
                );
        }
        return $this->getFactory()->pear()
            ->createPackageForDefaultLocation(
                $this->getPackageXmlPath()
            );
    }

    /**
     * Return the path to the package.xml file of the component.
     *
     * @return string The path to the package.xml file.
     */
    public function getPackageXmlPath()
    {
        return $this->getPackageXml()->getFullPath();
    }

    /**
     * Returns the path to the documenation directory.
     *
     * @return string  The directory name.
     */
    public function getDocDirectory()
    {
        if (is_dir($this->_directory . '/doc')) {
            $dir = $this->_directory . '/doc';
        } elseif (is_dir($this->_directory . '/docs')) {
            $dir = $this->_directory . '/docs';
        } else {
            $dir = $this->_directory . '/doc';
        }
        $info = $this->getWrapper('HordeYml');
        if ($info['type'] == 'library') {
            $dir .= '/Horde/' . str_replace('_', '/', $info['id']);
        }
        return $dir;
    }

    /**
     * Returns a file wrapper.
     *
     * @param string $file  File wrapper to return.
     *
     * @return Components_Wrapper  The requested file wrapper.
     */
    public function getWrapper($file)
    {
        if (!isset($this->_wrappers[$file])) {
            switch ($file) {
            case 'HordeYml':
                $this->_wrappers[$file] = new Components_Wrapper_HordeYml(
                    $this->_directory
                );
                if (!$this->_wrappers[$file]->exists()) {
                    throw new Components_Exception(
                        $this->_wrappers[$file]->getFileName() . ' is missing.'
                    );
                }
                break;
            case 'ComposerJson':
                $this->_wrappers[$file] = new Components_Wrapper_ComposerJson(
                    $this->_directory
                );
                break;
            case 'PackageXml':
                $this->_wrappers[$file] = new Components_Wrapper_PackageXml(
                    $this->_directory
                );
                break;
            case 'ChangelogYml':
                $this->_wrappers[$file] = new Components_Wrapper_ChangelogYml(
                    $this->getDocDirectory()
                );
                break;
            case 'Changes':
                $this->_wrappers[$file] = new Components_Wrapper_Changes(
                    $this->getDocDirectory()
                );
                break;
            case 'ApplicationPhp':
                $this->_wrappers[$file] = new Components_Wrapper_ApplicationPhp(
                    $this->_directory
                );
                break;
            default:
                throw new InvalidArgumentException(
                    $file . ' is not a supported file wrapper'
                );
            }
        }
        return $this->_wrappers[$file];
    }

    /**
     * Saves all loaded file wrappers.
     */
    public function saveWrappers()
    {
        foreach ($this->_wrappers as $wrapper) {
            $wrapper->save();
        }
    }

    /**
     * Converts a list of wrappers to a file list.
     *
     * @param Components_Wrapper[] $wrappers  A list of wrappers.
     *
     * @return string  A comma-separated file list.
     */
    protected function _getWrapperNames($wrappers)
    {
        return implode(
            ', ',
            array_map(
                function($wrapper)
                {
                    return $wrapper->getLocalPath($this->_directory);
                },
                $wrappers
            )
        );
    }
}
