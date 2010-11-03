<?php

abstract class TeamWorkPm_Model
{
    /**
     * Es una instancia a la clase que maneja
     * las conexiones del api con curl
     * @var TeamWorkPm_Rest
     */
    private $_rest;
    /**
     * Es el elemento padre que contiene
     * los demas elementos xml de los paramentros
     * del put y del post
     * @var string
     */
    protected $_parent;
    /**
     * Es la accion que se debe ejecutar
     * @var string
     */
    protected $_action;
    /**
     *
     * @var DOMDocument
     */
    private $_doc;

    final protected function  __construct($company, $key, $class)
    {
        $this->_rest = TeamWorkPm_Rest::getInstance($company, $key);
        $class = strtolower(str_replace('TeamWorkPm_', '', $class));
        $this->_parent = str_replace('_', '-', $class);
        $this->_action = $class . 's';
        $this->_doc = new DOMDocument();
        $this->_doc->formatOutput = true;
        if (method_exists($this, '_init')) {
            $this->_init();
        }
    }

    private function _appendCreateAndUpdateXmlParameters (DOMElement $parent, array $parameters)
    {
        foreach ($this->_fields as $field=>$options) {
            $value = isset($parameters[$field]) ? $parameters[$field] : null;
            $field = str_replace('_', '-', $field);
            $element = $this->_doc->createElement($field);
            if (!is_array($options)) {
                $options = array('required'=>$options, 'attributes'=> array());
            }
            if ($options['required']) {
                if (null === $value) {
                    throw new TeamWorkPm_Exception('The field ' . $field . ' is required ');
                }
            }
            foreach ($options['attributes'] as $name=>$type) {
                list($type, $default) = explode('=', $type);
                if (is_null($value) && null !== $default) {
                    if ($default == 'false') {
                        $default = false;
                    } elseif ($default == 'true') {
                        $default = true;
                    }
                    $value = $default;
                }
                if (null !== $value) {
                    $element->setAttribute($name, $type);
                    if ($name == 'type') {
                        if ($type == 'array') {
                            $internal = $this->_doc->createElement($options['element']);
                            foreach ($value as $v) {
                                $internal->appendChild($this->_doc->createTextNode($v));
                                $element->appendChild($internal);
                            }
                        } else {
                            settype($value, $type);
                            $value = var_export($value, true);
                        }
                    }
                }
            }
            if (null !== $value) {
                $element->appendChild($this->_doc->createTextNode($value));
                $parent->appendChild($element);
            }
        }

    }

    private function _appendReOrderXmlParameters(DOMElement $parent, array $parameters)
    {
        $parent->setAttribute('type', 'array');
        foreach ($parameters as $id) {
            $element = $this->_doc->createElement($this->_parent);
            $item = $this->_doc->createElement('id');
            $item->appendChild($this->_doc->createTextNode($id));
            $element->appendChild($item);
            $parent->appendChild($element);
        }
    }
    
    private function _getXmlParameters($method, array $parameters, $reorder)
    {
        $isNewPost = $method == 'post' && $this->_action == 'posts';
        $method = $this->_doc->createElement($method);
        $parent = $this->_doc->createElement($this->_parent . ($reorder ? 's' : ''));
        $this->_doc->appendChild($method);
        if ($isNewPost) {
            $root = $this->_doc->createElement('request');
            $method->appendChild($root);
            $root->appendChild($parent);
        } else {
            $method->appendChild($parent);
        }        
        
        $method = '_append' . ($reorder ?
                  'ReOrder' :
                  'CreateAndUpdate') . 'XmlParameters';

        $this->$method($parent, $parameters);

        return $this->_doc->saveXML();
    }

    private function _appendCreateAndUpdateJsonParameters (array & $parent, array $parameters)
    {
        foreach ($this->_fields as $field=>$options) {
            $value = isset($parameters[$field]) ? $parameters[$field] : null;
            $field = str_replace('_', '-', $field);
            if (!is_array($options)) {
                $options = array('required'=>$options, 'attributes'=> array());
            }
            if ($options['required']) {
                if (null === $value) {
                    throw new TeamWorkPm_Exception('The field ' . $field . ' is required ');
                }
            }
            foreach ($options['attributes'] as $name=>$type) {
                list($type, $default) = explode('=', $type);
                if (is_null($value) && null !== $default) {
                    if ($default == 'false') {
                        $default = false;
                    } elseif ($default == 'true') {
                        $default = true;
                    }
                    $value = $default;
                }
                if (null !== $value) {
                    if ($name == 'type') {
                        if ($type == 'array') {
                            
                        } else {
                            settype($value, $type);
                        }
                    }
                }
            }
            if (null !== $value) {
                $parent[$field] = $value;
            }
        }

    }

    private function _appendReOrderJsonParameters(array &$parent, array $parameters)
    {

    }

    private function _getJsonParameters($method, array $parameters, $reorder)
    {
        $request = array();
        //$request[$method] = array();
        $isNewPost = $method == 'post' && $this->_action == 'posts';
        $parent = $this->_parent . ($reorder ? 's' : '');
        if ($isNewPost) {
            $request['request'][$parent] = array();
            $parent = & $request['request'][$parent];
        } else {
            $request[$parent] = array();
            $parent = & $request[$parent];
        }

        $method = '_append' . ($reorder ?
                  'ReOrder' :
                  'CreateAndUpdate') . 'JsonParameters';

        $this->$method($parent, $parameters);

        return json_encode($request);

    }

    private function _getParameters($method, array $parameters, $reorder)
    {
        $function = '_get' . ucfirst(TeamWorkPm_Rest::FORMAT) . 'Parameters';
        $method = strtolower($method);
        return $this->$function($method, $parameters, $reorder);
    }

    protected function _post($action, array $request = array())
    {
        return $this->_execute('POST', $action, $request);
    }

    protected function _put($action, array $request = array())
    {
        return $this->_execute('PUT', $action, $request);
    }

    private function _execute($method, $action, array $request)
    {
        $request = !empty($request) ?
            $this->_getParameters($method, $request, basename($action) == 'reorder') :
            null;
        return $this->_rest->$method($action, $request);
    }
    
    protected function _get($action, $request = null)
    {
        return $this->_rest->get($action, $request);
    }

    protected function _delete($action)
    {
        return $this->_rest->delete($action);
    }

    public function get($id)
    {
        if (is_numeric($id)) {
            return $this->_get("$this->_action/$id");
        }
        return null;
    }

    public function insert(array $data)
    {
        $project_id = $data['project_id'];
        if (empty($project_id)) {
            throw new TeamWorkPm_Exception('Require field project id');
        }
        
        return $this->_post("projects/$project_id/$this->_action", $data);
    }

    public function update(array $data)
    {
        $id = $data['id'];
        if (empty($id)) {
            throw new TeamWorkPm_Exception('Require field id');
        }
        return $this->_put("$this->_action/$id", $data);
    }

    public final function save(array $data)
    {
        return isset($data['id']) ?
            $this->update($data) :
            $this->insert($data);
    }

    public function delete($id)
    {
        if (empty($id)) {
            throw new TeamWorkPm_Exception('Require field id');
        }
        return $this->_delete("$this->_action/$id");
    }
}
