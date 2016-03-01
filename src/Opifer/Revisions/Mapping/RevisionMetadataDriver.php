<?php

namespace Opifer\Revisions\Mapping;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class RevisionMetadataDriver implements MappingDriver
{
    /* @var EntityManagerInterface */
    protected $em;

    /* @var AnnotationReader */
    protected $annotationReader;

    const REVISION_ENTITY = 'Opifer\\Revisions\\Model\\Revision';

    /**
     * Constructor
     *
     * @param EntityManagerInterface $em
     * @param AnnotationReader $annotationReader
     */
    public function __construct(EntityManagerInterface $em, AnnotationReader $annotationReader)
    {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
    }

    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @param string        $className
     * @param ClassMetadata $metadata
     *
     * @return void
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $originClassName = $className;
        $originMetadata = $this->em->getClassMetadata('Opifer\\ContentBundle\\Entity\\ListBlock');

//
//        $metadata->name = self::REVISION_ENTITY; //. '' . str_replace('\\', '', $class->name);
//        $class->rootEntityName = $class->name;
//        $class->namespace = 'Opifer\\Revisions\\Entity';
//
//        $class->discriminatorMap = null;
//        $class->discriminatorColumn = null;

        $metadata->setPrimaryTable(['name' => $originMetadata->getTableName() . '_revisions']);


        foreach ($originMetadata->fieldMappings as $key => $fieldMapping) {
            $fieldMapping['inherited'] = self::REVISION_ENTITY;
            $fieldMapping['declared'] = self::REVISION_ENTITY;

            if ($this->annotationReader->isPropertyRevised($originClassName, $fieldMapping['fieldName'])) {
                $metadata->mapField($fieldMapping);
            }
        }

        foreach ($originMetadata->associationMappings as $key => $associationMapping) {
            $associationMapping['inherited'] = self::REVISION_ENTITY;
            $associationMapping['declared'] = self::REVISION_ENTITY;

            if ($this->annotationReader->isPropertyRevised($originClassName, $associationMapping['fieldName'])) {
                if ($associationMapping['type'] == ClassMetadataInfo::ONE_TO_ONE) {
                    $metadata->mapOneToOne($associationMapping);
                } else if ($associationMapping['type'] == ClassMetadataInfo::MANY_TO_ONE) {
                    $metadata->mapManyToOne($associationMapping);
                } else if ($associationMapping['type'] == ClassMetadataInfo::ONE_TO_MANY) {
                    $metadata->mapOneToMany($associationMapping);
                } else if ($associationMapping['type'] == ClassMetadataInfo::MANY_TO_MANY) {
                    $metadata->mapManyToMany($associationMapping);
                }
            }

        }
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames()
    {
        return [
            'Opifer\\Revisions\\Entity\\RevisionEntity',
            'Opifer\\Revisions\\Entity\\Opifer\\ContentBundle\\Entity\\ListBlock',
            'Opifer\\Revisions\\Entity\\Opifer\\ContentBundle\\Entity\\SectionBlock',
            'Opifer\\Revisions\\Entity\\Opifer\\ContentBundle\\Entity\\JumbotronBlock',
            'Opifer\\Revisions\\Entity\\Opifer\\ContentBundle\\Entity\\NavigationBlock',
        ];
    }

    /**
     * Returns whether the class with the specified name should have its metadata loaded.
     * This is only the case if it is either mapped as an Entity or a MappedSuperclass.
     *
     * @param string $className
     *
     * @return boolean
     */
    public function isTransient($className)
    {
        return true;
    }

}