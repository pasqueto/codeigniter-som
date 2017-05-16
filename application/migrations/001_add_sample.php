<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_sample extends CI_Migration {

    public function up()
    {
        $this->_create_users();
        $this->_create_cities();
        $this->_create_roles();
        $this->_create_users_roles();
        $this->_create_foreign_keys();
        $this->_seed();
    }

    public function down()
    {
        $this->dbforge->drop_table('users_roles');
        $this->dbforge->drop_table('users');
        $this->dbforge->drop_table('roles');
        $this->dbforge->drop_table('cities');
    }

    private function _create_users()
    {
        $this->dbforge->add_field(array(
                'id' => array(
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => TRUE,
                        'auto_increment' => TRUE
                ),
                'name' => array(
                        'type' => 'VARCHAR',
                        'constraint' => 45,
                ),
                'email' => array(
                        'type' => 'VARCHAR',
                        'constraint' => 60,
                        'null' => TRUE,
                        'default' => NULL
                ),
                'id_city' => array(
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => TRUE,
                        'null' => TRUE,
                        'default' => NULL
                ),
        ));

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('users');
    }

    private function _create_cities()
    {
        $this->dbforge->add_field(array(
                'id' => array(
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => TRUE,
                        'auto_increment' => TRUE
                ),
                'name' => array(
                        'type' => 'VARCHAR',
                        'constraint' => 45
                )
        ));

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('cities');
    }

    private function _create_roles()
    {
        $this->dbforge->add_field(array(
                'id' => array(
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => TRUE,
                        'auto_increment' => TRUE
                ),
                'name' => array(
                        'type' => 'VARCHAR',
                        'constraint' => 45
                )
        ));

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('roles');
    }

    private function _create_users_roles()
    {
        $this->dbforge->add_field(array(
                'id_user' => array(
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => TRUE,
                ),
                'id_role' => array(
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => TRUE,
                )
        ));

        $this->dbforge->add_key('id_user', TRUE);
        $this->dbforge->add_key('id_role', TRUE);

        $this->dbforge->create_table('users_roles');
    }

    private function _create_foreign_keys()
    {
        $this->db->query('ALTER TABLE users ADD CONSTRAINT fk_user_city FOREIGN KEY (id_city) REFERENCES cities(id)');

        $this->db->query('ALTER TABLE users_roles ADD CONSTRAINT fk_userrole_user FOREIGN KEY (id_user) 
                REFERENCES users(id) ON DELETE CASCADE');

        $this->db->query('ALTER TABLE users_roles ADD CONSTRAINT fk_userrole_role FOREIGN KEY (id_role)
                 REFERENCES roles(id)');
    }

    private function _seed()
    {
        $this->db->query("INSERT INTO roles(id, name) VALUES(1, 'Master'), (2, 'User')");

        $this->db->query("INSERT INTO cities(id, name) VALUES(1, 'New York'), (2, 'Miami'), (3, 'Chicago')");

        $this->db->query("INSERT INTO users(id, name, email, id_city) VALUES
                (1, 'Rafel', 'rafael@turtleninja.com', 1), 
                (2, 'Michelangelo', 'michelangelo@turtleninja.com', 2), 
                (3, 'Leonardo', 'leonardo@turtleninja.com', 2), 
                (4, 'Donatello', 'donatello@turtleninja.com', 1)");

        $this->db->query("INSERT INTO users_roles(id_user, id_role) VALUES
                (1, 2), (2, 1), (2, 2), (3, 1), (3, 2), (4, 2)");
    }
}
