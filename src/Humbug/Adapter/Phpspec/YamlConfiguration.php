<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/humbug/blob/master/LICENSE New BSD License
 */

namespace Humbug\Adapter\Phpspec;

use Humbug\Container;
use Humbug\Adapter\ConfigurationAbstract;
use Humbug\Exception\RuntimeException;
use Humbug\Exception\InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class YamlConfiguration extends ConfigurationAbstract
{

    private static $config;

    private static $container;

    /**
     * Assemble configuration file required for current mutation testing iteration
     * of the underlying tests.
     *
     * @return string
     */
    public static function assemble(Container $container, $firstRun = false, array $testSuites = [])
    {
        self::$config = self::parseConfigurationFile();
        self::$container = $container;
        if (empty(self::$config)) {
            throw new RuntimeException(
                'Unable to locate the phpspec configuration file'
            );
        }

        self::$config['formatter.name'] = 'tap';
        if (!isset(self::$config['extensions']) || !is_array(self::$config['extensions'])) {
            self::$config['extensions'] = [];
        }

        self::handleCodeCoverageLogging();

        $saveFile = self::$container->getCacheDirectory() . '/phpspec.humbug.yml';
        $yaml = Yaml::dump(self::$config, 5);
        file_put_contents($saveFile, $yaml);
        return $saveFile;
    }

    protected static function handleCodeCoverageLogging()
    {
        if (!isset(self::$config['code_coverage'])
        || !is_array(self::$config['code_coverage'])) {
            self::$config['code_coverage'] = [];
        }
        if (!isset(self::$config['code_coverage']['whitelist'])
        || !is_array(self::$config['code_coverage']['whitelist'])) {
            self::$config['code_coverage']['whitelist'] = [];
        }
        self::$config['extensions'][] = 'PhpSpec\Extension\CodeCoverageExtension';
        self::$config['code_coverage']['format'] = ['text', 'php'];
        self::$config['code_coverage']['output'] = [
            'php'   => self::$container->getCacheDirectory() . '/coverage.humbug.php',
            'text'   => self::$container->getCacheDirectory() . '/coverage.humbug.txt'
        ];

        self::$config['code_coverage']['whitelist'] = [];

        $source = self::$container->getSourceList();
        if (isset($source->directories)) {
            foreach ($source->directories as $d) {
                self::$config['code_coverage']['whitelist'][] = realpath($d);
            }
        }

        if (isset($source->excludes)) {
            if (!isset(self::$config['code_coverage']['blacklist'])
            || !is_array(self::$config['code_coverage']['blacklist'])) {
                self::$config['code_coverage']['blacklist'] = [];
            }
            foreach ($source->excludes as $d) {
                self::$config['code_coverage']['blacklist'][] = realpath($d);
            }
        }
    }

    protected static function parseConfigurationFile()
    {
        $paths = array('phpspec.yml','phpspec.yml.dist');

        $config = array();
        foreach ($paths as $path) {
            if ($path && file_exists($path) && $parsedConfig = Yaml::parse(file_get_contents($path))) {
                $config = $parsedConfig;
                break;
            }
        }

        if ($homeFolder = getenv('HOME')) {
            $localPath = $homeFolder.'/.phpspec.yml';
            if (file_exists($localPath) && $parsedConfig = Yaml::parse(file_get_contents($localPath))) {
                $config = array_replace_recursive($parsedConfig, $config);
            }
        }

        return $config;
    }
}
