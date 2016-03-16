<?php

namespace Opifer\Revisions\Tests;

use Opifer\Revisions\Tests\Entity\Car;

class RevisionManagerTest extends AbstractTest
{

    public function testRevert()
    {
        $car = new Car();
        $car->setLicensePlate('AB-123-C');
        $car->setSeats(5);
        $this->em->persist($car);
        $this->em->flush();

        $car->setSeats(7);
        $this->em->flush();

        $car->setSeats(3);
        $this->em->flush();

        $carId = $car->getId();

        $this->em->detach($car); // clear cache

        $persistedCar = $this->em->getRepository('Opifer\Revisions\Tests\Entity\Vehicle')->find($carId);

        $this->revisionManager->revert($persistedCar, 1);
        $this->assertEquals(5, $persistedCar->getSeats());

        $this->revisionManager->revert($persistedCar, 2);
        $this->assertEquals(7, $persistedCar->getSeats());

        $this->revisionManager->revert($persistedCar, 3);
        $this->assertEquals(3, $persistedCar->getSeats());
    }

    public function testDraftRevert()
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

        $this->revisionManager->revert($persistedCar, 2);

        $this->assertEquals(7, $persistedCar->getSeats());
    }

}