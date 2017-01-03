<?php

namespace DeltaCli\Config\Database\TypeHandler;

use PDO;

interface TypeHandlerInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $username
     * @param string $password
     * @param string $hostname
     * @param string $databaseName
     * @param integer $port
     * @return PDO
     */
    public function createPdoConnection($username, $password, $hostname, $databaseName, $port);

    /**
     * @param string $username
     * @param string $password
     * @param string $hostname
     * @param string $databaseName
     * @param integer $port
     * @return string
     */
    public function getShellCommand($username, $password, $hostname, $databaseName, $port);

    /**
     * @param string $hostname
     * @param integer $port
     * @return string
     */
    public function getDumpCommand($username, $password, $hostname, $databaseName, $port);

    /**
     * @return integer
     */
    public function getDefaultPort();

    /**
     * @return void
     */
    public function emptyDb(PDO $pdo);
}
