<?php

namespace Opifer\Revisions\Filter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Opifer\Revisions\EventListener\RevisionListener;

/**
 * Class DraftFilter
 *
 * The DraftVersionFilter adds the condition necessary to
 * filter entities which are created as draft (createdAt NULL)
 *
 * @package Opifer\Revisions
 */
class DraftFilter extends SQLFilter
{

    protected $listener;
    protected $em;

    /**
     * @param ClassMetaData $targetEntity
     * @param string        $targetTableAlias
     *
     * @return string
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        $annotationReader = $this->getListener()->getAnnotationReader();

        if (!$annotationReader->isDraft($targetEntity->getName())) {
            return;
        }

        // Check if the entity implements the LocalAware interface
        if (!$targetEntity->reflClass->implementsInterface('Opifer\Revisions\DraftInterface')) {
            return "";
        }

        return "{$targetTableAlias}.created_at IS NOT NULL";
    }

    protected function getListener()
    {
        if ($this->listener === null) {
            $em = $this->getEntityManager();
            $evm = $em->getEventManager();

            foreach ($evm->getListeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof RevisionListener) {
                        $this->listener = $listener;

                        break 2;
                    }
                }
            }

            if ($this->listener === null) {
                throw new \RuntimeException('Listener "RevisionListener" was not added to the EventManager!');
            }
        }

        return $this->listener;
    }

    protected function getEntityManager()
    {
        if ($this->em === null) {
            $refl = new \ReflectionProperty('Doctrine\ORM\Query\Filter\SQLFilter', 'em');
            $refl->setAccessible(true);
            $this->em = $refl->getValue($this);
        }

        return $this->em;
    }
}
