<?php

namespace Ojs\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Ojs\CmsBundle\Form\Type\AnnouncementType;
use Ojs\AdminBundle\Entity\AdminAnnouncement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Symfony\Component\Form\FormTypeInterface;
use Ojs\ApiBundle\Exception\InvalidFormException;

class AnnouncementRestController extends FOSRestController
{
    /**
     * List all Announcements.
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     200 = "Returned when successful"
     *   }
     * )
     *
     * @Annotations\QueryParam(name="offset", requirements="\d+", nullable=true, description="Offset from which to start listing Announcements.")
     * @Annotations\QueryParam(name="limit", requirements="\d+", default="5", description="How many Announcements to return.")
     *
     *
     * @param Request               $request      the request object
     * @param ParamFetcherInterface $paramFetcher param fetcher service
     *
     * @return array
     */
    public function getAnnouncementsAction(Request $request, ParamFetcherInterface $paramFetcher)
    {
        $offset = $paramFetcher->get('offset');
        $offset = null == $offset ? 0 : $offset;
        $limit = $paramFetcher->get('limit');
        return $this->container->get('ojs_api.announcement.handler')->all($limit, $offset);
    }

    /**
     * Get single Announcement.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Gets a Announcement for a given id",
     *   output = "Ojs\AnnouncementBundle\Entity\Announcement",
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     404 = "Returned when the Announcement is not found"
     *   }
     * )
     *
     * @param int     $id      the Announcement id
     *
     * @return array
     *
     * @throws NotFoundHttpException when Announcement not exist
     */
    public function getAnnouncementAction($id)
    {
        $entity = $this->getOr404($id);
        return $entity;
    }

    /**
     * Presents the form to use to create a new Announcement.
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     200 = "Returned when successful"
     *   }
     * )
     *
     * @return FormTypeInterface
     */
    public function newAnnouncementAction()
    {
        return $this->createForm(new AnnouncementType(), null, ['csrf_protection' => false]);
    }

    /**
     * Create a Announcement from the submitted data.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Creates a new Announcement from the submitted data.",
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     400 = "Returned when the form has errors"
     *   }
     * )
     *
     * @param Request $request the request object
     *
     * @return FormTypeInterface|View
     */
    public function postAnnouncementAction(Request $request)
    {
        try {
            $newEntity = $this->container->get('ojs_api.announcement.handler')->post(
                $request->request->all()
            );
            $routeOptions = array(
                'id' => $newEntity->getId(),
                '_format' => $request->get('_format')
            );
            return $this->routeRedirectView('api_1_get_announcements', $routeOptions, Codes::HTTP_CREATED);
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * Update existing Announcement from the submitted data or create a new Announcement at a specific location.
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     201 = "Returned when the Announcement is created",
     *     204 = "Returned when successful",
     *     400 = "Returned when the form has errors"
     *   }
     * )
     *
     * @param Request $request the request object
     * @param int     $id      the Announcement id
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when Announcement not exist
     */
    public function putAnnouncementAction(Request $request, $id)
    {
        try {
            if (!($entity = $this->container->get('ojs_api.announcement.handler')->get($id))) {
                $statusCode = Codes::HTTP_CREATED;
                $entity = $this->container->get('ojs_api.announcement.handler')->post(
                    $request->request->all()
                );
            } else {
                $statusCode = Codes::HTTP_NO_CONTENT;
                $entity = $this->container->get('ojs_api.announcement.handler')->put(
                    $entity,
                    $request->request->all()
                );
            }
            $routeOptions = array(
                'id' => $entity->getId(),
                '_format' => $request->get('_format')
            );
            return $this->routeRedirectView('api_1_get_announcement', $routeOptions, $statusCode);
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * Update existing announcement from the submitted data or create a new announcement at a specific location.
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     204 = "Returned when successful",
     *     400 = "Returned when the form has errors"
     *   }
     * )
     *
     * @param Request $request the request object
     * @param int     $id      the announcement id
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when announcement not exist
     */
    public function patchAnnouncementAction(Request $request, $id)
    {
        try {
            $entity = $this->container->get('ojs_api.announcement.handler')->patch(
                $this->getOr404($id),
                $request->request->all()
            );
            $routeOptions = array(
                'id' => $entity->getId(),
                '_format' => $request->get('_format')
            );
            return $this->routeRedirectView('api_1_get_announcement', $routeOptions, Codes::HTTP_NO_CONTENT);
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * @param $id
     * @throws NotFoundHttpException
     * @return Response
     * @ApiDoc(
     *      resource = false,
     *      description = "Delete Announcement",
     *      requirements = {
     *          {
     *              "name" = "id",
     *              "dataType" = "integer",
     *              "requirement" = "Numeric",
     *              "description" = "Announcement ID"
     *          }
     *      },
     *      statusCodes = {
     *          "204" = "Deleted Successfully",
     *          "404" = "Object cannot found"
     *      }
     * )
     *
     */
    public function deleteAnnouncementAction($id)
    {
        $entity = $this->getOr404($id);
        $this->container->get('ojs_api.announcement.handler')->delete($entity);
        return $this->view(null, Codes::HTTP_NO_CONTENT, []);
    }

    /**
     * Fetch a Announcement or throw an 404 Exception.
     *
     * @param mixed $id
     *
     * @return AdminAnnouncement
     *
     * @throws NotFoundHttpException
     */
    protected function getOr404($id)
    {
        if (!($entity = $this->container->get('ojs_api.announcement.handler')->get($id))) {
            throw new NotFoundHttpException(sprintf('The resource \'%s\' was not found.',$id));
        }
        return $entity;
    }
}