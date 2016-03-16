<?php

namespace Opifer\Revisions\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Opifer\Revisions\Mapping\Annotation as Revisions;

/**
 * Boat
 *
 * @ORM\Entity
 */
class Boat extends Vehicle
{
    /**
     * @var string
     *
     * @Revisions\Revised
     * @ORM\Column(type="string", nullable=true)
     */
    protected $propulsionType;
}