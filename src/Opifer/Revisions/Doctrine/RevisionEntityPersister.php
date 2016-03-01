<?php

namespace Opifer\Revisions\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Persisters\Entity\BasicEntityPersister;

class RevisionEntityPersister extends BasicEntityPersister
{

    protected function generateFilterConditionSQL(ClassMetadata $targetEntity, $targetTableAlias)
    {
        return "";
    }
}