<?php

namespace Opifer\Revisions\Exception;

class DeletedException extends \Exception
{
    /** @var object */
    protected $entity;

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param object $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }


}