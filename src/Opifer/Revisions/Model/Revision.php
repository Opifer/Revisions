<?php

namespace Opifer\Revisions\Model;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;

class Revision
{
    protected $className;
    protected $revisionId;
    protected $revisionType;
    protected $entity;
    protected $data;

    /**
     * @var \ReflectionClass
     */
    protected $reflection;

    public function __construct($data, $entity)
    {
        $data = (array) $data;
        $this->className = get_class($entity);
        $this->entity = $entity;
        $this->revisionId = $data['revision_id'];
        $this->revisionType = $data['rev_type'];
        $this->data = array_diff_key($data, ['revision_id', 'rev_type']);
        $this->reflection = new \ReflectionClass($entity);
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getRevisionId()
    {
        return $this->revisionId;
    }

    public function getRevisionType()
    {
        return $this->revisionType;
    }

    public function __call($name, $arguments)
    {
        $property = lcfirst(substr($name, 3));
        if (strpos($name, 'get') === 0 && $this->reflection->hasMethod($name) && $this->reflection->hasProperty($property)) {
            return isset($this->data[$property]) ? $this->data[$property] : call_user_func_array([$this->entity, $name], $arguments);
        }
        // Support twig.
        elseif ($this->reflection->hasProperty($name)) {
          return isset($this->data[$name]) ? $this->data[$name] : call_user_func_array([$this->entity, 'get' . ucfirst($name)], $arguments);
        }
        trigger_error('Call to undefined method '.__CLASS__.'::'.$name.'()', E_USER_ERROR);
    }
}
