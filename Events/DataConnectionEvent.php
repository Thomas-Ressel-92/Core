<?php

namespace exface\Core\Events;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\NameResolver;

/**
 * Action sheet event names consist of the alias of the connector followed by "DataConnection" and the respective event type:
 * e.g.
 * exface.sqlDataConnector.DataConnectors.MySQL.DataConnection.Before, etc.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataConnectionEvent extends ExfaceEvent
{

    private $data_connection = null;

    private $current_query = null;

    /**
     *
     * @return DataConnectionInterface
     */
    public function getDataConnection()
    {
        return $this->data_connection;
    }

    /**
     *
     * @param DataConnectionInterface $connection            
     */
    public function setDataConnection(DataConnectionInterface $connection)
    {
        $this->data_connection = $connection;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getCurrentQuery()
    {
        return $this->current_query;
    }

    /**
     *
     * @param string $value            
     */
    public function setCurrentQuery($value)
    {
        $this->current_query = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Events\ExfaceEvent::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getDataConnection()->getAliasWithNamespace() . NameResolver::NAMESPACE_SEPARATOR . 'DataConnection';
    }
}