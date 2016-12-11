<?php

class Role_model extends MY_Model {

    public $name;

    /**
     * @cardinality has_many
     * @class User_model
     * @table users_roles
     */
    protected $users;
}