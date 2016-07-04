<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\UiWidgetException;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\NameResolver;

/**
 * A Button is the primary widget for triggering actions. In addition to the general widget attributes it can have
 * an icon and also subwidgets (if the triggered action shows a widget).
 * @author aka
 *
 */
class Button extends AbstractWidget implements iHaveIcon, iTriggerAction, iHaveChildren {
	private $action_alias = null;
	private $action = null;
	private $active_condition = null;
	private $input_widget_id = null;
	private $input_widget = null;
	private $hotkey = null;
	private $icon_name = null;
	private $refresh_input = true;
	private $hide_button_text = false;
	private $hide_button_icon = false;
	
	public function get_action() {
		if (!$this->action){
			if ($this->get_action_alias()){
				$this->action = ActionFactory::create($this->exface()->create_name_resolver($this->get_action_alias(), NameResolver::OBJECT_TYPE_ACTION), $this);
			}
		}
		return $this->action;
	}
	
	public function set_action($action_object_or_uxon_description) {
		if ($action_object_or_uxon_description instanceof ActionInterface){
			$this->action = $action_object_or_uxon_description;
		} elseif ($action_object_or_uxon_description instanceof \stdClass){
			$this->set_action_alias($action_object_or_uxon_description->alias);
			$this->set_action_options($action_object_or_uxon_description);
		} else {
			throw new UiWidgetException('The set_action() method of a button accepts either an action object extended from ActionInterface or a UXON description object. ' . gettype($action_object_or_uxon_description) . ' given for button "' . $this->get_id() . '".');
		}
	}
	
	public function get_action_alias() {
		// If the action has already been instantiated, return it's qualified alias. This is mostly the same as the alias in $this->action_alias
		// but they may differ in case ($this->action_alias is entered by the user!). In addition this approach would allow to switch the
		// action of the button programmatically, still getting the right alias here.
		if ($this->action){
			return $this->get_action()->get_alias_with_namespace();
		}
		return $this->action_alias;
	}
	
	public function set_action_alias($value) {
		$this->action_alias = $value;
	}  
	
	public function get_active_condition() {
		return $this->active_condition;
	}
	
	public function set_active_condition($value) {
		$this->active_condition = $value;
	}
	
	
	/**
	 * @param string $caption
	 */
	public function set_caption($caption) {
		// TODO get caption automatically from action model once it is created
		$this->caption = $caption;
	}
	
	/**
	 * Returns the id of the widget, which the action is supposed to be performed upon.
	 * I.e. if it is an Action doing something with a table row, the input widget will be
	 * the table. If the action ist to be performed upon an Input field - that Input is the input widget.
	 * 
	 * By default the input widget is the actions parent
	 */
	public function get_input_widget_id() {
		if (!$this->input_widget_id){
			if ($this->input_widget){
				$this->set_input_widget_id($this->get_input_widget()->get_id());
			} else {
				$this->set_input_widget_id($this->get_parent()->get_id());
			}
		}
		return $this->input_widget_id;
	}
	
	/**
	 * Sets the input widget id 
	 * @param string $value
	 */
	public function set_input_widget_id($value) {
		$this->input_widget_id = $value;
	}
	
	public function get_input_widget() {
		if (!$this->input_widget){
			if ($this->input_widget_id){
				$this->input_widget = $this->get_ui()->get_widget($this->input_widget_id, $this->get_page_id());
			} else {
				$this->input_widget = $this->get_parent();
			}
		}
		return $this->input_widget;
	}
	
	public function set_input_widget(AbstractWidget $widget) {
		$this->input_widget = $widget;
		$this->set_input_widget_id($widget->get_id());
		return $this;
	}

	/**
	 * Buttons allow to set action options as an options array or directly as an option of the button itself.
	 * In the latter case the option's name must be prefixed by "action_": to set a action's property
	 * called "script" simply add "action_script": XXX to the button.
	 * @see \exface\Core\Widgets\AbstractWidget::import_uxon_object()
	 */
	public function import_uxon_object(\stdClass $source) {
		// If there are button attributes starting with "action_", these are just shortcuts for
		// action attributes. We need to remove them from the button's description an pass
		// them all in on "action_options" attribute. The only exclusion is the action_alias, which
		// we need to instantiate the action.
		$action_options = $source->action_options ? $source->action_options : new \stdClass();
		foreach ($source as $attr => $val){
			if ($attr != 'action_alias' && strpos($attr, "action_") === 0){
				unset($source->$attr);
				$attr = substr($attr, 7);
				$action_options->$attr = $val;
			}
		}
		if (count((array)$action_options)){
			$source->action_options = $action_options;
		}
		parent::import_uxon_object($source);
	} 
	
	/**
	 * Sets options of the action, defined in the button's description.
	 * NOTE: the action must be defined first!
	 * @param \stdClass $action_options
	 * @throws \exface\Core\Exceptions\UiWidgetException
	 */
	public function set_action_options(\stdClass $action_options){
		if (!$action = $this->get_action()){
			throw new \exface\Core\Exceptions\UiWidgetException('Cannot set action properties prior to action initialization! Please ensure, that the action_alias is defined first!');
		} else {
			$action->import_uxon_object($action_options);
		}
	}
	/**
	 * Returns the hotkeys bound to this button. 
	 * @see set_hotkey()
	 * @return string
	 */
	public function get_hotkey() {
		return $this->hotkey;
	}
	
	/**
	 * Make the button perform it's action when the hotkey is pressed. Hotkeys can be passed in
	 * JS manner: ctrl+z, alt+q, etc. Multiple hotkeys can be used by separating them by comma.
	 * If multiple hotkeys defined, they will all act exactly the same.
	 * @param string $value
	 */
	public function set_hotkey($value) {
		$this->hotkey = $value;
	}  
	
	public function get_icon_name() {
		if (!$this->icon_name && $this->get_action()){
			$this->icon_name = $this->get_action()->get_icon_name();
		}
		return $this->icon_name;
	}
	
	public function set_icon_name($value) {
		$this->icon_name = $value;
	}
	
	public function get_refresh_input() {
		return $this->refresh_input;
	}
	
	public function set_refresh_input($value) {
		$this->refresh_input = $value;
	}  

	public function get_hide_button_text() {
		return $this->hide_button_text;
	}
	
	public function set_hide_button_text($value) {
		$this->hide_button_text = $value;
	}
	
	public function get_hide_button_icon() {
		return $this->hide_button_icon;
	}
	
	public function set_hide_button_icon($value) {
		$this->hide_button_icon = $value;
	}
	
	/**
	 * The Button may have a child widget, if the action it triggers shows a widget.
	 * NOTE: the widget description will only be returned, if the widget is explicitly defined, not merely by a link to another resource.
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children(){
		$children = array();
		if ($this->get_action() && $this->get_action()->implements_interface('iShowWidget') && $this->get_action()->get_widget()){
			$children[] = $this->get_action()->get_widget();
		}
		return $children;
	}    
}
?>