<?php

namespace Opifer\Revisions\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Opifer\Revisions\Mapping\Annotation as Revisions;

/**
 * Truck
 *
 * @ORM\Entity
 */
class Truck extends Vehicle
{
    /**
     * @var int
     *
     * @Revisions\Revised
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxLoad;
}