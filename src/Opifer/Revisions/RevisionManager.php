<?php

namespace Opifer\Revisions;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Opifer\Revisions\Exception\DeletedException;
use Opifer\Revisions\Mapping\AnnotationReader;
use Opifer\Revisions\Model\Revision;

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
        $result = $this->getRevisionData($entity, [], [], 1);

        if ($result) {
            $latest = reset($result);
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

        $result = $this->getRevisionData($entity, $criteria, $params, 1);

        if ($result) {
            $current = reset($result);
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
        if (!$data) {
            throw new \InvalidArgumentException(sprintf('Revision with id %d not found for entity %s', $revision, get_class($entity)));
        }

        $this->fillObject($entity, $meta, reset($data));

        if ($meta->getReflectionClass()->hasMethod('getDeletedAt') && $entity->getDeletedAt() !== null) {
            $exception = new DeletedException('Entity is deleted in this revision');
            $exception->setEntity($entity);
            throw $exception;
        }

        return $entity;
    }

    protected function fillObject(&$object, ClassMetadataInfo $meta, $data)
    {
        /** @var \ReflectionProperty[] $revisedProperties */
        $revisedProperties = $this->annotationReader->getRevisedProperties($meta->getName());

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
            $property->setValue($object, $value);
        }
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

        $rows = $this->em->getConnection()->fetchAll($sql, $params);

        if (!$rows) {
            return false;
        }
        foreach ($rows as &$row) {
            $row = (array) $row;
            foreach ($row as $column => &$value) {
                try {
                    $this->mapValue($class, $class->getFieldForColumn($column), $value);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $rows;
    }

    public function getRevisions($entity, $criteria = array(), $params = array(), $limit = false)
    {
        $result = $this->getRevisionData($entity, $criteria, $params, $limit);
        /** @var ClassMetadataInfo $meta */
        $meta = $this->getClassMetadata(get_class($entity));
        $revisions  = array();
        foreach ($result as $row) {
            $object = new \stdClass();
            $object->revision_id = $row['revision_id'];
            $object->rev_type = $row['rev_type'];
            $this->fillObject($object, $meta, $row);
            $revisions[] = new Revision($object, $entity);
        }
        return $revisions;
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
