<?php

namespace Ojs\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Entity;
use Ojs\Common\Helper\ActionHelper;
use Symfony\Component\HttpFoundation\Request;
use Ojs\Common\Controller\OjsController as Controller;
use Ojs\JournalBundle\Entity\JournalIndex;
use Ojs\JournalBundle\Form\JournalIndexType;

/**
 * JournalIndex controller.
 *
 */
class JournalIndexController extends Controller
{

    /**
     * Lists all JournalIndex entities.
     *
     */
    public function indexAction()
    {
        $source = new Entity('OjsJournalBundle:JournalIndex');
        $grid = $this->get('grid')->setSource($source);

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = ActionHelper::showAction('admin_journalindex_show', 'id');
        $rowAction[] = ActionHelper::editAction('admin_journalindex_edit', 'id');
        $rowAction[] = ActionHelper::deleteAction('admin_journalindex_delete', 'id');

        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);
        $data = [];
        $data['grid'] = $grid;

        return $grid->getGridResponse('OjsJournalBundle:JournalIndex:index.html.twig', $data);
    }
    /**
     * Creates a new JournalIndex entity.
     *
     */
    public function createAction(Request $request)
    {
        $entity = new JournalIndex();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $logo = $request->get('logo');
            if ($logo) {
                $entity->setLogoOptions(json_encode($logo));
            }
            $em->persist($entity);
            $em->flush();
            $this->successFlashBag('successful.create');

            return $this->redirectToRoute('admin_journalindex_show', ['id' => $entity->getId()]);
        }

        return $this->render('OjsJournalBundle:JournalIndex:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Creates a form to create a JournalIndex entity.
     *
     * @param JournalIndex $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createCreateForm(JournalIndex $entity)
    {
        $form = $this->createForm(new JournalIndexType(), $entity, array(
            'action' => $this->generateUrl('admin_journalindex_create'),
            'method' => 'POST',
        ));

        return $form;
    }

    /**
     * Displays a form to create a new JournalIndex entity.
     *
     */
    public function newAction()
    {
        $entity = new JournalIndex();
        $form   = $this->createCreateForm($entity);

        return $this->render('OjsJournalBundle:JournalIndex:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Finds and displays a JournalIndex entity.
     *
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('OjsJournalBundle:JournalIndex')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        return $this->render('OjsJournalBundle:JournalIndex:show.html.twig', array(
            'entity'      => $entity,
        ));
    }

    /**
     * Displays a form to edit an existing JournalIndex entity.
     *
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('OjsJournalBundle:JournalIndex')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        $editForm = $this->createEditForm($entity);

        return $this->render('OjsJournalBundle:JournalIndex:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
        ));
    }

    /**
     * Creates a form to edit a JournalIndex entity.
     *
     * @param JournalIndex $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createEditForm(JournalIndex $entity)
    {
        $form = $this->createForm(new JournalIndexType(), $entity, array(
            'action' => $this->generateUrl('admin_journalindex_update', array('id' => $entity->getId())),
            'method' => 'POST',
        ));

        return $form;
    }
    /**
     * Edits an existing JournalIndex entity.
     *
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var JournalIndex $entity */
        $entity = $em->getRepository('OjsJournalBundle:JournalIndex')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $logo = $request->get('logo');
            if ($logo) {
                $entity->setLogoOptions(json_encode($logo));
            }
            $em->persist($entity);
            $em->flush();
            $this->successFlashBag('successful.update');

            return $this->redirect($this->generateUrl('admin_journalindex_edit', array('id' => $id)));
        }

        return $this->render('OjsJournalBundle:JournalIndex:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
        ));
    }

    /**
     * Deletes a JournalIndex entity.
     * @param  JournalIndex                                       $entity
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(JournalIndex $entity)
    {
        $this->throw404IfNotFound($entity);
        $em = $this->getDoctrine()->getEntityManager();
        $em->remove($entity);
        $em->flush();
        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('admin_journalindex');
    }
}
