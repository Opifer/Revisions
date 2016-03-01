<?php

namespace Opifer\Revisions\Mapping;

use Doctrine\Common\Annotations\AnnotationReader as Reader;

/**
 * Reading the Revisions annotations
 *
 * Defined in Opifer\Revisions\Mapping\Annotation
 */
class AnnotationReader
{
    const REVISION = 'Opifer\\Revisions\\Mapping\\Annotation\\Revision';
    const REVISED = 'Opifer\\Revisions\\Mapping\\Annotation\\Revised';

    /** @var AnnotationReader */
    protected $reader;

    public function __construct()
    {
        $this->reader = new Reader();
    }

    /**
     * Get annotations by entity and annotation
     *
     * @param  Object $entity
     * @param  string $annotation
     * @return array
     */
    public function get($entity, $annotation)
    {
        $properties = $this->getProperties($entity);

        $return = array();
        foreach ($properties as $reflectionProperty) {
            $propertyAnnotation = $this->reader->getPropertyAnnotation($reflectionProperty, $annotation);
            if (!is_null($propertyAnnotation) && get_class($propertyAnnotation) == $annotation) {
                $return[$reflectionProperty->name] = $reflectionProperty;
            }
        }

        return $return;
    }

    protected function getProperties($entity, $types = 'public,protected,static'){
        $ref = new \ReflectionClass($entity);
        $props = $ref->getProperties();
        $props_arr = array();

        foreach ($props as $prop) {
            $f = $prop->getName();

            if ($prop->isPublic() and (stripos($types, 'public') === false)) continue;
            if ($prop->isPrivate() and (stripos($types, 'private') === false)) continue;
            if ($prop->isProtected() and (stripos($types, 'protected') === false)) continue;
            if ($prop->isStatic() and (stripos($types, 'static') === false)) continue;

            $props_arr[$f] = $prop;
        }

        if ($parentClass = $ref->getParentClass()) {
            $parent_props_arr = $this->getProperties($parentClass->getName());//RECURSION
            if (count($parent_props_arr) > 0)
                $props_arr = array_merge($parent_props_arr, $props_arr);
        }

        return $props_arr;
    }

    /**
     * Get all entity properties with the 'revised' annotation
     *
     * @param  Object $entity
     * @return array
     */
    public function getRevisedProperties($entity)
    {
        return $this->get($entity, self::REVISED);
    }


    public function isRevised($className)
    {
        return $this->getClassAnnotation($className) !== false;
    }

    protected function getClassAnnotation($className, $name = self::REVISION)
    {
        $class = new \ReflectionClass($className);
        $classAnnotation = $this->reader->getClassAnnotation($class, $name);

        if ($classAnnotation) {
            return $classAnnotation;
        }

        while ($parent = $class->getParentClass()) {
            if ($classAnnotation = $this->reader->getClassAnnotation($parent, $name)) {
                return is_null($classAnnotation) ? false : $classAnnotation;
            }
            $class = $parent;
        }

        return false;
    }


    public function isPropertyRevised($entity, $propertyName)
    {
        return key_exists($propertyName, $this->getRevisedProperties($entity));
    }

    public function isDraft($entity)
    {
        $annotation = $this->getClassAnnotation($entity);

        if ($annotation) {
            return $annotation->draft;
        }

        return false;
    }

    /**
     * Gets all annotations
     *
     * @param  [type] $entity [description]
     * @return [type] [description]
     */
    public function all($entity)
    {
        $reflectionClass = new \ReflectionClass($entity);
        $properties = $reflectionClass->getProperties();

        $class = new \ReflectionClass($entity);

        while ($parent = $class->getParentClass()) {
            $parentProperties = $parent->getProperties();
            $properties = array_merge($parentProperties, $properties);
            $class = $parent;
        }

        $return = array();
        foreach ($properties as $reflectionProperty) {
            $propertyAnnotation = $this->reader->getPropertyAnnotation($reflectionProperty, $this->annotationClass);
            if (!is_null($propertyAnnotation) && $propertyAnnotation->listable) {
                $return[] = array(
                    'property' => $reflectionProperty->name,
                    'type' => $propertyAnnotation->type
                );
            }
        }

        return $return;
    }
}