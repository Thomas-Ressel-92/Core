<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\DataSheet\AbstractDataSheetEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReplaceDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnReplaceDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Makes a child-object affect its parent whenever it is changed
 * 
 * For example, if we have an `ORDER` object and multiple `ORDER_POS` for that order. Changing the `QTY`
 * of an order position obviously also changes the order. If the order has a TimeStampingBehavior, we
 * expect the last-change time to update when the quantity of a position is changed.
 * 
 * Technically this means an `OnUpdateData` event of order position is also an `OnUpdateData` for the
 * order. Other events may be mapped differently, depending on your configuration. The default mapping is as follows:
 * - On**Create**Data, On**Update**Data, On**Replace**Data and On**Delete**Data all trigger On**Update**Data in the
 * synchronized relations.
 * - On**BeforeCreate**Data, On**BeforeUpdate**Data, On**BeforeReplace**Data and On**BeforeDelete**Data all trigger
 * On**BeforeUpdate**Data in the synchronized relations.
 * 
 * 
 * ## Examples
 * 
 * ### Order positions change their order
 * 
 * The behavior is to be attached to `ORDER_POS`. Assuming this object has a relation called `ORDER`, the behavior
 * configuration would look like this:
 * 
 * ```
 * 
 * {
 *	    "changes_affect_relations": [
 *		    ORDER
 *	    ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 */
class SynchronizingBehavior extends AbstractBehavior
{
    private array $affectedRelations = [];
    
    private array $mapToOnUpdate = [];
    
    private array $mapToOnBeforeUpdate = [];
    
    private array $registeredEventListeners = [];
    
    private bool $inProgress = false;
    
    /**
     *
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $eventMapping = $this->getMapToOnBeforeUpdate();
        $eventMapping = array_merge($eventMapping, $this->getMapToOnUpdate());
        
        if (! empty($eventMapping)) {
            $eventMgr = $this->getWorkbench()->eventManager();
            foreach ($eventMapping as $fromEvent => $toEvent) {
                $eventMgr->addListener($fromEvent, [$this, 'onEventRelay'], $this->getPriority());
                $this->registeredEventListeners[$fromEvent] = $toEvent;
            }
        }

        return $this;
    }
    
    /**
     *
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $eventMgr = $this->getWorkbench()->eventManager();
        
        if (! empty($this->registeredEventListeners)) {
            foreach ($this->registeredEventListeners as $fromEvent => $toEvent) {
                $eventMgr->removeListener($fromEvent, [$this, 'onEventRelay']);
            }
        }
        
        $this->registeredEventListeners = [];
        return $this;
    }

    /**
     * Executes the action if applicable
     *
     * @param AbstractDataSheetEvent $event
     * @return void
     */
    public function onEventRelay(AbstractDataSheetEvent $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $inputSheet = $event->getDataSheet();
        // Ignore empty sheets - nothing to calculate here!
        if ($inputSheet->isEmpty()) {
            return;
        }
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $inputSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
		
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        
        $logbook->addDataSheet('Input data', $inputSheet);
        $logbook->addLine('Reacting to event `' . $event::getEventName() . '`');
        $logbook->addLine('Found input data for object ' . $inputSheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        
        $eventManager = $this->getWorkbench()->eventManager();
        $eventManager->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));

        // Ignore loops
        if ($this->inProgress === true) {
            $logbook->addLine('**Skipped** because of operation performed from within the same behavior (e.g. reading missing data)');
            $eventManager->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        $this->inProgress = true;
        
        foreach ($this->getAffectedRelationPaths() as $relationString) {
            $relPath = RelationPathFactory::createFromString($this->getObject(), $relationString);
            $targetObject = $relPath->getEndObject();
            $targetSheet = DataSheetFactory::createFromObject($targetObject);
            $targetSheet->getColumns()->addFromSystemAttributes();

            if(!$relUidCol = $inputSheet->getColumns()->getByExpression($relationString)) {
                $relSheet = $inputSheet->copy();
                $relSheet->getFilters()->addConditionFromColumnValues($relSheet->getUidColumn());
                $relUidCol = $relSheet->getColumns()->addFromExpression($relationString);
                $relSheet->dataRead();
            }

            $targetUids = $relUidCol->getValues();
            $targetSheet->getFilters()->addConditionFromValueArray($targetSheet->getUidColumn()->getExpressionObj(), $targetUids);
            $targetSheet->dataRead();

            $eventType = $this->getActiveEventMapping()[strtolower($event::getEventName())];
            if($eventType === null) {
                throw new BehaviorConfigurationError($this, "Could not relay update to ".$targetObject.", because no valid event mapping was found for ".get_class($event)."!");
            }
            
            if(!is_a($eventType, AbstractDataSheetEvent::class, true)) {
                $eventType = $this->tryGetClassFromEventName($eventType);
            }
            
            $eventManager->dispatch(new $eventType($targetSheet, $event->getTransaction()));
        }

        $this->inProgress = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Relations to the objects, that will be affected by changes on this one
     * 
     * For example, for an `ORDER_POSITION` object
     * 
     * - `ORDER`
     * - `ORDER__CUSTOMER__CUSTOMER_STATS`
     * 
     * @uxon-property changes_affect_relations
     * @uxon-type metamodel:relation[]
     * @uxon-template [""]
     * 
     * @return \exface\Core\Behaviors\SynchronizingBehavior
     */
    public function setChangesAffectRelations(UxonObject $arrayOfRelationPaths) : SynchronizingBehavior
    {
        $this->affectedRelations = $arrayOfRelationPaths->toArray();

        /* IDEA mark related attributes as system to check ORDER timestamp when saving ORDER_POS.
         * Will probably not work yet because system attributes are only direct ones.
         * Do this LATER!
        foreach ($this->relations as $relationString) {
            $relPath = RelationPathFactory::createFromString($this->getObject(), $relationString);
            $relObj = $relPath->getEndObject();
            foreach ($relObj->getAttributes()->getSystem() as $systemAttr) {
                // If ORDER has system attribute MODIFIED_ON, give ORDER_POS the system attibute ORDER__MODIFIED_ON
                $this->getObject()->getAttribute(RelationPath::relationPathAdd($relationString, $systemAttr->getAlias()))->setSystem();
            }
            $this->getObject()->getRelation($relKey)->getRightObject();

        }
        */
        
        return $this;
    }


    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject() : UxonObject
    {
        // TODO
        return parent::exportUxonObject();
    }

    /**
     * 
     * @return string[]
     */
    protected function getAffectedRelationPaths() : array
    {
        return $this->affectedRelations;
    }

    /**
     * @return array|string[]
     */
    public function getActiveEventMapping() : array
    {
        return $this->registeredEventListeners;
    }

    /**
     * NOTE: This property is a stub and has no effect!
     * Overwrite all entries in the default event mapping with a custom event mapping.
     *
     * Since setting this property disables all events not explicitly defined in the
     * new mapping, it is especially useful if you want to exclude certain events. For example:
     *
     * ```
     *
     * "overwrite_event_mapping" :
     * {
     *      "OnCreateDataEvent" : "OnUpdateDataEvent"
     * }
     *
     * ```
     *
     * will only trigger on `OnCreateDataEvent` and will relay that trigger to the `OnUpdateDataEvent` of
     * all affected relations.
     *
     * @uxon-property overwrite_event_mapping
     * @uxon-type object
     * @uxon-template {"OnCreateDataEvent":"OnUpdateDataEvent","OnBeforeCreateDataEvent":"OnBeforeCreateDataEvent","OnUpdateDataEvent":"OnUpdateDataEvent","OnBeforeUpdateDataEvent":"OnBeforeCreateDataEvent","OnReplaceDataEvent":"OnUpdateDataEvent","OnBeforeReplaceDataEvent":"OnBeforeUpdateDataEvent","OnDeleteDataEvent":"OnUpdateDataEvent","OnBeforeDeleteDataEvent":"OnBeforeCreateDataEvent"}
     *
     * @param UxonObject $eventMapping
     * @return $this
     */
    public function setOverwriteEventMapping(UxonObject $eventMapping) : static
    {
        // TODO: Implement actual event mapping.
        //$this->eventMapping = $eventMapping->toArray();
        return $this;
    }

    /**
     * NOTE: This property is a stub and has no effect!
     * Modify the current event mapping with custom event mappings.
     *
     * Any default mappings not explicitly mentioned in this property will remain unchanged.
     * Existing keys will be overwritten, while new keys will be appended. For example:
     *
     * ```
     *
     * "modify_event_mapping" :
     * {
     *      "OnCreateDataEvent" : "OnCreateDataEvent", // This overwrites the default mapping.
     *      "MyOwnDataEvent" : "OnCreateDataEvent" // This appends a new mapping.
     * }
     *
     * ```
     *
     * @uxon-property modify_event_mapping
     * @uxon-type object
     * @uxon-template {"OnCreateDataEvent":"OnUpdateDataEvent","OnBeforeCreateDataEvent":"OnBeforeCreateDataEvent","OnUpdateDataEvent":"OnUpdateDataEvent","OnBeforeUpdateDataEvent":"OnBeforeCreateDataEvent","OnReplaceDataEvent":"OnUpdateDataEvent","OnBeforeReplaceDataEvent":"OnBeforeUpdateDataEvent","OnDeleteDataEvent":"OnUpdateDataEvent","OnBeforeDeleteDataEvent":"OnBeforeCreateDataEvent"}
     *
     * @param UxonObject $eventMappingModifications
     * @return $this
     */
    public function setModifyEventMapping(UxonObject $eventMappingModifications) : static
    {
        // TODO: Implement actual event mapping.
        //$this->eventMappingModifications = $eventMappingModifications->toArray();
        return $this;
    }

    /**
     * Maps all events in this collection to `OnUpdateData`.
     *
     * Whenever they are triggered, this behavior will trigger the `OnUpdateData` event for all
     * the target objects of its synchronized relations.
     *
     * @uxon-property map_to_on_update
     * @uxon-type metamodel:event[]
     *
     * @param UxonObject $mapToOnUpdate
     * @return $this
     */
    public function setMapToOnUpdate(UxonObject $mapToOnUpdate) : static
    {
        $onUpdate = OnUpdateDataEvent::class;
        foreach ($mapToOnUpdate->toArray() as $eventName) {
            $this->mapToOnUpdate[strtolower($eventName)] = $onUpdate;
        }
        
        return $this;
    }

    /**
     * @return array
     */
    public function getMapToOnUpdate() : array
    {
        return !empty($this->mapToOnUpdate) ? $this->mapToOnUpdate : [
            strtolower(OnCreateDataEvent::getEventName()) => OnUpdateDataEvent::class,
            strtolower(OnUpdateDataEvent::getEventName()) => OnUpdateDataEvent::class,
            strtolower(OnReplaceDataEvent::getEventName()) => OnUpdateDataEvent::class,
            strtolower(OnDeleteDataEvent::getEventName()) => OnUpdateDataEvent::class,
        ];
    }

    /**
     * Maps all events in this collection to `OnBeforeUpdateData`.
     *
     * Whenever they are triggered, this behavior will trigger the `OnBeforeUpdateData` event for all
     * the target objects of its synchronized relations.
     *
     * @uxon-property map_to_on_before_update
     * @uxon-type metamodel:event[]
     *
     * @param UxonObject $mapToOnBeforeUpdate
     * @return $this
     */
    public function setMapToOnBeforeUpdate(UxonObject $mapToOnBeforeUpdate) : static
    {
        $onBeforeUpdate = OnBeforeUpdateDataEvent::class;
        foreach ($mapToOnBeforeUpdate->toArray() as $eventName) {
            $this->mapToOnBeforeUpdate[strtolower($eventName)] = $onBeforeUpdate;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getMapToOnBeforeUpdate() : array
    {
        return !empty($this->mapToOnBeforeUpdate) ? $this->mapToOnBeforeUpdate : [
            strtolower(OnBeforeCreateDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class,
            strtolower(OnBeforeUpdateDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class,
            strtolower(OnBeforeReplaceDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class,
            strtolower(OnBeforeDeleteDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class
        ];
    }
    
    private function tryGetClassFromEventName(string $eventName) : string
    {
        $eventName = str_replace('.', '\\', $eventName);
        $before = StringDataType::substringBefore($eventName, '\\DataSheet');
        $result = $before.'\\Events'.StringDataType::substringAfter($eventName, '\\Core').'Event';
        
        if(is_a($result, AbstractDataSheetEvent::class, true)) {
            return $result;
        }
        
        throw new BehaviorConfigurationError($this, 'Could not resolve '.$eventName.' to a valid class name!');
    }
}