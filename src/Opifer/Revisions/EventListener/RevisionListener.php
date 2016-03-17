<?php

namespace Opifer\Revisions\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Persisters\Entity\BasicEntityPersister;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Doctrine\ORM\UnitOfWork;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Opifer\Revisions\Mapping\AnnotationReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RevisionListener implements EventSubscriber
{
    /**
     * @var AnnotationReader
     */
    protected $annotationReader;

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var AbstractPlatform
     */
    protected $platform;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var array
     */
    protected $revisionSQL = array();

    /**
     * @var UnitOfWork
     */
    protected $uow;

    /**
     * @var integer
     */
    protected $revisionId = null;

    /**
     * @var array
     */
    protected $revisionIds;

    /** @var bool */
    protected $draft = false;

    /**
     * @var array
     */
    protected $extraUpdates = array();

    /** @var ContainerInterface */
    protected $container;

    /** @var string */
    protected $username;

    /** @var array */
    protected $insertDrafts = array();

    public function __construct(ContainerInterface $container, AnnotationReader $annotationReader)
    {
        $this->container = $container;
        $this->annotationReader = $annotationReader;
    }

    public function getSubscribedEvents()
    {
        return array(Events::onFlush, Events::postPersist, Events::postUpdate, Events::postFlush, SoftDeleteableListener::POST_SOFT_DELETE);
    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();

        $class = $this->em->getClassMetadata(get_class($entity));
        if (! $this->annotationReader->isRevised($class->name, true)) {
            return;
        }

        $this->saveRevisionEntityData($class, $this->getOriginalEntityData($entity), 'INS');
    }

    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();

        $meta = $this->em->getClassMetadata(get_class($entity));
        if (! $this->annotationReader->isRevised($meta->name, true)) {
            return;
        }

        $changeSet = $this->uow->getEntityChangeSet($entity);

        foreach ($changeSet as $field => $value) {
            if (! $this->annotationReader->isPropertyRevised($entity, $field)) {
                unset($changeSet[$field]);
            }
        }

        if (count($changeSet) == 0) {
            return;
        }

        $entityData = array_merge($this->getOriginalEntityData($entity), $this->uow->getEntityIdentifier($entity));
        $this->saveRevisionEntityData($meta, $entityData, 'UPD');
    }

    /**
     * TODO: set deletedAt field through reflection and loading softdeletable config
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postSoftDelete(LifecycleEventArgs $eventArgs) {
        $entity = $eventArgs->getEntity();
        $this->em = $eventArgs->getEntityManager();
        $this->uow = $this->em->getUnitOfWork();

        if ($this->annotationReader->isRevised(get_class($entity), true) &&
            $this->annotationReader->isDraft($entity) &&
            $entity->isDraft()) {

            $this->setRevisionInfo($entity);

            $this->extraUpdates[spl_object_hash($entity)] = $entity;

            $persister = $this->uow->getEntityPersister(get_class($entity));
            $this->updateData[spl_object_hash($entity)] = $this->prepareUpdateData($persister, $entity);

            $fieldName = 'deletedAt';

            $reflProp = new \ReflectionProperty($entity, $fieldName);
            $reflProp->setAccessible(true);

            $oldValue = $reflProp->getValue($entity);
            $reflProp->setValue($entity, null);

            $this->em->persist($entity);

            $this->uow->scheduleExtraUpdate($entity, array(
                $fieldName => array($oldValue, null),
            ));

        }
    }

    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $this->em = $eventArgs->getEntityManager();
        $this->conn = $this->em->getConnection();
        $this->uow = $this->em->getUnitOfWork();
        $this->platform = $this->conn->getDatabasePlatform();
        $this->revisionId = null; // reset revision
        $this->draft = false;

        $processedEntities = array();

        foreach ($this->uow->getScheduledEntityDeletions() AS $entity) {
            if (! $this->annotationReader->isRevised(get_class($entity), true)) {
                continue;
            }

            //doctrine is fine deleting elements multiple times. We are not.
            $hash = $this->getHash($entity);

            if (in_array($hash, $processedEntities)) {
                continue;
            }

            $processedEntities[] = $hash;

            if ($this->annotationReader->isDraft($entity) && $entity->isDraft()) {
                $this->resetRevisedData($entity);
            } else {
                $this->extraUpdates[spl_object_hash($entity)] = $entity;

                $persister = $this->uow->getEntityPersister(get_class($entity));
                $this->updateData[spl_object_hash($entity)] =  $this->prepareUpdateData($persister, $entity);

                $entityData = array_merge($this->getOriginalEntityData($entity), $this->uow->getEntityIdentifier($entity));
                $this->saveRevisionEntityData($this->em->getClassMetadata(get_class($entity)), $entityData, 'DEL');
            }
        }

        foreach ($this->uow->getScheduledEntityInsertions() as $entity) {
            if (! $this->annotationReader->isRevised(get_class($entity), true)) {
                continue;
            }

            $this->setRevisionInfo($entity);

            $this->extraUpdates[spl_object_hash($entity)] = $entity;

            $persister = $this->uow->getEntityPersister(get_class($entity));
            $this->updateData[spl_object_hash($entity)] =  $this->prepareUpdateData($persister, $entity);

            if ($this->annotationReader->isDraft($entity) && $entity->isDraft()) {
                $this->insertDrafts[spl_object_hash($entity)] = $entity;
                $this->resetRevisedData($entity);
                $this->uow->recomputeSingleEntityChangeSet($this->em->getClassMetadata(get_class($entity)), $entity);
            }
        }

        foreach ($this->uow->getScheduledEntityUpdates() as $entity) {
            if (! $this->annotationReader->isRevised(get_class($entity), true)) {
                continue;
            }

            $this->setRevisionInfo($entity);

            $this->extraUpdates[spl_object_hash($entity)] = $entity;

            $persister = $this->uow->getEntityPersister(get_class($entity));
            $this->updateData[spl_object_hash($entity)] =  $this->prepareUpdateData($persister, $entity);

            if ($this->annotationReader->isDraft($entity) && $entity->isDraft()) {
                $this->resetRevisedData($entity);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        $em = $eventArgs->getEntityManager();

        foreach ($this->extraUpdates as $entity) {
            $className = get_class($entity);
            $meta = $em->getClassMetadata($className);

            $updateData = $this->updateData[spl_object_hash($entity)];

            if (! isset($updateData[$meta->table['name']]) || ! $updateData[$meta->table['name']]) {
                continue;
            }

            foreach ($updateData[$meta->table['name']] as $field => $value) {
                if (! $this->annotationReader->isPropertyRevised($entity, $meta->getFieldForColumn($field))) {
                    continue;
                }

                $sql = 'UPDATE ' . $this->getTableName($meta) . ' ' .
                    'SET ' . $field . ' = ? ' .
                    'WHERE revision_id = ? ';

                $params = array($value, $this->getRevisionId());

                $types = array();

                if (in_array($field, $meta->columnNames)) {
                    $types[] = $meta->fieldMappings[$meta->getFieldForColumn($field)]['type'];
                } else {
                    //try to find column in association mappings
                    $type = null;

                    foreach ($meta->associationMappings as $mapping) {
                        if (isset($mapping['joinColumns'])) {
                            foreach ($mapping['joinColumns'] as $definition) {
                                if ($definition['name'] == $field) {
                                    $targetTable = $em->getClassMetadata($mapping['targetEntity']);
                                    $type = $targetTable->getTypeOfColumn($definition['referencedColumnName']);
                                }
                            }
                        }
                    }

                    if (is_null($type)) {
                        throw new \Exception(
                            sprintf('Could not resolve database type for column "%s" during extra updates', $field)
                        );
                    }
                }

                $types[] = $this->getRevisionIdFieldType();

                foreach ($meta->identifier AS $idField) {
                    if (isset($meta->fieldMappings[$idField])) {
                        $columnName = $meta->fieldMappings[$idField]['columnName'];
                        $types[] = $meta->fieldMappings[$idField]['type'];
                    } elseif (isset($meta->associationMappings[$idField])) {
                        $columnName = $meta->associationMappings[$idField]['joinColumns'][0];
                        $types[] = $meta->associationMappings[$idField]['type'];
                    }

                    $params[] = $meta->reflFields[$idField]->getValue($entity);

                    $sql .= 'AND ' . $columnName . ' = ?';
                }

                $this->em->getConnection()->executeQuery($sql, $params, $types);
            }
        }

        foreach ($this->insertDrafts as $hash => $entity) {
            if ($this->annotationReader->isDraft($entity) && $entity->isDraft()) {
                $this->em->detach($entity);
            }
        }
    }

    /**
     * get original entity data, including versioned field, if "version" constraint is used
     *
     * @param mixed $entity
     *
     * @return array
     */
    private function getOriginalEntityData($entity)
    {
        $class = $this->em->getClassMetadata(get_class($entity));
        $data = $this->uow->getOriginalEntityData($entity);
        if ($class->isVersioned) {
            $versionField = $class->versionField;
            $data[$versionField] = $class->reflFields[$versionField]->getValue($entity);
        }

        return $data;
    }

    private function getRevisionId()
    {
        if ($this->revisionId === null) {
            $this->conn->insert(
                'revisions',
                array(
                    'timestamp' => date_create('now'),
                    'username' => $this->getUsername() ?: 'UNKNOWN',
                    'draft' => $this->draft,
                ),
                array(
                    Type::DATETIME,
                    Type::STRING,
                    Type::BOOLEAN,
                )
            );

            $sequenceName = $this->platform->supportsSequences()
                ? $this->platform->getIdentitySequenceName($this->config->getRevisionTableName(), 'id')
                : null;
            $this->revisionId = $this->conn->lastInsertId($sequenceName);
        }

        return $this->revisionId;
    }

    protected function isDraft()
    {
        return $this->draft;
    }

    /**
     * @param ClassMetadata $meta
     *
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getRevisionSQL($meta)
    {
        if (! isset($this->revisionSQL[$meta->name])) {
            $placeholders = ['?', '?'];
            $values = ['revision_id', 'rev_type'];
            $updates = array();
            $tableName = $this->getTableName($meta);

            $fields = array();

            foreach ($meta->associationMappings as $field => $assoc) {
                if ($meta->isInheritanceTypeJoined() && $meta->isInheritedAssociation($field)) {
                    continue;
                }

                if (! $this->annotationReader->isPropertyRevised($meta->name, $field)) {
                    continue;
                }

                if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {
                    foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
                        $fields[$sourceCol] = true;
                        $values[] = $sourceCol;
                        $placeholders[] = '?';
                    }
                }
            }

            foreach ($meta->fieldNames as $field) {
                if (array_key_exists($field, $fields)) {
                    continue;
                }

                if ($meta->isInheritanceTypeJoined()
                    && $meta->isInheritedField($field)
                    && ! $meta->isIdentifier($field)
                ) {
                    continue;
                }

                if (! $this->annotationReader->isPropertyRevised($meta->name, $field)
                    && ! $meta->isIdentifier($field)) {
                    continue;
                }

                $type = Type::getType($meta->fieldMappings[$field]['type']);
                $placeholders[] = (! empty($meta->fieldMappings[$field]['requireSQLConversion']))
                    ? $type->convertToDatabaseValueSQL('?', $this->platform)
                    : '?';
                $values[] = $meta->getQuotedColumnName($field, $this->platform);
                $updates[] = $meta->getQuotedColumnName($field, $this->platform);
            }

            if (($meta->isInheritanceTypeJoined() && $meta->rootEntityName == $meta->name)
//                || $meta->isInheritanceTypeSingleTable()
            ) {
                $values[] = $meta->discriminatorColumn['name'];
                $updates[] = $meta->discriminatorColumn['name'];
                $placeholders[] = '?';
            }

            $sql = "INSERT INTO " . $tableName . " (".implode(", ", $values);
            $sql .= ") VALUES (" . implode(", ", $placeholders) . ")";
//            $sql .= " ON DUPLICATE KEY UPDATE ";
//            array_walk($updates, function (&$value) { $value = sprintf("%s=VALUES(%s)", $value, $value); });
//            $sql .= implode(", ", $updates);

            $this->revisionSQL[$meta->name] = $sql;
        }

        return $this->revisionSQL[$meta->name];
    }

    /**
     * @param ClassMetadata $class
     * @param array         $entityData
     * @param string        $revType
     * @param boolean       $draft
     */
    private function saveRevisionEntityData($class, $entityData, $revType)
    {
        $version = $this->getRevisionId();
        $params = array($version ? $version : 1, $revType);
        $types = array(Type::INTEGER, Type::STRING);

        $fields = array();



        foreach ($class->associationMappings AS $field => $assoc) {
            if ($class->isInheritanceTypeJoined() && $class->isInheritedAssociation($field)) {
                continue;
            }
            if (! (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide'])) {
                continue;
            }

            if (!$this->annotationReader->isPropertyRevised($class->name, $field)) {
                continue;
            }

            $data = isset($entityData[$field]) ? $entityData[$field] : null;
            $relatedId = false;

            if ($data !== null && $this->uow->isInIdentityMap($data)) {
                $relatedId = $this->uow->getEntityIdentifier($data);
            }

            $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);

            foreach ($assoc['sourceToTargetKeyColumns'] as $sourceColumn => $targetColumn) {
                $fields[$sourceColumn] = true;
                if ($data === null) {
                    $params[] = null;
                    $types[] = \PDO::PARAM_STR;
                } else {
                    $params[] = $relatedId ? $relatedId[$targetClass->fieldNames[$targetColumn]] : null;
                    $types[] = $targetClass->getTypeOfColumn($targetColumn);
                }
            }
        }

        foreach ($class->fieldNames AS $field) {
            if (array_key_exists($field, $fields)) {
                continue;
            }

            if ($class->isInheritanceTypeJoined()
                && $class->isInheritedField($field)
                && ! $class->isIdentifier($field)
            ) {
                continue;
            }

            if (!$this->annotationReader->isPropertyRevised($class->name, $field) && !$class->isIdentifier($field)) {
                continue;
            }

            $params[] = isset($entityData[$field]) ? $entityData[$field] : null;
            $types[] = $class->fieldMappings[$field]['type'];
        }

        if ($class->isInheritanceTypeJoined()
            && $class->name == $class->rootEntityName
        ) {
            $params[] = $entityData[$class->discriminatorColumn['name']];
            $types[] = $class->discriminatorColumn['type'];
        }

        if ($class->isInheritanceTypeJoined() && $class->name != $class->rootEntityName) {
            $entityData[$class->discriminatorColumn['name']] = $class->discriminatorValue;
            $this->saveRevisionEntityData(
                $this->em->getClassMetadata($class->rootEntityName),
                $entityData,
                $revType
            );
        }

        $this->conn->executeUpdate($this->getRevisionSQL($class), $params, $types);
    }

    /**
     * @param $entity
     *
     * @return string
     */
    private function getHash($entity)
    {
        return implode(
            ' ',
            array_merge(
                array(get_class($entity)),
                $this->uow->getEntityIdentifier($entity)
            )
        );
    }

    /**
     * Modified version of BasicEntityPersister::prepareUpdateData()
     * git revision d9fc5388f1aa1751a0e148e76b4569bd207338e9 (v2.5.3)
     *
     * @license MIT
     *
     * @author  Roman Borschel <roman@code-factory.org>
     * @author  Giorgio Sironi <piccoloprincipeazzurro@gmail.com>
     * @author  Benjamin Eberlei <kontakt@beberlei.de>
     * @author  Alexander <iam.asm89@gmail.com>
     * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
     * @author  Rob Caiger <rob@clocal.co.uk>
     * @author  Simon Mönch <simonmoench@gmail.com>
     *
     * @param EntityPersister|BasicEntityPersister $persister
     * @param                 $entity
     *
     * @return array
     */
    private function prepareUpdateData($persister, $entity)
    {
        $uow = $this->em->getUnitOfWork();
        $classMetadata = $persister->getClassMetadata();

        $versionField = null;
        $result = array();

        if (($versioned = $classMetadata->isVersioned) != false) {
            $versionField = $classMetadata->versionField;
        }

        foreach ($uow->getEntityChangeSet($entity) as $field => $change) {
            if (isset($versionField) && $versionField == $field) {
                continue;
            }

            if (isset($classMetadata->embeddedClasses[$field])) {
                continue;
            }

            $newVal = $change[1];

            if ( ! isset($classMetadata->associationMappings[$field])) {
                $columnName = $classMetadata->columnNames[$field];
                $result[$persister->getOwningTable($field)][$columnName] = $newVal;

                continue;
            }

            $assoc = $classMetadata->associationMappings[$field];

            // Only owning side of x-1 associations can have a FK column.
            if ( ! $assoc['isOwningSide'] || ! ($assoc['type'] & ClassMetadata::TO_ONE)) {
                continue;
            }

            if ($newVal !== null) {
                if ($uow->isScheduledForInsert($newVal)) {
                    $newVal = null;
                }
            }

            $newValId = null;

            if ($newVal !== null) {
                if (! $uow->isInIdentityMap($newVal)) {
                    continue;
                }

                $newValId = $uow->getEntityIdentifier($newVal);
            }

            $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);
            $owningTable = $persister->getOwningTable($field);

            foreach ($assoc['joinColumns'] as $joinColumn) {
                $sourceColumn = $joinColumn['name'];
                $targetColumn = $joinColumn['referencedColumnName'];

                $result[$owningTable][$sourceColumn] = $newValId
                    ? $newValId[$targetClass->getFieldForColumn($targetColumn)]
                    : null;
            }
        }

        return $result;
    }


    protected function resetRevisedData($object)
    {
        $meta   = $this->em->getClassMetadata(get_class($object));
        /** @var UnitOfWork $uow */
        $uow       = $this->em->getUnitOfWork();

        /** @var \ReflectionProperty[] $revisedProperties */
        $revisedProperties = $this->annotationReader->getRevisedProperties($object);

        if (isset($this->insertDrafts[spl_object_hash($object)])) {
            $object->setCreatedAt(null);
        }

        foreach ($uow->getEntityChangeSet($object) as $field => $changes) {
            if (! key_exists($field, $revisedProperties)) {
                continue;
            }

            $value = $changes[0];
            $property = $meta->getReflectionProperty($field);
            $property->setValue($object, $value);
        }
    }

    /**
     * @param object $entity
     */
    protected function setRevisionInfo($entity)
    {
        if ($this->annotationReader->isDraft($entity) && $entity->isDraft()) {
            $this->draft = true;
        }
    }

    /**
     * @return EntityManager
     */
    protected function getManager()
    {
        return $this->container->get('doctrine.orm.entity_manager');
    }

    /**
     * @param ClassMetadata $meta
     *
     * @return string
     */
    protected function getTableName($meta)
    {
        return $meta->getTableName() .'_revisions';
    }

    /**
     * @return string
     */
    protected function getRevisionIdFieldType()
    {
        return 'integer';
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return RevisionListener
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
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
     */
    public function setAnnotationReader($annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }
}