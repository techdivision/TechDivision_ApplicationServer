<?php

/**
 * TechDivision\ApplicationServer\PbcClassLoaderFactory
 *
 * PHP version 5
 *
 * @category  Appserver
 * @package   TechDivision_ApplicationServer
 * @author    Bernhard Wick <b.wick@techdivision.com>
 * @copyright 2013 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */

namespace TechDivision\ApplicationServer;

use TechDivision\PBC\Config;
use TechDivision\Application\Interfaces\ApplicationInterface;
use TechDivision\ApplicationServer\Api\Node\ClassLoaderNodeInterface;

/**
 * A factory for the PBC class loader instances.
 *
 * @category  Appserver
 * @package   TechDivision_ApplicationServer
 * @author    Bernhard Wick <b.wick@techdivision.com>
 * @copyright 2013 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class PbcClassLoaderFactory
{

    /**
     * Visitor method that registers the class loaders in the application.
     *
     * @param \TechDivision\Application\Interfaces\ApplicationInterface                   $application   The application instance to register the class loader with
     * @param \TechDivision\ApplicationServer\Interfaces\Api\NodeClassLoaderNodeInterface $configuration The class loader configuration
     *
     * @return void
     */
    public static function visit(ApplicationInterface $application, ClassLoaderNodeInterface $configuration)
    {

        // load the web application path we want to register the class loader for
        $webappPath = $application->getWebappPath();

        // initialize the class path and the enforcement directories
        $classPath = array();
        $enforcementDirs = array();

        // add the possible class path if folder is available
        foreach ($configuration->getDirectories() as $directory) {
            if (is_dir($webappPath . $directory->getNodeValue())) {
                array_push($classPath, $webappPath . $directory->getNodeValue());
                if ($directory->isEnforced()) {
                    array_push($enforcementDirs, $webappPath . $directory->getNodeValue());
                }
            }
        }

        // initialize the arrays of different omit possibilities
        $omittedEnforcement = array();
        $omittedAutoLoading = array();

        // iterate over all namespaces and check if they are omitted in one or the other way
        foreach ($configuration->getNamespaces() as $namespace) {

            // is the enforcement omitted for this namespace?
            if ($namespace->omitEnforcement()) {

                $omittedEnforcement[] = $namespace->getNodeValue()->__toString();
            }

            // is the autoloading omitted for this namespace?
            if ($namespace->omitAutoLoading()) {

                $omittedAutoLoading[] = $namespace->getNodeValue()->__toString();
            }
        }

        // initialize the class loader configuration
        $config = new Config();

        // set the environment mode we want to use
        $config->setValue('environment', $configuration->getEnvironment());

        // set the cache directory
        $config->setValue('cache/dir', $application->getCacheDir());

        // set the default autoloader values
        $config->setValue('autoloader/dirs', $classPath);

        // collect the omitted namespaces (if any)
        $config->setValue('autoloader/omit', $omittedAutoLoading);
        $config->setValue('enforcement/omit', $omittedEnforcement);

        // set the default enforcement configuration values
        $config->setValue('enforcement/dirs', $enforcementDirs);
        $config->setValue('enforcement/enforce-default-type-safety', $configuration->getTypeSafety());
        $config->setValue('enforcement/processing', $configuration->getProcessing());
        $config->setValue('enforcement/level', $configuration->getEnforcementLevel());
        $config->setValue('enforcement/logger', $application->getInitialContext()->getSystemLogger());

        // create the autoloader instance and fill the structure map
        $application->addClassLoader(new PbcClassLoader($config));
    }
}
