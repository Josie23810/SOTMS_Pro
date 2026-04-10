<?php 
namespace LeviZwannah\PesapalSdk\Traits;

use BadMethodCallException;

/**
 * Converts class attributes to functions
 */
trait PropertyTrait{
    public function __call($name, $arguments){
        if(!property_exists($this, $name)) throw new BadMethodCallException("Property does not exist");

        if(empty($arguments)) return $this->$name;
        $this->$name = $arguments[0];
        return $this;
    }
}
?>