<?php 
namespace LeviZwannah\PesapalSdk\Traits;

use LeviZwannah\PesapalSdk\PesapalException;

trait AssertTrait {
    /**
     * Ensures that a property exists and it's not empty
     * @param string $attribute
     * @param string $descName
     * 
     * @return void
     */
    public function assertProperty($attribute, $descName = ""){
        if(empty($descName)){
            $descName = $attribute;
        }

        if(!property_exists($this, $attribute) || empty($this->$attribute)) throw new PesapalException("$descName must not be empty");
    }

    /**
     * Checks if the request was accepted.
     * @return void
     */
    public function assertAccepted(){
        if(!$this->accepted()){
            throw new PesapalException("Pesapal rejected the request with error " . print_r((array)$this->response(), true));
        }
    }
}

?>