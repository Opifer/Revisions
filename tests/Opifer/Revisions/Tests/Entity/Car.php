<?php

namespace Opifer\Revisions\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Opifer\Revisions\Mapping\Annotation as Revisions;

/**
 * Car
 *
 * @ORM\Entity
 */
class Car extends Vehicle
{
    /**
     * @var int
     *
     * @ORM\Column(name="seats", type="integer", nullable=true)
     * @Revisions\Revised
     */
    protected $seats;

    /**
     * @return int
     */
    public function getSeats()
    {
        return $this->seats;
    }

    /**
     * @param int $seats
     */
    public function setSeats($seats)
    {
        $this->seats = $seats;
    }
}