<?php

namespace Opifer\Revisions\Filter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Class DraftFilter
 *
 * The DraftVersionFilter adds the condition necessary to
 * filter entities which are created as draft (createdAt NULL)
 *
 * @package Opifer\Revisions
 */
class DraftFilter extends SQLFilter
{
    /**
     * @param ClassMetaData $targetEntity
     * @param string        $targetTableAlias
     *
     * @return string
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        // Check if the entity implements the LocalAware interface
        if (!$targetEntity->reflClass->implementsInterface('Opifer\Revisions\DraftInterface')) {
            return "";
        }

        return "{$targetTableAlias}.created_at IS NOT NULL";
    }
}
