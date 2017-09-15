<?php
namespace exface\Core\CommonLogic;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Factories\TemplateFactory;

class UiManager implements UiManagerInterface
{

    private $widget_id_forbidden_chars_regex = '[^A-Za-z0-9_\.]';

    private $loaded_templates = array();

    private $pagesById = [];

    private $pagesByIdCms = [];

    private $pagesByAlias = [];

    private $exface = null;

    private $base_template = null;

    private $page_alias_current = null;

    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Returns a template instance for a given template alias.
     * If no alias given, returns the current template.
     * 
     * @param string $template
     * @return AbstractTemplate
     */
    function getTemplate($template = null)
    {
        if (! $template)
            return $this->getTemplateFromRequest();
        
        if (! $instance = $this->loaded_templates[$template]) {
            $instance = TemplateFactory::createFromString($template, $this->exface);
            $this->loaded_templates[$template] = $instance;
        }
        
        return $instance;
    }

    /**
     * Output the final UI code for a given widget
     * IDEA Remove this method from the UI in favor of template::draw() after template handling has been moved to the actions
     * 
     * @param AbstractWidget $widget
     * @param TemplateInterface $template ui_template to use when drawing
     * @return string
     */
    function draw(WidgetInterface $widget, TemplateInterface $template = null)
    {
        if (is_null($template))
            $template = $this->getTemplateFromRequest();
        return $template->draw($widget);
    }

    /**
     * Output document headers, needed for the widget.
     * This could be JS-Includes, stylesheets - anything, that needs to be placed in the
     * resulting document separately from the renderen widget itself.
     * IDEA Remove this method from the UI in favor of template::drawHeaders() after template handling has been moved to the actions
     * 
     * @param WidgetInterface $widget
     * @param TemplateInterface $template ui_template to use when drawing
     * @return string
     */
    function drawHeaders(WidgetInterface $widget, TemplateInterface $template = null)
    {
        if (is_null($template))
            $template = $this->getTemplateFromRequest();
        return $template->drawHeaders($widget);
    }

    /**
     * Returns an ExFace widget from a given resource by id
     * Caching is used to store widgets from already loaded pages
     * 
     * @param string $widget_id
     * @param string $page_id_or_alias
     * @return WidgetInterface
     */
    function getWidget($widget_id, $page_id_or_alias)
    {
        $page = $this->getPage($page_id_or_alias);
        if (! is_null($widget_id)) {
            return $page->getWidget($widget_id);
        } else {
            return $page->getWidgetRoot();
        }
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns the UI page with the given $page_id_or_alias.
     * If the $page_id_or_alias is ommitted or ='', the default (initially empty) page is returned.
     * 
     * @param string $page_id_or_alias
     * @return UiPageInterface
     */
    public function getPage($page_id_or_alias = null)
    {
        if (! $page_id_or_alias) {
            if (! $this->pagesByAlias[null]) {
                $uiPage = UiPageFactory::createEmpty($this);
                $this->pagesById[null] = $uiPage;
                $this->pagesByIdCms[null] = $uiPage;
                $this->pagesByAlias[null] = $uiPage;
            }
            return $this->pagesByAlias[null];
        }
        
        if (substr($page_id_or_alias, 0, 2) == '0x') {
            if (! $this->pagesById[$page_id_or_alias]) {
                $this->addPageToCache($page_id_or_alias);
            }
            return $this->pagesById[$page_id_or_alias];
        } elseif (! is_numeric($page_id_or_alias)) {
            if (! $this->pagesByAlias[$page_id_or_alias]) {
                $this->addPageToCache($page_id_or_alias);
            }
            return $this->pagesByAlias[$page_id_or_alias];
        } else {
            if (! $this->pagesByIdCms[$page_id_or_alias]) {
                $this->addPageToCache($page_id_or_alias);
            }
            return $this->pagesByIdCms[$page_id_or_alias];
        }
    }
    
    /**
     * 
     * @param string $page_id_or_alias
     */
    protected function addPageToCache($page_id_or_alias)
    {
        $uiPage = UiPageFactory::createFromCmsPage($this, $page_id_or_alias);
        if ($uiPage->getId()) {
            $this->pagesById[$uiPage->getId()] = $uiPage;
        }
        if ($uiPage->getIdCms()) {
            $this->pagesByIdCms[$uiPage->getIdCms()] = $uiPage;
        }
        if ($uiPage->getAliasWithNamespace()) {
            $this->pagesByAlias[$uiPage->getAliasWithNamespace()] = $uiPage;
        }
        // Pruefen ob die Seite durch eine andere ersetzt wird. Wenn ja die Seite auch unter
        // ihrer urspruenglichen Bezeichnung cachen.
        if (substr($page_id_or_alias, 0, 2) == '0x' && $uiPage->getId() != $page_id_or_alias) {
            // Die Seite wird durch eine andere ersetzt.
            $this->pagesById[$page_id_or_alias] = $uiPage;
        } elseif (! is_numeric($page_id_or_alias) && $uiPage->getAliasWithNamespace() != $page_id_or_alias) {
            // Die Seite wird durch eine andere ersetzt.
            $this->pagesByAlias[$page_id_or_alias] = $uiPage;
        } elseif ($uiPage->getIdCms() != $page_id_or_alias) {
            // Die Seite wird durch eine andere ersetzt.
            $this->pagesByIdCms[$page_id_or_alias] = $uiPage;
        }
    }

    /**
     * 
     * @return \exface\Core\Interfaces\Model\UiPageInterface
     */
    public function getPageCurrent()
    {
        return $this->getPage($this->getPageAliasCurrent());
    }

    public function getTemplateFromRequest()
    {
        if (is_null($this->base_template)) {
            // $this->base_template = $this->getTemplate($this->getWorkbench()->getConfig()->getOption('TEMPLATES.DEFAULT_UI_TEMPLATE'));
            $this->base_template = $this->getWorkbench()->getConfig()->getOption('TEMPLATES.DEFAULT_UI_TEMPLATE');
        }
        return $this->getTemplate($this->base_template);
    }

    public function setBaseTemplateAlias($qualified_alias)
    {
        $this->base_template = $qualified_alias;
        return $this;
    }

    public function getPageAliasCurrent()
    {
        if (is_null($this->page_alias_current)) {
            $this->page_alias_current = $this->getWorkbench()->getCMS()->getCurrentPageAlias();
        }
        return $this->page_alias_current;
    }

    public function setPageAliasCurrent($value)
    {
        $this->page_alias_current = $value;
        return $this;
    }
}

?>