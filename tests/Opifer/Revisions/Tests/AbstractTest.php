<?php

namespace Opifer\Revisions\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Opifer\Revisions\RevisionManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AbstractTest extends KernelTestCase
{
    /**
     * @var EntityManager
     */
    protected $em = null;
    /**
     * @var RevisionManager
     */
    protected $revisionManager = null;

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

        $this->revisionManager = static::$kernel->getContainer()
            ->get('opifer.revisions.revision_manager');

        $driver = new AnnotationDriver(new AnnotationReader(), [__DIR__ . '/Entity']);
        $this->em->getConfiguration()->setMetadataDriverImpl($driver);
        $this->em->getFilters()->enable('draft');
        $this->em->getFilters()->disable('softdeleteable');

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->getClasses());
    }

    public function getClasses()
    {
        $classes = array();

        foreach ($this->classNames as $className) {
            $classes[] = $this->em->getClassMetadata($className);
        }

        return $classes;
    }

    public function tearDown()
    {
        $schemaTool = new SchemaTool($this->em);

        $schemaTool->dropSchema($this->getClasses());
    }
}