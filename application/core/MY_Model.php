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
     *
	 * @param array $filters
	 * @param string $order_by
	 * @param bool $paged
	 * @param int $limit
	 * @param int $page
	 * @return array
	 */
	public static function find($filters = array(), $order_by = NULL, $paged = FALSE, $limit = 0, $offset = 0)
	{
		if ($filters) self::$_ci->db->where($filters);
		
		if ($order_by) self::$_ci->db->order_by($order_by);
		
		$query = self::$_ci->db->get(self::_table_name(), $limit, $offset);
		
		$result = self::_build_result($query->result());
		
		if ( ! $paged) return $result;
		
		return self::_build_paged($result, self::count($filters), $limit, $offset);
	}


    /**
     * Get an entity by your id.
     *
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
     *
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
     *
     * @param array $properties
     * @return object
     */
    public function fill($properties)
    {
        return self::_set_properties($properties, $this, FALSE);
    }


    /**
     * Delete an entity.
     *
     * @throws Exception
     * @return object
     */
    public function delete()
    {
        if ( ! self::$_ci->db->delete(self::_table_name(), array('id' => $this->id)))
        {
            throw new Exception(self::$_ci->db->error()['message'], self::$_ci->db->error()['code']);
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
            if (self::$_ci->db->insert_id()) $this->id = self::$_ci->db->insert_id();
        }

        $this->_after_save();

        return $this;
    }


    /**
     *  __get magic
     *
     * @param string $name
     * @return array|null
     * @throws Exception
     */
    public function __get($name)
    {
        $class = new ReflectionClass(get_called_class());
        $doc_params = self::_doc_params($class->getProperty($name)->getDocComment());

        if ( ! isset($doc_params['cardinality']) OR ! isset($doc_params['class']))
        {
            return NULL;
        }

        self::$_ci->load->model($doc_params['class']);

        if ($doc_params['cardinality'] == 'has_many')
        {
            if ($this->id && ! $this->$name)
            {
                $order = 'id asc';

                if (isset($doc_params['order']))
                {
                    $order = $doc_params['order'];
                }

                $ref_class = new ReflectionClass($doc_params['class']);
                $is_many_to_many = FALSE;

                foreach ($ref_class->getProperties() as $ref_property)
                {
                    $ref_params = self::_doc_params($ref_property->getDocComment());

                    if (isset($ref_params['class']) && $ref_params['class'] == get_called_class() &&
                        isset($ref_params['cardinality']) && $ref_params['cardinality'] == 'has_many')
                    {
                        $is_many_to_many = TRUE;
                        break;
                    }
                }

                if ($is_many_to_many)
                {
                    if ( ! isset($doc_params['table']))
                    {
                        throw new Exception('Missing parameter "table" on many to many cardinality.');
                    }

                    $t_ref = self::_table_name($doc_params['class']); // table_reference
                    $r_ref = self::_foreign_property($doc_params['class']); // right_reference
                    $s_ref = self::_foreign_property(); // self_reference

                    return self::_build_result(
                        self::$_ci->db->select("$t_ref.*")
                            ->join($t_ref, "$t_ref.id = {$doc_params['table']}.$r_ref")
                            ->where("{$doc_params['table']}.$s_ref", $this->id)
                            ->order_by("$t_ref.$order")
                            ->get($doc_params['table'])
                            ->result(),
                        $doc_params['class']);

                }

                return $doc_params['class']::find([self::_foreign_property() => $this->id], $order);
            }
            else
            {
                return array();
            }
        }
        elseif ($doc_params['cardinality'] == 'has_one')
        {
            if ($this->{self::_foreign_property($doc_params['class'])} && ! $this->$name)
            {
                return $doc_params['class']::get($this->{self::_foreign_property($doc_params['class'])});
            }
            else
            {
                return new $doc_params['class'];
            }
        }
    }


    /**
     * Get the table name of entity.
     * @param string $class_name
     * @return string
     */
    protected static function _table_name($class_name = NULL)
    {
        if ( ! $class_name)
        {
            $class_name = get_called_class();
        }

        $class = new ReflectionClass($class_name);
        $doc_params = self::_doc_params($class->getDocComment());

        if (isset($doc_params['table']))
        {
            return $doc_params['table'];
        }
        else
        {
            self::$_ci->load->helper('inflector');
            return plural(str_replace('_model', '', strtolower($class_name)));
        }
    }


    /**
     * Build a result set with paginations links.
     *
     * @param array $result_set
     * @param int $total
     * @param int $limit
     * @param int $page
     * @return array
     */
    protected static function _build_paged($result_set, $total, $limit, $offset)
    {
        self::$_ci->load->helper('url');

        $my_result = array(
            'total' => $total,
            '_links' => [],
            'items' => $result_set
        );

        $num_pages = $limit > 0 ? (int) ceil($total / $limit) - 1 : 0;

        $params = self::$_ci->input->get();
        $params['limit'] = $limit;

        $params['offset'] = $offset;
        $links['self']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = 0;
        $links['first']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = ($offset - $limit) > 0 ? ($offset - $limit) : 0;
        $links['previous']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = ($offset + $limit) > ($total - $limit) ? ($total - $limit) : ($offset + $limit);
        $links['next']['href'] = uri_string().'?'.http_build_query($params);

        $params['offset'] = $total - $limit;
        $links['last']['href'] = uri_string().'?'.http_build_query($params);

        if ($offset == 0 OR $total <= $offset)
        {
            unset($links['first']);
            unset($links['previous']);
        }

        if ($offset >= ($total - $limit))
        {
            unset($links['next']);
            unset($links['last']);
        }

        $my_result['_links'] = $links;

        return $my_result;
    }


    /**
     * Transform a list of stdClass to list of entity instances.
     *
     * @param array $result_set
     * @param string $class_name
     * @return array
     */
    protected static function _build_result($result_set, $class_name = NULL)
    {
        $my_result = array();

        foreach ($result_set as $row)
        {
            $my_result[] = self::_build_instance($row, $class_name);
        }

        return $my_result;
    }


    /**
     * Generates an instance of the class.
     *
     * @param stdClass $properties
     * @param string $class_name
     * @return object
     */
    protected static function _build_instance($properties, $class_name = NULL)
    {
        if ( ! $class_name)
        {
            $class_name = get_called_class();
        }

        return self::_set_properties($properties, new $class_name);
    }


	protected function _before_save() { }


	protected function _after_save() { }


	protected function _update()
	{
		if ( ! self::$_ci->db->update(self::_table_name(), $this, array('id' => $this->id)))
		{
			throw new Exception(self::$_ci->db->error()['message'], self::$_ci->db->error()['code']);
		}
	}


	protected function _insert()
	{
		if ( ! self::$_ci->db->insert(self::_table_name(), $this))
		{
			throw new Exception(self::$_ci->db->error()['message'], self::$_ci->db->error()['code']);
		}
	}


    /**
     * Transform stdClass object to entity instance.
     *
     * @param object|array $properties
     * @param object $instance
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
                if (substr($key, 0, 1) === '_' OR ! isset($properties->$key)) continue;

                $instance->$key = $properties->$key;
            }

            if ($from_database) $instance->_is_persisted = TRUE;
        }

        return $instance;
    }


    /**
     * Extract doc params from doc comment.
     *
     * @param string $doc_comment
     * @return array
     */
    private static function _doc_params($doc_comment)
    {
        preg_match_all('/@([^@*]+)\s+([^*\s]+)/', $doc_comment, $matches);

        $params = array();
        foreach ($matches[1] as $i => $param)
        {
            $params[$param] = $matches[2][$i];
        }

        return $params;
    }


    /**
     * Get foreign key from class.
     *
     * @param string|NULL $class_name
     * @return string
     */
    private static function _foreign_property($class_name = NULL)
    {
        if ( ! $class_name)
        {
            $class_name = get_called_class();
        }

        $class = new ReflectionClass($class_name);
        $doc_params = self::_doc_params($class->getDocComment());

        if (isset($doc_params['table']))
        {
            self::$_ci->load->helper('inflector');
            return 'id_'.singular($doc_params['table']);
        }
        else
        {
            return 'id_'.str_replace('_model', '', strtolower($class_name));
        }
    }
}