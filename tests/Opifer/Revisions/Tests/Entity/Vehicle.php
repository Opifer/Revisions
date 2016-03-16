<?php

namespace Opifer\Revisions\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Opifer\Revisions\Mapping\Annotation as Revisions;


/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"car" = "Car", "truck" = "Truck", "boat" = "Boat"})
 * @Revisions\Revision(draft=true)
 */
abstract class Vehicle
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="license_plate", type="string", nullable=true, unique=true)
     * @Revisions\Revised
     */
    protected $licensePlate;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @Revisions\Revised
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @Revisions\Revised
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * @var \DateTime
     *
     * @Revisions\Revised
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true)
     */
    protected $deletedAt;
}