<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\Uxon\BehaviorSchema;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractBehavior implements BehaviorInterface
{
    use ImportUxonObjectTrait;
    use AliasTrait;
    
    private $object = null;
    
    private $selector = null;

    private $behavior = null;

    private $disabled = false;

    private $registered = false;

    private $name_resolver = false;
    
    private $appSelectorOrString = null;
    
    private $priority = null;
    
    /**
     * 
     * @var BehaviorLogBook[]
     */
    private $logBooks = [];

    public function __construct(BehaviorSelectorInterface $selector, MetaObjectInterface $object = null, string $appSelectorOrString = null)
    {
        $this->object = $object;
        $this->selector = $selector;
        $this->appSelectorOrString = $appSelectorOrString;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::getSelector()
     */
    public function getSelector() : BehaviorSelectorInterface
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::getObject()
     */
    public function getObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::setObject()
     */
    public function setObject(MetaObjectInterface $object) : BehaviorInterface
    {
        $this->object = $object;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     * @return WorkbenchInterface
     */
    public function getWorkbench()
    {
        return $this->getObject()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('disabled', $this->isDisabled());
        return $uxon;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return BehaviorSchema::class;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::activate()
     */
    public function register() : BehaviorInterface
    {
        $this->registerEventListeners();
        $this->setRegistered(true);
        return $this;
    }
    
    protected function registerEventListeners() : BehaviorInterface
    {
        return $this;
    }
    
    protected function unregisterEventListeners() : BehaviorInterface
    {
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }

    /**
     * Set to TRUE to disabled this behavior
     * 
     * @param bool $value
     * @return BehaviorInterface
     */
    public function setDisabled(bool $value) : BehaviorInterface
    {
        if ($value === true) {
            $this->disable();
        } else {
            $this->enable();
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::disable()
     */
    public function disable() : BehaviorInterface
    {
        if ($this->disabled === true) {
            return $this;
        }
        try {
            $this->unregisterEventListeners();
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException(new BehaviorRuntimeError($this, 'Cannot disable behavior: ' . $e->getMessage(), null, $e));
        }
        $this->disabled = true;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::enable()
     */
    public function enable() : BehaviorInterface
    {
        if ($this->disabled === false) {
            return $this;
        }
        
        if (! $this->isRegistered()) {
            $this->register();
        } else {
            $this->registerEventListeners();
        }
        $this->disabled = false;
        return $this;
    }

    /**
     * Marks the behavior as registered.
     * is_registered() will now return true. This is a helper method for
     * the case, if you don't want to override the is_registered() method: just call set_registered() in
     * your register() implementation!
     *
     * @param boolean $value            
     * @return BehaviorListInterface
     */
    protected function setRegistered(bool $value) : BehaviorInterface
    {
        $this->registered = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\BehaviorInterface::isRegistered()
     */
    public function isRegistered() : bool
    {
        return $this->registered;
    }

    /**
     * Returns a copy of the Behavior without
     *
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     * @return BehaviorInterface
     */
    public function copy() : self
    {
        return clone $this;
    }
    
    public function getApp() : AppInterface
    {
        if ($this->appSelectorOrString === null) {
            return $this->getObject()->getApp();
        }
        return $this->getWorkbench()->getApp($this->appSelectorOrString);
    }
    
    /**
     * 
     * @param AppSelectorInterface|string $selectorOrString
     * @return BehaviorInterface
     */
    public function setAppSelector($selectorOrString) : BehaviorInterface
    {
        $this->appSelectorOrString = $selectorOrString;
    }
    
    /**
     *
     * @return int|NULL
     */
    public function getPriority() : ?int
    {
        return $this->priority;
    }
    
    /**
     * Behaviors with higher priority will be executed first if mutiple behaviors of an object are registered for the same event.
     *
     * @param int $value
     * @return BehaviorInterface
     */
    public function setPriority(int $value) : BehaviorInterface
    {
        $this->priority = $value;
        return $this;
    }
    
    /**
     *
     * @param EventInterface $event
     * @return BehaviorLogBook
     */
    protected function getLogBook(EventInterface $event) : BehaviorLogBook
    {
        foreach ($this->logBooks as $lb) {
            if ($lb->getEvent() === $event) {
                return $lb;
            }
        }
        $lb = new BehaviorLogBook('Behavior', $this, $event);
        $this->logBooks[] = $lb;
        return $lb;
    }
}