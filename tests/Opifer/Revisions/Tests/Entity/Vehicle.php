<?php

namespace Opifer\Revisions\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Opifer\Revisions\DraftInterface;
use Opifer\Revisions\Mapping\Annotation as Revisions;


/**
 * @ORM\Entity(repositoryClass="Opifer\Revisions\Tests\Repository\VehicleRepository")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"car" = "Car", "truck" = "Truck", "boat" = "Boat"})
 * @Gedmo\SoftDeleteable(fieldName="deletedAt")
 * @Revisions\Revision(draft=true)
 */
abstract class Vehicle implements DraftInterface
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

    /**
     * @var boolean
     */
    protected $draft = false;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getLicensePlate()
    {
        return $this->licensePlate;
    }

    /**
     * @param string $licensePlate
     */
    public function setLicensePlate($licensePlate)
    {
        $this->licensePlate = $licensePlate;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param \DateTime $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return boolean
     */
    public function isDraft()
    {
        return $this->draft;
    }

    /**
     * @param boolean $draft
     *
     * @return Block
     */
    public function setDraft($draft)
    {
        $this->draft = $draft;

        return $this;
    }
}