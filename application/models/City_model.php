<?php

class City_model extends MY_Model {

    public $name;

    /**
     * @cardinality has_many
     * @class User_model
     * @ref id_city
     */
    protected $users;
}