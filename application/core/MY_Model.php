<?php
defined('BASEPATH') OR exit('No direct script access allowed');

abstract class MY_Model extends CI_Model {
	
	public $id;
	protected $_is_persisted = FALSE;
	protected static $_ci;

    public function __construct()
    {
        parent::__construct();
        self::$_ci = &get_instance();
    }

    /**
	 * Get a list of entities with or without pagination.
	 * @param array $filters
	 * @param string $order_by
	 * @param bool $paged
	 * @param int $limit
	 * @param int $page
	 * @return array
	 */
	public static function find($filters = array(), $order_by = NULL, $paged = FALSE, $limit = 0, $page = 0)
	{
		if ($filters) self::$_ci->db->where($filters);
		
		if ($order_by) self::$_ci->db->order_by($order_by);
		
		$query = self::$_ci->db->get(self::_table_name(), $limit, ($limit * $page));
		
		$result = self::_build_result($query->result());
		
		if ( ! $paged) return $result;
		
		return self::_build_paged($result, self::count($filters), $limit, $page);
	}

    /**
     * Get an entity by your id.
     * @param int $id
     * @return object
     * @throws Exception
     */
    public static function get($id)
    {
        $query = self::$_ci->db->get_where(self::_table_name(), array('id' => $id));

        if ( ! $query->row())
        {
            throw new Exception('Entity "'.get_called_class()." #$id\" not found", 404);
        }

        return self::_build_instance($query->row());
    }

    /**
     * Count all entities.
     * @param array $filters
     * @param array $distinct
     */
    public static function count($filters = array(), $distinct = array())
    {
        if ($filters) self::$_ci->db->where($filters);

        if ($distinct)
        {
            if (is_array($distinct)) $distinct = implode(',', $distinct);

            self::$_ci->db->select($distinct)->distinct();
        }

        return self::$_ci->db->count_all_results(self::_table_name());
    }

    /**
     * Fill the entity with param passed by.
     * @param array $properties
     * @return object
     */
    public function fill($properties)
    {
        return self::_set_properties($properties, $this, FALSE);
    }

    /**
     * Delete an entity.
     * @throws Exception
     * @return object
     */
    public function delete()
    {
        if ( ! $this->db->delete(self::_table_name(), array('id' => $this->id)))
        {
            throw new Exception($this->db->error()['message'], $this->db->error()['code']);
        }

        $this->_is_persisted = FALSE;
        return $this;
    }

    /**
     * Save entity.
     */
    public function save()
    {
        $this->_before_save();

        if ($this->id && $this->_is_persisted)
        {
            $this->_update();
        }
        else
        {
            $this->_insert();
            $this->_is_persisted = TRUE;
            if ($this->db->insert_id()) $this->id = $this->db->insert_id();
        }

        $this->_after_save();

        return $this;
    }

    /**
     * Get the table name of entity.
     */
    protected static function _table_name()
    {
        $class_name = strtolower(get_called_class());
        return plural(str_replace('_model', '', $class_name));
    }

    /**
     * Build a result set with paginations links.
     * @param array $result_set
     * @param int $total
     * @param int $limit
     * @param int $page
     * @return array
     */
    protected static function _build_paged($result_set, $total, $limit, $page)
    {
        $my_result = array(
            'total' => $total,
            '_links' => [],
            'items' => $result_set
        );

        $num_pages = $limit > 0 ? (int) ceil($total / $limit) - 1 : 0;

        $params = self::$_ci->input->get();
        $params['limit'] = $limit;

        $params['offset'] = $page;
        $links['self']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = 0;
        $links['first']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = $page - 1;
        $links['previous']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = $page + 1;
        $links['next']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = $num_pages;
        $links['last']['href'] = uri_string().'?'.http_build_query($params);

        if ($page == 0 || $total <= $limit * $page)
        {
            unset($links['first']);
            unset($links['previous']);
        }

        if($page >= $num_pages || $total <= $limit * $page + $limit)
        {
            unset($links['next']);
            unset($links['last']);
        }

        $my_result['_links'] = $links;

        return $my_result;
    }

    /**
     * Transform a list of stdClass to list of entity instances.
     * @param array $result_set
     * @return array
     */
    protected static function _build_result($result_set)
    {
        $my_result = array();

        foreach ($result_set as $row)
        {
            $my_result[] = self::_build_instance($row);
        }

        return $my_result;
    }

    /**
     * Generates an instance of the class.
     * @param stdClass $properties
     * @return object
     */
    protected static function _build_instance($properties)
    {
        $class_name = get_called_class();
        return self::_set_properties($properties, new $class_name);
    }
	
	protected function _before_save() { }
	
	protected function _after_save() { }

    /**
     * Transform stdClass object to entity instance.
     * @param stdClass|array $properties
     * @param MY_Model $instance
     * @param bool $from_database
     * @return object
     */
    private static function _set_properties($properties, $instance, $from_database = TRUE)
    {
        if ($properties)
        {
            $properties = (object) $properties;

            foreach (get_object_vars($instance) as $key => $val)
            {
                if (substr($key, 0, 1) === '_' || ! isset($properties->$key)) continue;

                $instance->$key = $properties->$key;
            }

            if ($from_database) $instance->_is_persisted = TRUE;
        }

        return $instance;
    }

	private function _update()
	{
		if ( ! self::$_ci->db->update(self::_table_name(), $this, array('id' => $this->id)))
		{
			throw new Exception($this->db->error()['message'], $this->db->error()['code']);
		}
	}
	
	private function _insert()
	{
		if ( ! self::$_ci->db->insert(self::_table_name(), $this))
		{
			throw new Exception($this->db->error()['message'], $this->db->error()['code']);
		}
	}
}