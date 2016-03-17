[![Build Status](https://travis-ci.org/Opifer/Revisions.svg?branch=master)](https://travis-ci.org/Opifer/Revisions)

Revisions
==========


## Revision Entity example:

``` php
<?php
namespace Entity;

use Opifer\Revisions\Mapping\Annotation as Revisions;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="articles")
 * @ORM\Entity
 */
class Article
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /**
     * @Revisions\Revised
     * @ORM\Column(length=128)
     */
    private $title;

    /**
     * @Revisions\Revised
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @Revisions\Draft
     */
    private $draft;
}
```