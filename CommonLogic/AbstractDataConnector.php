<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionConfigurationError;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Exceptions\ModelBuilders\ModelBuilderNotAvailableError;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Events\DataConnection\OnBeforeConnectEvent;
use exface\Core\Events\DataConnection\OnConnectEvent;
use exface\Core\Events\DataConnection\OnBeforeDisconnectEvent;
use exface\Core\Events\DataConnection\OnDisconnectEvent;
use exface\Core\Events\DataConnection\OnBeforeQueryEvent;
use exface\Core\Events\DataConnection\OnQueryEvent;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Traits\MetaModelPrototypeTrait;
use exface\Core\Uxon\ConnectionSchema;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\RuntimeException;

abstract class AbstractDataConnector implements DataConnectionInterface
{
    use ImportUxonObjectTrait {
		importUxonObject as importUxonObjectDefault;
	}
	use MetaModelPrototypeTrait;

    private $config_array = array();

    private $exface = null;
    
    private $prototypeSelector = null;
    
    private $id = null;
    
    private $alias = null;
    
    private $alias_namespace = null;
    
    private $name = '';
    
    private $connected = false;
    
    private $readonly = false;
    
    /**
     *
     * @deprecated Use DataConnectionFactory instead!
     */
    public function __construct(DataConnectorSelectorInterface $prototypeSelector, UxonObject $config = null)
    {
        $this->exface = $prototypeSelector->getWorkbench();
        $this->prototypeSelector = $prototypeSelector;
        if ($config !== null) {
            $this->importUxonObject($config);
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setAlias()
     */
    public function setAlias(string $alias, string $namespace = null) : DataConnectionInterface
    {
        $this->alias = $alias;
        $this->alias_namespace = $namespace;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->alias_namespace ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getId()
     */
    public function getId() : ?string
    {
        return $this->id;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setId()
     */
    public function setId(string $uid) : DataConnectionInterface
    {
        $this->id = $uid;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getName()
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setName()
     */
    public function setName(string $string) : DataConnectionInterface
    {
        $this->name = $string;
        return $this;
    }
    
    public function hasModel() : bool
    {
        return $this->id !== null;
    }
    
    /**
     *
     * @return string
     */
    protected function getClassnameSuffixToStripFromAlias() : string
    {
        return '';
    }
    
    public function getSelector() : ?DataConnectionSelectorInterface
    {
        return $this->selector;
    }
    
    public function getPrototypeSelector() : DataConnectorSelectorInterface
    {
        return $this->getPrototypeSelector();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        try {
            return $this->importUxonObjectDefault($uxon);
        } catch (UxonMapError $e) {
            throw new DataConnectionConfigurationError($this, 'Invalid data connection configuration: ' . $e->getMessage(), '6T4F41P', $e);
        }
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::connect()
     */
    public final function connect()
    {
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeConnectEvent($this));
        $result = $this->performConnect();
        $this->connected = true;
        $this->getWorkbench()->eventManager()->dispatch(new OnConnectEvent($this));
        return $result;
    }

    protected abstract function performConnect();
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::isConnected()
     */
    public function isConnected() : bool
    {
        return $this->connected;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::disconnect()
     */
    public final function disconnect()
    {
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeDisconnectEvent($this));
        $result = $this->performDisconnect();
        $this->connected = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnDisconnectEvent($this));
        return $result;
    }

    protected abstract function performDisconnect();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::query()
     */
    public final function query(DataQueryInterface $query) : DataQueryInterface
    {
        if ($this->isConnected() === false) {
            $this->connect();
        }
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeQueryEvent($this, $query));
        $result = $this->performQuery($query);
        $this->getWorkbench()->eventManager()->dispatch(new OnQueryEvent($this, $query));
        return $result;
    }

    protected abstract function performQuery(DataQueryInterface $query);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionStart()
     */
    public abstract function transactionStart();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionCommit()
     */
    public abstract function transactionCommit();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionRollback()
     */
    public abstract function transactionRollback();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionIsStarted()
     */
    public abstract function transactionIsStarted();
    
    
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getModelBuilder()
     */
    public function getModelBuilder()
    {
        throw new ModelBuilderNotAvailableError('No model builder implemented for data connector ' . $this->getAliasWithNamespace() . '!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::isReadOnly()
     */
    public function isReadOnly() : bool
    {
        return $this->readonly;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setReadOnly()
     */
    public function setReadOnly(bool $trueOrFalse) : DataConnectionInterface
    {
        $this->readonly = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return ConnectionSchema::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token, bool $updateUserCredentials = true) : AuthenticationTokenInterface
    {
        try {
            $this->performConnect();
            return $token;
        } catch (DataConnectionFailedError $e) {
            throw new AuthenticationFailedError('Authentication failed!', null, $e);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        $container->addWidget(WidgetFactory::createFromUxonInParent($container, new UxonObject([
            'widget_type' => 'Message',
            'type' => 'info',
            'text' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.CONNECTIONS.AUTHENTICATION_NOT_SUPPORTED')
        ])));
        return $container;
    }
    
    /**
     * Updates the user-specific connector config in the users's credential set for this connection.
     * 
     * NOTE: this only works for authenticated users as anonymous users can't have credential sets!
     * 
     * @param UserInterface $user
     * @param UxonObject $uxon
     * 
     * @throws RuntimeException
     * 
     * @return AbstractDataConnector
     */
    protected function updateUserCredentials(UserInterface $user, UxonObject $uxon) : AbstractDataConnector
    {
        if ($user->isUserAnonymous() === true || $this->hasModel() === false || $uxon->isEmpty() === true) {
            return $this;
        }
        
        $credData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.DATA_CONNECTION_CREDENTIALS');
        $credData->getColumns()->addMultiple(['NAME', 'DATA_CONNECTION', 'DATA_CONNECTOR_CONFIG', 'PRIVATE']);
        $credData->addFilterFromString('USER_CREDENTIALS__USER', $user->getUid(), ComparatorDataType::EQUALS);
        $credData->addFilterFromString('DATA_CONNECTION', $this->getId());
        $credData->dataRead();
        
        switch ($credData->countRows()) {
            case 1:
                $oldUxon = UxonObject::fromJson($credData->getCellValue('DATA_CONNECTOR_CONFIG', 0));
                $newUxon = $oldUxon->extend($uxon);
                $credData->setCellValue('DATA_CONNECTOR_CONFIG', 0, $newUxon->toJson());
                break;
            case 0:
                $transaction = $this->getWorkbench()->data()->startTransaction();
                
                $credData->addRow([
                    'NAME' => $this->getName(),
                    'DATA_CONNECTOR_CONFIG' => $uxon->toJson(),
                    'DATA_CONNECTION' => $this->getId(),
                    'PRIVATE' => '1'
                ]);
                $credData->dataCreate(false, $transaction);
                
                $credUserData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_CREDENTIALS');
                $credUserData->addRow([
                    'USER' => $user->getId(),
                    'DATA_CONNECTION_CREDENTIALS' => $credData->getUidColumn()->getCellValue(0)
                ]);
                $credUserData->dataCreate(false, $transaction);
                
                $transaction->commit();
                
                break;
            default:
                throw new RuntimeException('Cannot save user credentials: multiple credential sets found for user "' . $user->getUsername() . '" and data connection "' . $this->getAliasWithNamespace() . '"!');
        }
        
        return $this;
    }
}