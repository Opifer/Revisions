<?php

namespace Opifer\Revisions\Doctrine;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Internal;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

class RevisionManagerDecorator extends EntityManagerDecorator
{
    /** @var ClassMetadataFactory */
    protected $metadataFactory;

    /** @var ClassMetadataFactory */
    protected $wrappedMetadataFactory;

    /**
     * RevisionManagerDecorator constructor.
     *
     * @param EntityManagerInterface $wrapped
     * @param ClassMetadataFactory   $metadataFactory
     */
    public function __construct(EntityManagerInterface $wrapped, ClassMetadataFactory $metadataFactory)
    {
        $this->wrapped = $wrapped;
        $this->metadataFactory = $metadataFactory;
        $this->wrappedMetadataFactory = $wrapped->getMetadataFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function getClassMetadata($className)
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * {@inheritdoc}
     */
    public function find($entityName, $id, $lockMode = null, $lockVersion = null)
    {
        $this->overwriteMetadataFactory();
        $result = $this->wrapped->find($entityName, $id, $lockMode, $lockVersion);
        $this->resetMetadataFactory();

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * {@inheritDoc}
     */
    public function createQuery($dql = '')
    {
        $query = new Query($this);

        if ( ! empty($dql)) {
            $query->setDql($dql);
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    public function newHydrator($hydrationMode)
    {
        switch ($hydrationMode) {
            case Query::HYDRATE_OBJECT:
                return new Internal\Hydration\ObjectHydrator($this);

            case Query::HYDRATE_ARRAY:
                return new Internal\Hydration\ArrayHydrator($this);

            case Query::HYDRATE_SCALAR:
                return new Internal\Hydration\ScalarHydrator($this);

            case Query::HYDRATE_SINGLE_SCALAR:
                return new Internal\Hydration\SingleScalarHydrator($this);

            case Query::HYDRATE_SIMPLEOBJECT:
                return new Internal\Hydration\SimpleObjectHydrator($this);

            default:
                if (($class = $this->getConfiguration()->getCustomHydrationMode($hydrationMode)) !== null) {
                    return new $class($this);
                }
        }

        throw ORMException::invalidHydrationMode($hydrationMode);
    }


    public function overwriteMetadataFactory()
    {
        $reflProp = new \ReflectionProperty($this->wrapped, 'metadataFactory');
        $reflProp->setAccessible(true);
        $reflProp->setValue($this->wrapped, $this->metadataFactory);
    }

    public function resetMetadataFactory()
    {
        $reflProp = new \ReflectionProperty($this->wrapped, 'metadataFactory');
        $reflProp->setAccessible(true);
        $reflProp->setValue($this->wrapped, $this->wrappedMetadataFactory);
    }
}