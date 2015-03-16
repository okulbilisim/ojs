<?php

namespace Ojs\ManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Ojs\JournalBundle\Form\JournalType;
use \Symfony\Component\HttpFoundation\Request;

class ManagerController extends Controller {

    public function journalSettingsAction($journalId = null)
    {
        if (!$journalId) {
            $journal = $this->get("ojs.journal_service")->getSelectedJournal();
        } else {
            $em = $this->getDoctrine()->getManager();
            $journal = $em->getRepository('OjsJournalBundle:Journal')->find($journalId);
        }
        $form = $this->createForm(new JournalType(), $journal, array(
            'action' => $this->generateUrl('journal_update', array('id' => $journal->getId())),
            'method' => 'PUT',
        ));
        return $this->render('OjsManagerBundle:Manager:journal_settings.html.twig', array(
                    'journal' => $journal,
                    'form' => $form->createView(),
        ));
    }

    public function journalSettingsLanguageAction(Request $req, $journalId = null)
    {
        $settingName = 'mandotaryLanguages';
        $em = $this->getDoctrine()->getManager();
        /* @var $journal  \Ojs\JournalBundle\Entity\Journal  */
        if (!$journalId) {
            $journal = $this->get("ojs.journal_service")->getSelectedJournal();
        } else {
            $journal = $em->
                            getRepository('OjsJournalBundle:Journal')->find($journalId);
        }
        $setting = $em->
                getRepository('OjsJournalBundle:JournalSetting')->
                findOneBy(array('journal' => $journal, 'setting' => $settingName));
        if ($req->getMethod() == 'POST' && !empty($req->get('languages'))) {
            $settingString = implode(',', $req->get('languages'));
            if ($setting) {
                $setting->setValue($settingString);
            } else {
                $setting = new \Ojs\JournalBundle\Entity\JournalSetting($settingName, $settingString, $journal);
            }
            $em->persist($setting);
            $em->flush();
        }
        $languages = [];
        if ($setting) {
            foreach (explode(',', $setting->getValue()) as $item) {
                $languages[] = $item;
            }
        }
        return $this->render('OjsManagerBundle:Manager:journal_settings_language.html.twig', array(
                    'journal' => $journal,
                    'mandotaryLanguages' => $languages,
                    'allLanguages' => $journal->getLanguages()
        ));
    }

    public function userIndexAction()
    {
        $user = $this->getUser();
        $journal = $this->get("ojs.journal_service")->getSelectedJournal();
        $mySteps = [];
        if ($journal) {
            $dm = $this->get('doctrine_mongodb')->getManager();
            $allowedWorkflowSteps = $dm->getRepository('OjsWorkflowBundle:JournalWorkflowStep')
                    ->findBy(array('journalid' => $journal->getId()));
            // @todo we should query in a more elegant way  
            // { roles : { $elemMatch : { role : "ROLE_EDITOR" }} })
            // Don't know how to query $elemMatch 
            foreach ($allowedWorkflowSteps as $step) {
                if ($this->checkStepAndUserRoles($step)) {
                    $mySteps[] = $step;
                }
            }
        }
        $waitingTasksCount = [];
        foreach ($mySteps as $step) {
            $countQuery = $dm->getRepository('OjsWorkflowBundle:ArticleReviewStep')
                    ->createQueryBuilder('ars');
            $countQuery->field('step.$id')->equals(new \MongoId($step->getId()));
            $countQuery->field('finishedDate')->equals(null);
            $waitingTasksCount[$step->getId()] = $countQuery->count()->getQuery()->execute();
        }
        // invited steps 
        $invitedWorkflowSteps = $dm->getRepository('OjsWorkflowBundle:Invitation')
                ->findBy(array('userId' => $user->getId()));

        $super_admin = $this->container->get('security.context')->isGranted('ROLE_SUPER_ADMIN');
        if ($super_admin) {
            return $this->redirect($this->generateUrl('dashboard_admin'));
        }
        return $this->render('OjsManagerBundle:User:userwelcome.html.twig', array(
                    'mySteps' => $mySteps,
                    'waitingCount' => $waitingTasksCount,
                    'invitedSteps' => $invitedWorkflowSteps));
    }

    private function checkStepAndUserRoles($step)
    {
        $myRoles = $this->get('session')->get('userJournalRoles');
        $stepRoles = $step->getRoles();
        foreach ($myRoles as $myRole) {
            foreach ((array) $stepRoles as $stepRole) {
                if ($stepRole['role'] === $myRole->getRole()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * list journal users 
     */
    public function usersAction()
    {
        $em = $this->getDoctrine()->getManager();
        $data['journal'] = $this->get("ojs.journal_service")->getSelectedJournal();
        $data['entities'] = $em->getRepository('OjsUserBundle:UserJournalRole')->findAll();
        return $this->render('OjsManagerBundle:Manager:users.html.twig', $data);
    }

    public function roleUser()
    {
        $em = $this->getDoctrine()->getManager();
        $data['journal'] = $this->get("ojs.journal_service")->getSelectedJournal();
        $data['entities'] = $em->getRepository('OjsUserBundle:UserJournalRole')->findAll();
        return $this->render('OjsManagerBundle:Manager:role_users.html.twig', $data);
    }

}
