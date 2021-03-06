<?php

namespace DeltaCli\Config\Detector;

use DeltaCli\Config\Config;
use DeltaCli\Config\Database\DatabaseFactory;
use DeltaCli\Environment;

class WebsiteInfo implements DetectorInterface
{
    public function getMostLikelyRemoteFilePath()
    {
        return '.website_info';
    }

    public function getName()
    {
        return 'website-info';
    }

    public function getPotentialFilePaths()
    {
        return [];
    }

    public function createConfigFromFile(Environment $environment, $configFile)
    {
        $data   = parse_ini_file($configFile);
        $config = new Config();

        if (isset($data['website_url']) && $data['website_url']) {
            $config->setBrowserUrl($data['website_url']);
        }

        if ($this->databaseIsPresent('mysql', $data)) {
            $config->addDatabase(
                DatabaseFactory::createInstance(
                    'mysql',
                    $data['mysql_name'],
                    $data['mysql_user'],
                    $data['mysql_pass'],
                    $data['mysql_host']
                )
            );
        }

        if ($this->databaseIsPresent('pg', $data)) {
            $config->addDatabase(
                DatabaseFactory::createInstance(
                    'postgres',
                    $data['pg_name'],
                    $data['pg_user'],
                    $data['pg_pass'],
                    $data['pg_host']
                )
            );
        }

        return $config;
    }

    private function databaseIsPresent($prefix, array $data)
    {
        $params = ['user', 'pass', 'name', 'host'];

        foreach ($params as $suffix) {
            $param = "{$prefix}_{$suffix}";

            if (!isset($data[$param]) || !$data[$param]) {
                return false;
            }
        }

        return true;
    }
}
