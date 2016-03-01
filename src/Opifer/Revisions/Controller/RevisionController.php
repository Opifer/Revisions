<?php

namespace Opifer\Revisions\Controller;

use Opifer\CmsBundle\Entity\Content;
use Opifer\ContentBundle\Block\BlockManager;
use Opifer\ContentBundle\Entity\HtmlBlock;
use Opifer\ContentBundle\Model\ContentManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Revision Controller
 */
class RevisionController extends Controller
{
    /**
     * Index action.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->render($this->getParameter('opifer_revisions.revision_index_view'));
    }
}
