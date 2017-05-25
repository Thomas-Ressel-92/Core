<?php
namespace exface\Core\Widgets;

/**
 * A Split consists of multiple panels aligned vertically or horizontally.
 * Using splits groups of
 * widgets can be positioned next to each other instead of one-after-another. The borders between
 * panels within a split can be dragged, thus resizing parts of the split.
 *
 * The horizontal split has the additional feature of optionally being transformed to a vertical
 * split on small screens (stacking).
 *
 * @author PATRIOT
 *        
 */
class SplitHorizontal extends SplitVertical
{

    private $stack_on_smartphones = false;

    private $stack_on_tablets = false;

    public function getStackOnSmartphones()
    {
        return $this->stack_on_smartphones;
    }

    public function setStackOnSmartphones($value)
    {
        $this->stack_on_smartphones = $value;
        return $this;
    }

    public function getStackOnTablets()
    {
        return $this->stack_on_tablets;
    }

    public function setStackOnTablets($value)
    {
        $this->stack_on_tablets = $value;
        return $this;
    }
}
?>