<?php

namespace Opifer\Revisions\Model;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;

class Revision
{
    public $className;
    public $revisionId;
    public $revisionType;

}