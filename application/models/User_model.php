<?php

class User_model extends MY_Model {

    public $name;
    public $email;
    public $id_city;

    /**
     * @cardinality has_one
     * @class City_model
     */
    protected $city;

    /**
     * @cardinality has_many
     * @class Role_model
     * @table users_roles
     * @order name
     */
    protected $roles;
}