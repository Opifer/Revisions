<?php

namespace Opifer\Revisions;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\DBAL\Types\Type;
use Opifer\Revisions\Exception\DeletedException;
use Opifer\Revisions\Mapping\AnnotationReader;
use Opifer\Revisions\Mapping\RevisionMetadata;

class RevisionManager
{
    /* @var EntityManagerInterface */
    protected $em;

    /* @var AnnotationReader */
    private $annotationReader;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $em
     * @param AnnotationReader       $annotationReader
     */
    public function __construct(EntityManagerInterface $em, AnnotationReader $annotationReader)
    {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param $entity
     *
     * @return bool
     */
    public function getLatestRevision($entity)
    {
        $latest = $this->getRevisionData($entity, [], [], 1);

        if ($latest) {
            return $latest['revision_id'];
        }

        return false;
    }


    public function getCurrentRevision($entity)
    {
        $criteria = array();
        $params = array();

        // Exclude "future" draft revisions
        if ($this->annotationReader->isDraft($entity)) {
            $criteria[] = '((r.updated_at <= e.updated_at AND r.deleted_at IS NULL) OR (e.created_at IS NULL AND e.created_at IS NOT NULL) OR (e.deleted_at IS NOT NULL AND r.deleted_at <= e.updated_at))';
        }

        $current = $this->getRevisionData($entity, $criteria, $params, 1);

        if ($current) {
            return $current['revision_id'];
        }

        return false;
    }

    /**
     * @param object $entity
     * @param int    $revision
     *
     * @throws \Exception
     *
     * @return object
     */
    public function revert(&$entity, $revision)
    {
        /** @var ClassMetadataInfo $meta */
        $meta = $this->getClassMetadata(get_class($entity));

        $data = $this->getRevisionData($entity, ['revision_id = :revisionId'], ['revisionId' => $revision], 1);

        /** @var \ReflectionProperty[] $revisedProperties */
        $revisedProperties = $this->annotationReader->getRevisedProperties($entity);

        $exception = null;
        foreach ($revisedProperties as $field => $property) {
            if (! key_exists($field, $revisedProperties)) {
                continue;
            }

            if (isset($meta->fieldMappings[$field])) {
                $columnName = $meta->fieldMappings[$field]['columnName'];
            } elseif (isset($meta->associationMappings[$field])) {
                $columnName = $meta->associationMappings[$field]['joinColumns'][0]['name'];
            } else {
                throw new \Exception('Cannot find column name for' . $field);
            }

            $value = $data[$columnName];

            $property = $meta->getReflectionProperty($field);
            $property->setValue($entity, $value);

            if ($field == 'deletedAt' && $value !== null) {
                $exception = new DeletedException('Entity is deleted in this revision');
            }
        }

        if ($exception) {
            $exception->setEntity($entity);
            throw $exception;
        }

        return $entity;
    }

    /**
     * Finds the revision id for an entity that contains draft changes and returns
     * false if entity is up-to-date with latest revision.
     *
     * @param $entity
     *
     * @return int|null
     */
    public function getDraftRevision($entity)
    {
        $currentRevision = $this->getCurrentRevision($entity);
        $latestRevision = $this->getLatestRevision($entity);

        return ($currentRevision < $latestRevision) ? $latestRevision : null;
    }

    public function setRevisionDraft($revision, $draft)
    {
        $type = Type::getType(Type::BOOLEAN);
        $draftValue = $type->convertToPHPValue($draft, $this->em->getConnection()->getDatabasePlatform());

        $sql = "UPDATE revisions SET draft = ? WHERE id = ?";

        $params = [$draftValue, $revision];

        $this->em->getConnection()->executeUpdate($sql, $params);
    }


    protected function getRevisionData($entity, $criteria = array(), $params = array(), $limit = false)
    {
        $class = $this->getClassMetadata(get_class($entity));

        $i = 0;
        foreach ($class->getIdentifierFieldNames() as $fieldName) {
            $i++;
            $criteria[] = 'r.'.$class->getColumnName($fieldName) . ' = :id'.$i;
            $params['id'.$i] = $class->getFieldValue($entity, $fieldName);
        }
//
//        foreach ($criteria as $fieldName => $value) {
//            $criteria[] = $class->getColumnName($fieldName);
//            $params[] = $value;
//        }

        $sql = 'SELECT r.* FROM ' . $class->getTableName() . '_revisions r';
        $sql .= ' INNER JOIN ' . $class->getTableName() . ' e ON r.id = e.id';
        $sql .= ' INNER JOIN revisions rv ON r.revision_id = rv.id';
        $sql .= ' WHERE ' . implode(' AND ', $criteria);
        $sql .= ' ORDER BY r.revision_id DESC ';

        if ($limit) {
            $sql .= ' LIMIT 0,' . $limit;
        }

        $row = $this->em->getConnection()->fetchAssoc($sql, $params);

        if (!$row) {
            return false;
        }
        foreach ($row as $column => &$value) {
            try {
                $this->mapValue($class, $class->getFieldForColumn($column), $value);
            } catch (\Exception $e) {
                continue;
            }
        }

        return $row;
    }


    /**
     * @param ClassMetadataInfo $meta
     * @param string            $field
     * @param mixed             $value
     */
    protected function mapValue(ClassMetadataInfo $meta, $field, &$value)
    {
        if ($meta->isSingleValuedAssociation($field)) {
            $mapping = $meta->getAssociationMapping($field);
            $value   = $value ? $this->em->getReference($mapping['targetEntity'], $value) : null;

            return;
        }

        $type = Type::getType($meta->fieldMappings[$field]['type']);
        $value = $type->convertToPHPValue($value, $this->em->getConnection()->getDatabasePlatform());
    }

    /**
     * @param string $className
     *
     * @return ClassMetadataInfo
     */
    protected function getClassMetadata($className)
    {
        $class = clone $this->em->getClassMetadata($className);

        if (! isset($class->fieldMappings['revisionId'])) {
            $revisionMapping = [
                'fieldName' => 'revisionId',
                'columnName' => 'revision_id',
                'type' => 'integer',
            ];

            $class->mapField($revisionMapping);

            $revisionMapping = [
                'fieldName' => 'revisionType',
                'columnName' => 'rev_type',
                'type' => 'string',
            ];

            $class->mapField($revisionMapping);

            $revisionMapping = [
                'fieldName' => 'draft',
                'columnName' => 'draft',
                'type' => 'boolean',
            ];

            $class->mapField($revisionMapping);
        }

        return $class;
    }
}