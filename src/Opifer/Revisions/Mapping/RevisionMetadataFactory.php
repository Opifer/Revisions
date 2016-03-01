<?php

namespace Opifer\Revisions\Mapping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class RevisionMetadataFactory implements ClassMetadataFactory
{
    /* @var AnnotationReader */
    protected $annotationReader;

    const REVISION_ENTITY = 'Opifer\\Revisions\\Model\\Revision';

    /** @var array */
    protected $classNames;

    /** @var ClassMetadataFactory */
    protected $wrapped;

    /** @var array */
    protected $transformedMetadata = array();

    /**
     * RevisionMetadataFactory constructor.
     *
     * @param ClassMetadataFactory $wrapped
     * @param AnnotationReader     $annotationReader
     */
    public function __construct(ClassMetadataFactory $wrapped, AnnotationReader $annotationReader)
    {
        $this->wrapped = $wrapped;
        $this->annotationReader = $annotationReader;
        $this->getAllClassNames();
    }

    /**
     * @param string $className
     *
     * @return ClassMetadata
     *
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     */
    public function getMetadataFor($className)
    {
        if (isset($this->transformMetadata[$className])) {
            return $this->transformMetadata[$className];
        }

        $class = $this->wrapped->getMetadataFor($className);

        if (in_array($className, $this->classNames)) {
            $class = $this->transformMetadata($class);
            $this->transformMetadata[$className] = $class;
        }

        return $class;
    }

    /**
     * @inheritDoc
     */
    public function getAllMetadata()
    {
        return $this->wrapped->getAllMetadata();
    }

    /**
     * @inheritDoc
     */
    public function hasMetadataFor($className)
    {
        if ($className == self::REVISION_ENTITY) {
            return false;
        }

        return $this->wrapped->hasMetadataFor($className);
    }

    /**
     * @inheritDoc
     */
    public function setMetadataFor($className, $class)
    {
        return $this->setMetadataFor($className, $class);
    }

    /**
     * @inheritDoc
     */
    public function isTransient($className)
    {
        return  $this->wrapped->isTransient($className);
    }

    /**
     * Returns an array of all the loaded metadata currently in memory.
     *
     * @return ClassMetadata[]
     */
    public function getLoadedMetadata()
    {
        return $this->wrapped->getLoadedMetadata();
    }

    /**
     * @param ClassMetadata $class
     *
     * @return ClassMetadata
     */
    protected function transformMetadata($class)
    {
        $class = clone $class;

        $className = $class->getName();

        $ghostClassName =$class->name;
//        $class->namespace = 'Opifer\\Revisions\\Entity';
//         = $ghostClassName;
//        $class->rootEntityName = $class->name;
        $class->inheritanceType = ClassMetadataInfo::INHERITANCE_TYPE_NONE;

        $class->parentClasses = null;
        $class->discriminatorMap = null;
        $class->discriminatorColumn = null;

        $class->setPrimaryTable(['name' => $class->getTableName() . '_revisions']);
//        $this->name = $entityName;
//        $this->rootEntityName = $entityName;

        foreach ($class->fieldMappings as $key => $fieldMapping) {
            $class->fieldMappings[$fieldMapping['fieldName']]['inherited'] = $ghostClassName;
            $class->fieldMappings[$fieldMapping['fieldName']]['declared'] = $ghostClassName;

            if (! $this->annotationReader->isPropertyRevised($className, $fieldMapping['fieldName']) &&
                ! $class->isIdentifier($fieldMapping['fieldName'])) {
                unset($class->fieldMappings[$fieldMapping['fieldName']]);
                unset($class->fieldNames[$fieldMapping['columnName']]);
                unset($class->columnNames[$fieldMapping['fieldName']]);
                unset($class->reflFields[$fieldMapping['fieldName']]);
            }
        }

        foreach ($class->associationMappings as $key => $associationMapping) {
            $class->associationMappings[$associationMapping['fieldName']]['inherited'] = $ghostClassName;
            $class->associationMappings[$associationMapping['fieldName']]['declared'] = $ghostClassName;

            if (! $this->annotationReader->isPropertyRevised($className, $associationMapping['fieldName']) &&
                ! $class->isIdentifier($associationMapping['fieldName'])) {
                unset($class->associationMappings[$associationMapping['fieldName']]);
                unset($class->reflFields[$associationMapping['fieldName']]);
            }
        }

//        if (! isset($class->fieldMappings['revisionId'])) {
//            $revisionMapping = [
//                'fieldName' => 'revisionId',
//                'columnName' => 'revision_id',
//                'type' => 'integer',
//            ];
//
//            $class->mapField($revisionMapping);
//            $this->addReflectionField($class, $revisionMapping['fieldName']);
//
//            $revisionMapping = [
//                'fieldName' => 'revisionType',
//                'columnName' => 'rev_type',
//                'type' => 'string',
//            ];
//
//            $class->mapField($revisionMapping);
//            $this->addReflectionField($class, $revisionMapping['fieldName']);
//
//            $revisionMapping = [
//                'fieldName' => 'draft',
//                'columnName' => 'draft',
//                'type' => 'boolean',
//            ];
//
//            $class->mapField($revisionMapping);
//            $this->addReflectionField($class, $revisionMapping['fieldName']);
//        }

//        $mock = new $ghostClassName;
//        foreach ($class->reflFields as $key => $reflectionProperty) {
//            $mock->$key = null;
//            $class->reflFields[$key] = new \ReflectionProperty($mock, $key);
//        }

        return $class;
    }

    protected function addReflectionField(ClassMetadata &$class, $fieldName)
    {
        $className = $class->getName();
        $reflClass = new \ReflectionClass($className);

        if ($reflClass->isAbstract()) {
            return;
        }

        $mock = new $className;

        if (! property_exists($className, $fieldName)) {
            $mock->$fieldName = null;
        }

        $class->reflFields[$fieldName] = new \ReflectionProperty($mock, $fieldName);
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames()
    {
        if ($this->classNames === null) {
            $metadata = $this->getAllMetadata();

            /** @var ClassMetadataInfo $meta */
            foreach ($metadata as $meta) {
                if ($this->annotationReader->isRevised($meta->getName())) {
                    $this->classNames[] = $meta->getName();
                }
            }
        }

        return $this->classNames;
    }

    /**
     * @return AnnotationReader
     */
    public function getAnnotationReader()
    {
        return $this->annotationReader;
    }

    /**
     * @param AnnotationReader $annotationReader
     *
     * @return RevisionMetadata
     */
    public function setAnnotationReader($annotationReader)
    {
        $this->annotationReader = $annotationReader;

        return $this;
    }
}