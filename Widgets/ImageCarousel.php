<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iCanUseProxyTemplate;
use exface\Core\Widgets\Traits\iCanUseProxyTemplateTrait;

/**
 * Shows an image carousel with images from data rows.
 * 
 * @author Andrej Kabachnik
 *
 */
class ImageCarousel extends DataCards implements iCanUseProxyTemplate
{
    use iCanUseProxyTemplateTrait;

    private $image_url_column_id = null;

    private $image_title_column_id = null;

    public function getImageUrlColumnId()
    {
        return $this->image_url_column_id;
    }

    public function setImageUrlColumnId($value)
    {
        $this->image_url_column_id = $value;
        return $this;
    }

    public function getImageTitleColumnId()
    {
        return $this->image_title_column_id;
    }

    public function setImageTitleColumnId($value)
    {
        $this->image_title_column_id = $value;
        return $this;
    }

    /**
     * Keine sinnvolle Funktion fuer ImageCarousel, gibt daher immer true zurueck.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataCards::getAutoloadData()
     */
    public function getAutoloadData()
    {
        return true;
    }
}
?>