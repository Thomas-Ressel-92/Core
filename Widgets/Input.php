<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

class Input extends Text implements iTakeInput {
	private $required = null;
	private $validator = null;
	private $readonly = false;
	
	public function get_validator() {
		return $this->validator;
	}
	
	public function set_validator($value) {
		$this->validator = $value;
	}
	
	/**
	 * {@inheritDoc}
	 * Input widgets are considered as required if they are explicitly marked as such or if the represent a meta attribute, 
	 * that is a required one.
	 * 
	 * IDEA It's not quite clear, if automatically marking an input as required depending on it's attribute being required,
	 * is a good idea. This works well for forms creating objects, but what if the form is used for something else? If there
	 * will be problems with this feature, the alternative would be making the EditObjectAction loop through it's widgets
	 * and set the required flag depending on attribute setting.
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::is_required()
	 */
	public function is_required() {
		if (is_null($this->required)){
			if ($this->get_attribute()){
				return $this->get_attribute()->is_required();
			} else {
				return false;
			}
		}
		return $this->required;
	}
	
	public function set_required($value) {
		$this->required = $value;
	}
	
	/**
	 * Input widgets are disabled if the displayed attribute is not editable or if the widget was explicitly disabled.
	 * @see \exface\Core\Widgets\AbstractWidget::is_disabled()
	 */
	public function is_disabled(){
		if ($this->is_readonly()){
			return true;
		}
		
		$disabled = parent::is_disabled();
		if (is_null($disabled)){
			try {
				if (!$this->get_attribute()->is_editable()){
					$disabled = true;
				} else {
					$disabled = false;
				}
			} catch (MetaAttributeNotFoundError $e){
				// Ignore invalid attributes
			}
		}
		return $disabled;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::is_readonly()
	 */
	public function is_readonly() {
		return $this->readonly;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::set_readonly()
	 */
	public function set_readonly($value) {
		$this->readonly = $value ? true : false;
		return $this;
	}
  
}
?>