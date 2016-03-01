<?php

namespace Opifer\Revisions\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * Revision annotation
 *
 * @Annotation
 * @Target({"CLASS"})
 */
final class Revision extends Annotation
{
    /** @var bool */
    public $draft = false;
}