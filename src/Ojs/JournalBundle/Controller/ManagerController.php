<?php

namespace Ojs\JournalBundle\Controller;

use Ojs\JournalBundle\Form\Type\JournalType;
use Ojs\AdminBundle\Form\Type\QuickSwitchType;
use Ojs\AnalyticsBundle\Utils\GraphDataGenerator;
use Ojs\CoreBundle\Controller\OjsController as Controller;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\JournalSetting;
use Ojs\JournalBundle\Event\WorkflowEvent;
use Ojs\JournalBundle\Event\WorkflowEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Yaml\Parser;

class ManagerController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function journalSettingsAction(Request $request)
    {
        $journal = $this->get("ojs.journal_service")->getSelectedJournal();
        $this->throw404IfNotFound($journal);
        if (!$this->isGranted('EDIT', $journal)) {
            throw new AccessDeniedException("You not authorized for this page!");
        }

        $form = $this->createJournalEditForm($journal);
        return $this->render(
            'OjsJournalBundle:Manager:journal_settings.html.twig',
            array(
                'entity' => $journal,
                'edit_form' => $form->createView(),
            )
        );
    }

    private function createJournalEditForm(Journal $journal)
    {
        $form = $this->createForm(
            new JournalType(),
            $journal,
            array(
                'action' => $this->generateUrl('ojs_journal_settings_update', ['journalId' => $journal->getId()]),
                'method' => 'PUT',
            )
        );
        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }

    /**
     * @param  Request $request
     * @return RedirectResponse|Response
     */
    public function updateJournalAction(Request $request)
    {
        /** @var Journal $entity */
        $em = $this->getDoctrine()->getManager();
        $entity = $this->get('ojs.journal_service')->getSelectedJournal();
        $this->throw404IfNotFound($entity);

        if (!$this->isGranted('EDIT', $entity)) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }
        $editForm = $this->createJournalEditForm($entity);
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $entity->setLanguageCodeSet($entity->getLanguages());
            $entity->addLanguage($entity->getMandatoryLang());
            $em->flush();
            $this->successFlashBag('successful.update');

            return $this->redirectToRoute('ojs_journal_settings_index', ['journalId' => $entity->getId()]);
        }

        return $this->render(
            'OjsJournalBundle:Manager:journal_settings.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * @todo setttings enumeration should be done, otherwise setting keys will be a garbage
     * @param  Request $request
     * @param  integer $journalId
     * @return Response
     */
    public function journalSettingsSubmissionAction(Request $request, $journalId = null)
    {
        $em = $this->getDoctrine()->getManager();
        /* @var $journal  Journal */
        $journal = !$journalId ?
            $this->get("ojs.journal_service")->getSelectedJournal() :
            $em->getRepository('OjsJournalBundle:Journal')->find($journalId);

        if (!$this->isGranted('EDIT', $journal)) {
            throw new AccessDeniedException("You not authorized for this page!");
        }

        if ($request->getMethod() == 'POST') {
            $submissionConfirmText = $request->get('submissionConfirmText');
            if (!empty($submissionConfirmText)) {
                $this->updateJournalSetting(
                    $journal,
                    'submissionConfirmText',
                    $submissionConfirmText
                );
            }
            $submissionAbstractTemplate = $request->get('submissionAbstractTemplate');
            if (!empty($submissionAbstractTemplate)) {
                $this->updateJournalSetting(
                    $journal,
                    'submissionAbstractTemplate',
                    $submissionAbstractTemplate
                );
            }
        }
        $yamlParser = new Parser();
        $root = $this->container->getParameter('kernel.root_dir');
        $data = array(
            'settings' => array(
                'submissionConfirmText' => $journal->getSetting('submissionConfirmText') ?
                    $journal->getSetting('submissionConfirmText')->getValue() :
                    null,
                'submissionAbstractTemplate' => $journal->getSetting('submissionAbstractTemplate') ?
                    $journal->getSetting('submissionAbstractTemplate')->getValue() :
                    null,
            ),
            'abstractTemplates' => $yamlParser->parse(
                file_get_contents(
                    $root .
                    '/../src/Ojs/JournalBundle/Resources/data/abstracttemplates.yml'
                )
            ),
            'journal' => $journal,
        );

        return $this->render('OjsJournalBundle:Manager:journal_settings_submission.html.twig', $data);
    }

    /**
     * @param  Journal $journal
     * @param  string $settingName
     * @param  string $settingValue if null, function will return current value
     * @param  bool $encoded set true if setting stored as json_encoded
     * @return array|mixed|string
     */
    private function updateJournalSetting($journal, $settingName, $settingValue, $encoded = false)
    {
        if (!$this->isGranted('EDIT', $journal)) {
            throw new AccessDeniedException($this->get('translator')->trans("You can't view this page."));
        }

        $em = $this->getDoctrine()->getManager();
        /** @var JournalSetting $setting */
        $setting = $em->
        getRepository('OjsJournalBundle:JournalSetting')->
        findOneBy(array('setting' => $settingName));

        $settingString = $encoded ? json_encode($settingValue) : $settingValue;
        if ($setting) {
            $setting->setValue($settingString);
        } else {
            $setting = new JournalSetting($settingName, $settingString, $journal);
        }
        $em->persist($setting);
        $em->flush();

        return $setting ? ($encoded ? json_decode($setting->getValue()) : $setting->getValue()) : [];
    }

    /**
     * @param  Request $req
     * @param  null|integer $journalId
     * @return Response
     */
    public function journalSettingsMailAction(Request $req, $journalId = null)
    {
        $em = $this->getDoctrine()->getManager();
        /* @var $journal Journal */
        $journal = !$journalId ?
            $this->get("ojs.journal_service")->getSelectedJournal() :
            $em->getRepository('OjsJournalBundle:Journal')->find($journalId);

        if (!$this->isGranted('EDIT', $journal)) {
            throw new AccessDeniedException($this->get('translator')->trans("You can't view this page."));
        }

        if ($req->getMethod() == 'POST' && !empty($req->get('emailSignature'))) {
            $this->updateJournalSetting($journal, 'emailSignature', $req->get('emailSignature'), false);
        }

        $emailSignature = $journal->getSetting('emailSignature') ?
            $journal->getSetting('emailSignature')->getValue() : null;

        return $this->render('OjsJournalBundle:Manager:journal_settings_mail.html.twig',
            ['journal' => $journal, 'emailSignature' => $emailSignature]);
    }

    /**
     * @param  Request $request
     * @return Response
     */
    public function userIndexAction(Request $request)
    {
        $dispatcher = $this->get('event_dispatcher');
        $switcher = $this->createForm(new QuickSwitchType(), null, ['user' => $this->getUser()])->createView();
        $articles = $this
            ->getDoctrine()
            ->getRepository('OjsJournalBundle:Article')
            ->findBy(['submitterUser' => $this->getUser()]);

        $response = $response = $this->render(
            'OjsJournalBundle:User:home.html.twig',
            [
                'switcher' => $switcher,
                'articles' => $articles,
                'data' => $this->createStats()
            ]
        );

        $event = new WorkflowEvent($request);
        $dispatcher->dispatch(WorkflowEvents::LIST_ARTICLES, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        return $response;
    }

    /**
     *  Arranges statistics
     * @return array
     */
    private function createStats()
    {
        $generator = new GraphDataGenerator($this->getDoctrine()->getManager());

        $lastMonth = ['x'];
        for ($i = 0; $i < 30; $i++) {
            $lastMonth[] = date($generator->getDateFormat(), strtotime('-' . $i . ' days'));
        }

        $slicedLastMonth = array_slice($lastMonth, 1);

        $articles = $this
            ->getDoctrine()
            ->getRepository('OjsJournalBundle:Article')
            ->findBy(['submitterUser' => $this->getUser()]);

        $json = [
            'dates' => $lastMonth,
            'articleViews' => $generator->generateArticleBarChartData($articles, $slicedLastMonth),
            'articleFileDownloads' => $generator->generateArticleFilePieChartData($articles, $slicedLastMonth),
        ];

        $data = [
            'stats' => json_encode($json),
            'articles' => $generator->generateArticleViewsData($articles),
            'articleFiles' => $generator->generateArticleFileDownloadsData($articles),
            'articlesMonthly' => $generator->generateArticleViewsData($articles, $slicedLastMonth),
            'articleFilesMonthly' => $generator->generateArticleFileDownloadsData($articles, $slicedLastMonth),
        ];

        return $data;
    }

    /**
     * @return Response
     * @throws HttpException
     */
    public function myJournalsAction()
    {
        $user_id = $this->getUser()->getId();
        if (!$user_id) {
            throw new HttpException(403, 'ojs.403');
        }

        $entities = $this
            ->getDoctrine()
            ->getRepository('OjsJournalBundle:JournalUser')
            ->findBy(['user' => $this->getUser()]);

        return $this->render(
            'OjsJournalBundle:User:myjournals.html.twig',
            array(
                'entities' => $entities,
            )
        );
    }
}
