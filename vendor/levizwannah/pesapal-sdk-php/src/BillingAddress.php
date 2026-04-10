<?php 
namespace LeviZwannah\PesapalSdk;

use LeviZwannah\PesapalSdk\Traits\PropertyTrait;

/**
 * Wraps the Billing Address for the OrderData object.
 */
class BillingAddress{
    use PropertyTrait;

    /**
     * customer phone number.
     * Optional if email address is provided.
     * Mandatory if email address is **NOT** provided.
     * @var string
     */
    public string $phone = "";

    /**
     * Customer Email.
     * Optional if phone number is provided. 
     * Mandatory if phone number is **NOT** provided.
     * @var string
     */
    public string $email = "";

    /**
     * 	2 characters long country code in 
     * [ISO 3166-1](https://en.wikipedia.org/wiki/ISO_3166-1) **(optional)**
     * @var string
     */
    public string $countryCode = "";

    /** 
     * Customer's first name **(optional)**
     * @var string
     */
    public string $firstName = "";

    /**
     * Customer's middle name **(optional)**
     * @var string
     */
    public string $middleName = "";

    /**
     * Customer's last name **(optional)**
     * @var string
     */
    public string $lastName = "";

    /**
     * Customer's main address **(optional)**
     * @var string
     */
    public string $address1 = "";

    /**
     * Customer's alternative address **(optional)**
     * @var string
     */
    public string $address2 = "";

    /**
     * Customer's city **(optional)**
     * @var string
     */
    public string $city = "";

    /**
     * Customer's state *Maximum - 3 characters* **(optional)**
     * @var string
     */
    public string $state = "";

    /**
     * Customer's postal code **(optional)**
     * @var string
     */
    public string $postalCode = "";

    /**
     * Customer's zip code **(optional)**
     * @var string
     */
    public string $zipCode = "";

    /**
     * Converts the billing object to a data suitable for Pesapal
     * @return array
     */
    public function data(){
        return [
            "email_address" => $this->email,
            "phone_number" => $this->phone,
            "country_code" => $this->countryCode,
            "first_name" => $this->firstName,
            "middle_name" => $this->middleName,
            "last_name" => $this->lastName,
            "line_1" => $this->address1,
            "line_2" => $this->address2,
            "city" => $this->city,
            "state" => $this->state,
            "postal_code" => $this->postalCode,
            "zip_code" => $this->zipCode
        ];
    }
}

?>