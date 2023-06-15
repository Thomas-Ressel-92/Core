<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\PWA\PWADatasetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Behaviors\TimeStampingBehavior;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\ImageUrlDataType;

class PWADataset implements PWADatasetInterface
{
    use ImportUxonObjectTrait;
    
    private $pwa = null;
    
    private $dataSheet = null;
    
    private $actions =  [];
    
    private $uid = null;

    private $currentIncrementValue = null;
    
    private $lastIncrementValue = null;
    
    /**
     * 
     * @param PWAInterface $pwa
     * @param DataSheetInterface $dataSheet
     * @param string $uid
     */
    public function __construct(PWAInterface $pwa, DataSheetInterface $dataSheet, string $uid = null)
    {
        $this->pwa = $pwa;
        $this->dataSheet = $dataSheet;
        $this->uid = $uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWARouteInterface::getPWA()
     */
    public function getPWA(): PWAInterface
    {
        return $this->pwa;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getDataSheet()
     */
    public function getDataSheet(): DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::canInclude()
     */
    public function canInclude(DataSheetInterface $dataSheet) : bool
    {
        if (! $this->getMetaObject()->isExactly($dataSheet->getMetaObject())) {
            return false;
        }
        $thisSheet = $this->getDataSheet();
        if ($thisSheet->hasAggregations() && $dataSheet->hasAggregations()) {
            foreach ($dataSheet->hasAggregations() as $a => $aggr) {
                if ($thisSheet->getAggregations()->get($a)->getAttributeAlias() !== $aggr->getAttributeAlias()) {
                    return false;
                }
            }
            return true;
        }
        if ($thisSheet->hasAggregateAll() !== $dataSheet->hasAggregateAll()) {
            return false;
        }
        // TODO compare filters too!!!
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::includeData()
     */
    public function includeData(DataSheetInterface $anotherSheet) : PWADatasetInterface
    {
        if (! $this->getDataSheet()->getMetaObject()->isExactly($anotherSheet->getMetaObject())) {
            throw new RuntimeException('Cannot include data in offline data set: object mismatch!');
        }
        
        $setSheet = $this->getDataSheet();
        $setCols = $setSheet->getColumns();
        foreach ($anotherSheet->getColumns() as $col) {
            if (! $setCols->getByExpression($col->getExpressionObj())) {
                $setCols->addFromExpression($col->getExpressionObj());
            }
        }
        foreach ($anotherSheet->getFilters()->getConditionsRecursive() as $cond) {
            $setSheet->getColumns()->addFromExpression($cond->getExpression());
        }
        
        return $this;
    }

    public function getMetaObject(): MetaObjectInterface
    {
        return $this->dataSheet->getMetaObject();
    }
    
    public function addAction(ActionInterface $action) : PWADatasetInterface
    {
        $this->actions[] = $action;
        return $this;
    }
    
    /**
     * 
     * @return ActionInterface[]
     */
    public function getActions() : array
    {
        return $this->actions;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getUid()
     */
    public function getUid() : ?string
    {
        return $this->uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::setUid()
     */
    public function setUid(string $uid) : PWADatasetInterface
    {
        $this->uid = $uid;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::estimateRows()
     */
    public function estimateRows() : ?int
    {
        return $this->getDataSheet()->copy()->setAutoCount(true)->countRowsInDataSource();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::readData()
     */
    public function readData(int $limit = null, int $offset = null, string $incrementValue = null) : DataSheetInterface
    {
        $ds = $this->getDataSheet()->copy();
        $this->currentIncrementValue = $this->getIncrementValueCurrent();
        $incrementValue = DateTimeDataType::now();
        
        if ($incrementValue !== null) {
            // find incrementAttribute of DataSheet
            if(null !== $incrementAttr = $this->getIncrementAttribute()) {
                // filter read data by incrementAttribute of DataSheet
                $group = ConditionGroupFactory::createOR($ds->getMetaObject());
                $group->addConditionFromAttribute($incrementAttr, $incrementValue, ComparatorDataType::GREATER_THAN_OR_EQUALS);
            }
                foreach ($ds->getColumns() as $column) {
                    $relationsArray = [];
                    foreach ($column->getExpressionObj()->getRequiredAttributes() as $attrAlias) {
                        $attr = $ds->getMetaObject()->getAttribute($attrAlias);
                        if($attr->isRelated()) {
                            $attrObject = $attr->getObject();
                            // find incrementAttribute of end object of relation
                            // e.g. from Artikelstamm of LagerQuant__Artikelstamm 
                            if($this->findIncrementAttribute($attrObject) !== null) {
                                // usually ZeitAend
                                $incrementRelationAttribute = $this->findIncrementAttribute($attrObject);
                                $alias = $attrObject->getAlias();
                                $relationsArray[$attrObject->getAlias()] = $incrementRelationAttribute->getAlias();
                                // filter read data by incrementAttribute of end object of relation
                                $group->addConditionFromAttribute($incrementRelationAttribute, $incrementValue, ComparatorDataType::GREATER_THAN_OR_EQUALS);
                            }
                        }

                        //find incrementAttribute of every related object left from the end object
                        $relations = $attr->getRelationPath()->getRelations();
                        $processedArray = [];
                        
                        foreach($relations as $relation) {
                            
                            $leftAlias = $relation->getLeftObject()->getAlias();
                            $rightAlias = $relation->getRightObject()->getAlias();
                            
                            if(! array_key_exists($leftAlias, $processedArray)) {
                                $leftObject = $relation->getLeftObject();
                                $leftIncrementRelationAttribute = $this->findIncrementAttribute($leftObject)->getAlias();
                                $relationsArray[$leftAlias] = $leftIncrementRelationAttribute;
                                $group->addConditionFromAttribute($leftIncrementRelationAttribute, $incrementValue, ComparatorDataType::GREATER_THAN_OR_EQUALS);
                                array_push($processedArray, $leftAlias);
                            }
                            
                            if($relation->getAlias() === $relation->getrightObject()->getAlias()) {
                                if(! array_key_exists($rightAlias, $processedArray)) {
                                    $rightObject = $relation->getLeftObject();
                                    $rightIncrementRelationAttribute = $this->findIncrementAttribute($rightObject)->getAlias();
                                    $relationsArray[$rightAlias] = $rightIncrementRelationAttribute;
                                    $group->addConditionFromAttribute($rightIncrementRelationAttribute, $incrementValue, ComparatorDataType::GREATER_THAN_OR_EQUALS);
                                    array_push($processedArray, $rightAlias);
                                }
                            }
                        }  
                        $relationsArray = $relationsArray;
                    }
                }
            }
            $ds->getFilters()->addNestedGroup($group);
            $ds->dataRead($limit, $offset);
        return $ds;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getIncrementValueCurrent() : ?string
    {
        if($this->getIncrementAttribute() === null) {
            return null;
        }
        
        $type = $this->getIncrementAttribute()->getDataType();
        switch (true) {
            case $type instanceof DateDataType:
                $currentIncrementValue = $type::now();
                break;
            case $type instanceof TimestampDataType:
                $currentIncrementValue = $type::now();
                break;
            default: 
                // TODO DataSheet bauen, um den aktuellen Maximalwert von dem Incr-Attribut (ID) zu bestimmen.
        }
        return $currentIncrementValue;
    }
    
    /**
     * 
     * @return string
     */
    public function getIncrementValueOfLastRead() : ?string
    {
        return $this->currentIncrementValue;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::isIncremental()
     */
    public function isIncremental() : bool
    {
        return $this->getIncrementAttribute() !== null;
    }
    
    /**
     * 
     * @param MetaObjectInterface $obj
     * @return MetaAttributeInterface|NULL
     */
    protected function findIncrementAttribute(MetaObjectInterface $obj) : ?MetaAttributeInterface
    {
        $tsBehavior = $obj->getBehaviors()->getByPrototypeClass(TimeStampingBehavior::class)->getFirst();
        if ($tsBehavior === null) {
            return null;
        }
        return $tsBehavior->getUpdatedOnAttribute();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getIncrementAttribute()
     */
    public function getIncrementAttribute() : ?MetaAttributeInterface
    {
        $obj = $this->getMetaObject();
        return $this->findIncrementAttribute($obj);
    }
    
    /**
     *
     * @return array
     */
    public function getBinaryDataTypeColumnNames() : array
    {
        // TODO How to get download urls for binary columns?
        return [];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWADatasetInterface::getImageUrlDataTypeColumnNames()
     */
    public function getImageUrlDataTypeColumnNames() : array
    {
        $columnsArray = [];
        $columns = $this->getDataSheet()->getColumns();
        foreach ($columns as $column) {
            $columnDataType = $column->getDataType();
            if($columnDataType !== null && $columnDataType instanceof ImageUrlDataType) {
                array_push($columnsArray, $column->getName());
            }
        }
        return $columnsArray;
    }
}