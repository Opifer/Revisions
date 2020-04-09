<?php

namespace Opifer\Revisions\EventListener;

use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\Common\EventSubscriber;

use Opifer\Revisions\Mapping\AnnotationReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SchemaListener implements EventSubscriber
{
    /** @var AnnotationReader */
    protected $annotationReader;

    /** @var ContainerInterface */
    protected $container;

    /**
     * SchemaListener constructor.
     *
     * @param ContainerInterface $container
     * @param AnnotationReader   $annotationReader
     */
    public function __construct(ContainerInterface $container, AnnotationReader $annotationReader)
    {
        $this->container = $container;
        $this->annotationReader = $annotationReader;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            ToolEvents::postGenerateSchemaTable,
            ToolEvents::postGenerateSchema,
        );
    }

    /**
     * @param GenerateSchemaTableEventArgs $eventArgs
     *
     * @throws \Exception
     */
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs)
    {
        $cm = $eventArgs->getClassMetadata();

        if (!$this->annotationReader->isRevised($cm->name)) {
            $revised = false;
            if ($cm->isInheritanceTypeJoined() && $cm->rootEntityName == $cm->name) {
                foreach ($cm->subClasses as $subClass) {
                    if ($this->annotationReader->isRevised($subClass)) {
                        $revised = true;
                    }
                }
            }

            if (!$revised) {
                return;
            }
        }

        if (!in_array($cm->inheritanceType, array(ClassMetadataInfo::INHERITANCE_TYPE_NONE, ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE))) {
            throw new \Exception(sprintf('Inheritance type "%s" is not yet supported', $cm->inheritanceType));
        }

        $schema = $eventArgs->getSchema();
        $entityTable = $eventArgs->getClassTable();
        $revisionTable = $schema->createTable(
            $entityTable->getName() . '_revisions'
        );
        $revisionTable->addColumn('revision_id', 'integer');
        $revisionTable->addColumn('rev_type', 'string', array('length' => 4));

        foreach ($entityTable->getColumns() as $column) {
            if (!in_array($column->getName(), $cm->identifier) && !$this->isColumnRevised($cm, $column->getName())) {
                continue;
            }

            /* @var Column $column */
            $revisionTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
                $column->toArray(),
                array('notnull' => false, 'autoincrement' => false)
            ));
        }

        $pkColumns = $entityTable->getPrimaryKey()->getColumns();
        $pkColumns[] = 'revision_id';
        $revisionTable->setPrimaryKey($pkColumns);
    }

    /**
     * @param GenerateSchemaEventArgs $eventArgs
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs)
    {
        $schema = $eventArgs->getSchema();
        $table = $schema->createTable('revisions');

        $table->addColumn('id', 'integer', array(
            'autoincrement' => true,
        ));
        $table->addColumn('timestamp', 'datetime');
        $table->addColumn('username', 'string');
        $table->addColumn('draft', 'boolean');

        $table->setPrimaryKey(array('id'));
    }

    /**
     * @param ClassMetadataInfo $cm
     * @param string            $columnName
     * @return bool
     */
    protected function isColumnRevised(ClassMetadataInfo $cm, $columnName)
    {
        try {
            $propertyName = $cm->getFieldForColumn($columnName);
            return $this->annotationReader->isPropertyRevised($cm->name, $propertyName);
        } catch (MappingException $e) {
            if ($cm->isInheritanceTypeSingleTable()) {
                foreach ($cm->subClasses as $subClass) {
                    $subClassMeta = $this->getClassMetaData($subClass);

                    try {
                        $propertyName = $subClassMeta->getFieldForColumn($columnName);
                        if ($this->annotationReader->isPropertyRevised($subClassMeta->name, $propertyName)) {
                            // If the given property is revised on this subclass, we return true.
                            // If not, we go on to the next subclass to see if that maybe has marked the given
                            // property as `revised`
                            return true;
                        }
                    } catch (MappingException $e) {
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param string $entity
     *
     * @return mixed
     */
    protected function getClassMetaData($entity) {
        return $this->container->get('doctrine.orm.entity_manager')->getClassMetadata($entity);
    }
}
