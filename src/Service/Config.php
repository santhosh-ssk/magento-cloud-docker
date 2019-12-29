<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CloudDocker\Service;

use Magento\CloudDocker\Config\Application\Reader;
use Illuminate\Contracts\Config\Repository;
use Magento\CloudDocker\App\ConfigurationMismatchException;
use Magento\CloudDocker\Filesystem\FilesystemException;

/**
 * Retrieve Service versions/configs from Cloud configuration.
 */
class Config
{
    /**
     * List of services which can be configured in Cloud docker
     *
     * @var array
     */
    private static $configurableServices = [
        ServiceInterface::NAME_PHP => 'php',
        ServiceInterface::NAME_DB => 'mysql',
        ServiceInterface::NAME_NGINX => 'nginx',
        ServiceInterface::NAME_REDIS => 'redis',
        ServiceInterface::NAME_ELASTICSEARCH => 'elasticsearch',
        ServiceInterface::NAME_RABBITMQ => 'rabbitmq',
        ServiceInterface::NAME_NODE => 'node'
    ];

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @param Reader $reader
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Retrieves service versions set in configuration files.
     * Returns null if neither of services is configured or provided in $customVersions.
     *
     * Example of return:
     *
     * ```php
     *  [
     *      'elasticsearch' => '5.6',
     *      'db' => '10.0'
     *  ];
     * ```
     *
     * @param Repository $customVersions custom version which overwrite values from configuration files
     * @return array List of services
     * @throws ConfigurationMismatchException
     */
    public function getAllServiceVersions(Repository $customVersions): array
    {
        $configuredVersions = [];

        foreach (self::$configurableServices as $serviceName) {
            $version = $customVersions->get($serviceName) ?: $this->getServiceVersion($serviceName);
            if ($version) {
                $configuredVersions[$serviceName] = $version;
            }
        }

        return $configuredVersions;
    }

    /**
     * Retrieves service version set in configuration files.
     * Returns null if service was not configured.
     *
     * @param string $serviceName Name of service version need to retrieve
     * @return string|null
     * @throws ConfigurationMismatchException
     */
    public function getServiceVersion(string $serviceName, \Illuminate\Config\Repository $configInput = null)
    {
        try {
            if (!is_null($configInput) && $configInput->offsetExists($serviceName)) {
                $version = $configInput->get($serviceName);
            } else {                
                $version = $serviceName === ServiceInterface::NAME_PHP
                    ? $this->getPhpVersion($configInput)
                    : $this->reader->read()['services'][$serviceName]['version'] ?? null;                
            }
            return $version;
        } catch (FilesystemException $exception) {            
            if (strpos($exception->getMessage(),'File does not exist at path') === false) {
                throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
            } else {
                if ($serviceName === ServiceInterface::NAME_RABBITMQ) {
                    return null;
                } else {
                    $message = 'Required version number of ' . $serviceName . ' service wasn\'t found anywhere (both cmd line and cloud-yaml files).';
                    throw new ConfigurationMismatchException($message, $exception->getCode());
                }
            }
        }
    }

    /**
     * Retrieve version of PHP
     *
     * @return string
     * @throws ConfigurationMismatchException when PHP is not configured
     */
    public function getPhpVersion(\Illuminate\Config\Repository $configInput = null): string
    {
        try {
            if (!is_null($configInput) && $configInput->offsetExists($serviceName)) {
                $version = $configInput->get($serviceName);
            }
            $config = $this->reader->read();
            list($type, $version) = explode(':', $config['type']);
        } catch (FilesystemException $exception) {
            if (strpos($exception->getMessage(),'File does not exist at path') === false) {
                throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
            } else {
                $message = 'Required version number of php service wasn\'t found anywhere (both cmd line and cloud-yaml files).';
                throw new ConfigurationMismatchException($message, $exception->getCode());
            }
        }

        if ($type !== ServiceInterface::NAME_PHP) {
            throw new ConfigurationMismatchException(sprintf(
                'Type "%s" is not supported',
                $type
            ));
        }

        /**
         * We don't support release candidates.
         */
        return rtrim($version, '-rc');
    }

    /**
     * Retrieves cron configuration.
     *
     * @return array
     * @throws ConfigurationMismatchException
     */
    public function getCron(): array
    {
        $default = [];
        try {
            return $this->reader->read()['crons'] ?? [];
        } catch (FilesystemException $exception) {
            if (strpos($exception->getMessage(),'File does not exist at path') === false) {
                throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
            } else {
                return $default;
            }
        }
    }

    /**
     * @return array
     * @throws ConfigurationMismatchException
     */
    public function getEnabledPhpExtensions(): array
    {        
        $default = [
            'redis',
            'xsl',
            'json',
            'blackfire',
            'newrelic',
            'sodium'
        ];
        try {
            if (array_key_exists('runtime', $this->reader->read())) {
                return $this->reader->read()['runtime']['extensions'];
            } else {                
                return $default;
            }
        } catch (FilesystemException $exception) {
            if (strpos($exception->getMessage(),'File does not exist at path') === false) {
                throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
            } else {
                return $default;
            }
        }
    }

    /**
     * @return array
     * @throws ConfigurationMismatchException
     */
    public function getDisabledPhpExtensions(): array
    {
        $default = [];        
        try {
            if (array_key_exists('runtime', $this->reader->read())) {
                return $this->reader->read()['runtime']['disabled_extensions'];
            } else {
                return [];
            }
        } catch (FilesystemException $exception) {
           if (strpos($exception->getMessage(),'File does not exist at path') === false) {
                throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
            } else {
                return $default;
            }
        }
    }
}
