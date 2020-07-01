<?php

namespace Icinga\Module\Businessprocess\Common;

use Icinga\Application\Config as AppConfig;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use ipl\Sql\Config as SqlConfig;
use ipl\Sql\Connection;
use PDO;

trait IcingadbDatabase
{
    /** @var Connection Connection to the Icinga database */
    private $db;

    /**
     * Get the connection to the Icinga database
     *
     * @return Connection
     *
     * @throws ConfigurationError If the related resource configuration does not exist
     */
    public function getDb()
    {
        if ($this->db === null) {
            $config = new SqlConfig(ResourceFactory::getResourceConfig(
                AppConfig::module('icingadb')->get('icingadb', 'resource', 'icingadb')
            ));

            $config->options = [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION SQL_MODE='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE"
                    . ",ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'"
            ];

            $this->db = new Connection($config);
        }

        return $this->db;
    }
}
