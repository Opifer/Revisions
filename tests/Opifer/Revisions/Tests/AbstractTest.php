<?php

namespace Opifer\Revisions\Tests;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AbstractTest extends KernelTestCase
{
    /**
     * @var EntityManager
     */
    protected $em = null;

    protected $classNames = [
        'Opifer\Revisions\Tests\Entity\Vehicle',
        'Opifer\Revisions\Tests\Entity\Car',
        'Opifer\Revisions\Tests\Entity\Boat',
        'Opifer\Revisions\Tests\Entity\Truck',
    ];

    protected function setUp()
    {
        self::bootKernel();

        $this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->getClasses());
    }

    public function tearDown()
    {
        $schemaTool = new SchemaTool($this->em);

        $schemaTool->dropSchema($this->getClasses());
    }

    public function getClasses()
    {
        $classes = array();

        foreach ($this->classNames as $className) {
            $classes[] = $this->em->getClassMetadata($className);
        }

        return $classes;
    }
}