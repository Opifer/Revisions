<?php

namespace Opifer\Revisions\Tests;

use Doctrine\ORM\EntityNotFoundException;
use Opifer\Revisions\Tests\Entity\Car;
use Opifer\Revisions\Tests\Entity\Boat;

class RevisionListenerTest extends AbstractTest
{
    public function testInsert()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);

        $car2 = new Car();
        $car2->setLicensePlate('DE-456-F');
        $car2->setSeats(2);

        $boat = new Boat();
        $boat->setLicensePlate('ABC1234');
        $boat->setPropulsionType('jet');

        $this->em->persist($car);
        $this->em->persist($car2);
        $this->em->persist($boat);
        $this->em->flush();

        $this->assertEquals(1, count($this->em->getConnection()->fetchAll('SELECT id FROM revisions')));
    }

    public function testUpdate()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);

        $car2 = new Car();
        $car2->setLicensePlate('DE-456-F');
        $car2->setSeats(2);

        $boat = new Boat();
        $boat->setLicensePlate('ABC1234');
        $boat->setPropulsionType('jet');

        $this->em->persist($car);
        $this->em->persist($car2);
        $this->em->persist($boat);
        $this->em->flush();


        $car->setSeats(6);
        $this->em->flush($car);
        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT id FROM revisions')));

        $boat->setPropulsionType('propellor');
        $this->em->flush($boat);

        $this->assertEquals(3, count($this->em->getConnection()->fetchAll('SELECT id FROM revisions')));
    }


    public function testDelete()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);
        $this->em->persist($car);
        $this->em->flush();

        $carId = $car->getId();

        $this->em->remove($car);
        $this->em->flush();

        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT id FROM opifer_vehicle_revisions WHERE id = "'.$carId.'"')));

        $this->em->remove($car);
        $this->em->flush();

//        $this->assertNull($this->em->getRepository('Opifer\Revisions\Tests\Entity\Vehicle')->find($carId)); no hard deletes support atm
    }

    public function testDraftInsert()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);
        $car->setDraft(true);
        $this->em->persist($car);
        $this->em->flush();

        $carId = $car->getId();

        $this->em->detach($car); // clear cache otherwise draftfilter wont do its work
        $this->assertNull($this->em->getRepository('Opifer\Revisions\Tests\Entity\Vehicle')->find($carId));
    }

    public function testDraftUpdate()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);
        $this->em->persist($car);
        $this->em->flush();

        $car->setDraft(true);
        $car->setSeats(7);
        $this->em->flush();

        $carId = $car->getId();

        $this->em->detach($car); // clear cache
        $persistedCar = $this->em->getRepository('Opifer\Revisions\Tests\Entity\Vehicle')->find($carId);

        $this->assertEquals($car->getSeats(), $persistedCar->getSeats(), "Update on drafted entity should only have it's update persisted to the revisions table");
    }

    public function testDraftDelete()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);
        $this->em->persist($car);
        $this->em->flush();

        $carId = $car->getId();

        $car->setDraft(true);
        $this->em->remove($car);
        $this->em->flush();

        $this->em->detach($car); // clear cache

        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT id FROM opifer_vehicle_revisions WHERE id = "'.$carId.'"')));

        $persistedCar = $this->em->getRepository('Opifer\Revisions\Tests\Entity\Vehicle')->find($carId);

        $this->assertNull($persistedCar->getDeletedAt());
    }
}