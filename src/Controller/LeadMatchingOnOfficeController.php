<?php

namespace ContaoEstateManager\LeadMatchingToolOnOffice\Controller;

use ContaoEstateManager\LeadMatchingToolOnOffice\Importer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the LeadMatchingOnOffice routes.
 *
 * @author Daniele Sciannimanica <https://github.com/doishub>
 */
class LeadMatchingOnOfficeController extends Controller
{
    /**
     * Runs the command scheduler. (READ)
     *
     * @param $module
     *
     * @return Response
     */
    public function readAction($module)
    {
        $this->container->get('contao.framework')->initialize();

        switch ($module)
        {
            case 'import':
                return Importer::cronImport();
                break;
            case 'create':
                return Importer::cronCreate();
                break;
            case 'update':
                return Importer::cronUpdate();
                break;
        }

        return new Response('Nothing happen');
    }
}
