<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\AdminBundle\Controller\GDPR;

use Pimcore\Bundle\AdminBundle\GDPR\DataProvider\DataObjects;
use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Pimcore\Controller\KernelControllerEventInterface;
use Pimcore\Model\DataObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DataObjectController
 *
 * @Route("/data-object")
 *
 * @internal
 */
final class DataObjectController extends \Pimcore\Bundle\AdminBundle\Controller\AdminController implements KernelControllerEventInterface
{
    /**
     * {@inheritdoc}
     */
    public function onKernelControllerEvent(ControllerEvent $event)
    {
        $isMasterRequest = $event->isMasterRequest();
        if (!$isMasterRequest) {
            return;
        }

        $this->checkActionPermission($event, 'gdpr_data_extractor');
    }

    /**
     * @Route("/search-data-objects", name="pimcore_admin_gdpr_dataobject_searchdataobjects", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function searchDataObjectsAction(Request $request, DataObjects $service)
    {
        $allParams = array_merge($request->request->all(), $request->query->all());

        $result = $service->searchData(
            (int)$allParams['id'],
            strip_tags($allParams['firstname']),
            strip_tags($allParams['lastname']),
            strip_tags($allParams['email']),
            (int)$allParams['start'],
            (int)$allParams['limit'],
            $allParams['sort'] ?? null
        );

        return $this->adminJson($result);
    }

    /**
     * @Route("/export", name="pimcore_admin_gdpr_dataobject_exportdataobject", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function exportDataObjectAction(Request $request, DataObjects $service)
    {
        $object = DataObject::getById($request->get('id'));
        if (!$object->isAllowed('view')) {
            throw new \Exception('export denied');
        }

        $exportResult = $service->doExportData($object);

        $json = $this->encodeJson($exportResult, [], JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        $jsonResponse = new JsonResponse($json, 200, [
            'Content-Disposition' => 'attachment; filename="export-data-object-' . $object->getId() . '.json"',
        ], true);

        return $jsonResponse;
    }
}
