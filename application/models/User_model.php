<?php

class User_model extends MY_Model {

    public $name;
    public $email;
    public $id_city;

    /**
     * @cardinality has_one
     * @class City_model
     * @ref id_city
     */
    protected $city;
}