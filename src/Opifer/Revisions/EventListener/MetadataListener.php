<?php

namespace Opifer\Revisions\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Class MetadataListener
 *
 * @package Opifer\Revisions\EventListener
 */
class MetadataListener implements EventSubscriber
{
//    /** @var BlockManager $pool */
//    protected $blockManager;
//
//    /**
//     * Constructor
//     *
//     * @param Pool $pool
//     */
//    public function __construct(BlockManager $blockManager)
//    {
//        $this->blockManager = $blockManager;
//    }
    /**
     * @inheritDoc
     */
    public function getSubscribedEvents()
    {
        return [
            'loadClassMetadata',
        ];
    }

    /**
     * loadClassMetadata event
     *
     * @param LoadClassMetadataEventArgs $args
     *
     * @return void
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        $metadata = $args->getClassMetadata();

        if (strpos($metadata->name, 'Revisions') !== false) {
            $point = true;
        }
    }

}
