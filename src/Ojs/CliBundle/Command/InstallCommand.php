<?php

namespace Ojs\CliBundle\Command;

use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\SiteBundle\Acl\JournalRoleSecurityIdentity;
use Ojs\UserBundle\Entity\UserJournalRole;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Ojs\UserBundle\Entity\Role;
use Ojs\UserBundle\Entity\User;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Composer\Script\CommandEvent;
use Ojs\JournalBundle\Entity\Theme;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;

class InstallCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('ojs:install')
            ->setDescription('Ojs first installation')
            ->addOption('no-role', null, InputOption::VALUE_NONE, 'Whitout Role Data')
            ->addOption('no-admin', null, InputOption::VALUE_NONE, 'Whitout Admin Record')
            ->addOption('no-location', null, InputOption::VALUE_NONE, 'Whitout Location Data')
            ->addOption('no-theme', null, InputOption::VALUE_NONE, 'Whitout Theme')
            ->addOption('no-acl', null, InputOption::VALUE_NONE, 'Whitout ACL Data')
            ->addOption('fix-acl', null, InputOption::VALUE_NONE, 'Fix Acl Structure')
        ;
    }

    public static function composer(CommandEvent $event)
    {
        $options = self::getOptions($event);
        $appDir = $options['symfony-app-dir'];
        $webDir = $options['symfony-web-dir'];

        if (!is_dir($webDir)) {
            echo 'The symfony-web-dir ('.$webDir.') specified in composer.json was not found in '.getcwd().', can not install assets.'.PHP_EOL;

            return;
        }

        static::executeCommand($event, $appDir, 'ojs:install');
    }

    protected static function getOptions(CommandEvent $event)
    {
        $options = array_merge(array(
            'symfony-app-dir' => 'app',
            'symfony-web-dir' => 'web',
            'symfony-assets-install' => 'hard',
                ), $event->getComposer()->getPackage()->getExtra());

        $options['symfony-assets-install'] = getenv('SYMFONY_ASSETS_INSTALL') ?: $options['symfony-assets-install'];

        $options['process-timeout'] = $event->getComposer()->getConfig()->get('process-timeout');

        return $options;
    }

    protected static function getPhp()
    {
        $phpFinder = new PhpExecutableFinder();
        if (!$phpPath = $phpFinder->find()) {
            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        }

        return $phpPath;
    }

    protected static function executeCommand(CommandEvent $event, $appDir, $cmd, $timeout = 300)
    {
        $php = escapeshellarg(self::getPhp());
        $console = escapeshellarg($appDir.'/console');
        if ($event->getIO()->isDecorated()) {
            $console .= ' --ansi';
        }

        $process = new Process($php.' '.$console.' '.$cmd, null, null, null, $timeout);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('An error occurred when executing the "%s" command.', escapeshellarg($cmd)));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sb = '<fg=black;bg=green>';
        $se = '</fg=black;bg=green>';
        $translator = $this->getContainer()->get('translator');

        /** @var HelperSet $helperSet */
        $helperSet = $this->getHelperSet();
        /** @var DialogHelper $dialog */
        $dialog = $helperSet->get('dialog');
        $kernel = $this->getContainer()->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);
        //$translator->setLocale('tr_TR');
        $output->writeln($this->printWelcome());
        $output->writeln('<info>'.
                $translator->trans('ojs.install.title').
                '</info>');

        if (!$dialog->askConfirmation(
            $output, '<question>'.
            $translator->trans("ojs.install.confirm").
            ' (y/n) : </question>', true
        )) {
            return;
        }

        $command2 = 'doctrine:schema:update --force';
        $output->writeln('<info>Updating db schema!</info>');
        $application->run(new StringInput($command2));

        if (!$input->getOption('no-location')) {
            $location = $this->getContainer()->get('kernel')->getRootDir().'/../src/Okulbilisim/LocationBundle/Resources/data/location.sql';
            $locationSql = file_get_contents($location);
            $command3 = 'doctrine:query:sql "'.$locationSql.'"';
            $application->run(new StringInput($command3));
            $output->writeln("Locations inserted.");
        }

        if (!$input->getOption('no-role')) {
            $output->writeln($sb.'Inserting roles to db'.$se);
            $this->insertRoles($output);

            if (!$input->getOption('no-admin')) {
                $admin_username = $dialog->ask(
                    $output, '<info>Set system admin username (admin) : </info>', 'admin');
                $admin_email = $dialog->ask(
                    $output, '<info>Set system admin email'.
                    ' (root@localhost.com) : </info>', 'root@localhost.com');
                $admin_password = $dialog->ask(
                    $output, '<info>Set system admin password (admin) </info>', 'admin');

                $output->writeln($sb.'Inserting system admin user to db'.$se);
                $this->insertAdmin($admin_username, $admin_email, $admin_password);
            }
        }

        if (!$input->getOption('no-theme')) {
            $output->writeln($sb.'Inserting default theme record'.$se);
            $this->insertTheme();
        }
        if (!$input->getOption('no-acl')) {
            $output->writeln($sb.'Inserting default ACL records'.$se);
            $this->insertAcls();
        }
        if ($input->getOption('fix-acl')) {
            $output->writeln($sb.'Fixing ACL Records'.$se);
            $this->fixAcls($output);
        }

        $output->writeln("\nDONE\n");
        $output->writeln("You can run "
                ."<info>php app/console ojs:install:initial-data </info> "
                ."to add initial data\n");
    }

    /**
     * @param OutputInterface $output
     */
    protected function insertRoles(OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
        $roles = $this->getContainer()->getParameter('roles');
        $role_repo = $doctrine->getRepository('OjsUserBundle:Role');
        foreach ($roles as $role) {
            $new_role = new Role();
            $check = $role_repo->findOneBy(array('role' => $role['role']));
            if (!empty($check)) {
                $output->writeln('<error> This role record already exists on db </error> : <info>'.
                        $role['role'].'</info>');
                continue;
            }
            $output->writeln('<info>Added : '.$role['role'].'</info>');
            $new_role->setName($role['desc']);
            $new_role->setRole($role['role']);
            $new_role->setIsSystemRole($role['isSystemRole']);

            $em->persist($new_role);
        }
        $em->flush();
    }

    /**
     * @param $username
     * @param $email
     * @param $password
     */
    protected function insertAdmin($username, $email, $password)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $factory = $this->getContainer()->get('security.encoder_factory');
        $user = $em->getRepository('OjsUserBundle:User')->findOneBy(
            array('email' => $email)
        );
        if (is_null($user)) {
            $user = $em->getRepository('OjsUserBundle:User')->findOneBy(
                array('username' => $username)
            );
        }
        if (is_null($user)) {
            $user = new User();
        }

        $encoder = $factory->getEncoder($user);
        $pass_encoded = $encoder->encodePassword($password, $user->getSalt());
        $user->setEmail($email);
        $user->setPassword($pass_encoded);
        $user->setUsername($username);
        $user->setIsActive(true);
        $user->setStatus(1);
        $user->generateApiKey();

        $role_repo = $doctrine->getRepository('OjsUserBundle:Role');
        $user->addRole($role_repo->findOneBy(array('role' => 'ROLE_SUPER_ADMIN')));

        $em->persist($user);
        $em->flush();
    }

    protected function insertTheme()
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $theme = new Theme();
        $theme->setName("default");
        $theme->setTitle('Ojs');
        $em->persist($theme);
        $em->flush();
    }

    protected function insertAcls()
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $aclManager = $this->getContainer()->get('problematic.acl_manager');

        $journalClass = $em->getRepository('OjsJournalBundle:Journal')->getClassName();
        $userClass = $em->getRepository('OjsUserBundle:User')->getClassName();
        $aclManager->on($journalClass)->to('ROLE_ADMIN')->permit(MaskBuilder::MASK_OWNER)->save();
        $aclManager->on($journalClass)->field('boards')->to('ROLE_ADMIN')->permit(MaskBuilder::MASK_OWNER)->save();
        $aclManager->on($journalClass)->field('sections')->to('ROLE_ADMIN')->permit(MaskBuilder::MASK_OWNER)->save();
        $aclManager->on($journalClass)->field('issues')->to('ROLE_ADMIN')->permit(MaskBuilder::MASK_OWNER)->save();
        $aclManager->on($userClass)->to('ROLE_ADMIN')->permit(MaskBuilder::MASK_OWNER)->save();
    }

    protected function fixAcls(OutputInterface $output)
    {
        $sb = '<fg=black;bg=green>';
        $se = '</fg=black;bg=green>';
        $em = $this->getContainer()->get('doctrine')->getManager();
        $aclManager = $this->getContainer()->get('problematic.acl_manager');
        $builder = new MaskBuilder();
        $viewEdit = $builder
            ->add('view')
            ->add('edit')->get();

        /** @var Journal[] $journals */
        $journals = $em->getRepository('OjsJournalBundle:Journal')->findAll();
        foreach ($journals as $journal) {
            $output->writeln($sb.'Journal fix for : '.$journal->getTitle().$se);
            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_JOURNAL_MANAGER'))
                ->permit(MaskBuilder::MASK_OWNER)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_JOURNAL_MANAGER'))
                ->permit(MaskBuilder::MASK_OWNER)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_JOURNAL_MANAGER'))
                ->permit(MaskBuilder::MASK_OWNER)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_EDITOR'))
                ->permit(MaskBuilder::MASK_OWNER)->save();
            $aclManager->on($journal)->field('issues')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_EDITOR'))
                ->permit(MaskBuilder::MASK_OWNER)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_AUTHOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('articles')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_AUTHOR'))
                ->permit(MaskBuilder::MASK_CREATE)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_PROOFREADER'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_PROOFREADER'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_PROOFREADER'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('issues')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_PROOFREADER'))
                ->permit(MaskBuilder::MASK_VIEW)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_COPYEDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_COPYEDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_COPYEDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('issues')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_COPYEDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_LAYOUT_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_LAYOUT_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_LAYOUT_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('issues')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_LAYOUT_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SECTION_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SECTION_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SECTION_EDITOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('issues')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SECTION_EDITOR'))
                ->permit($viewEdit)->save();

            $aclManager->on($journal)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SUBSCRIPTION_MANAGER'))
                ->permit(MaskBuilder::MASK_VIEW)->save();
            $aclManager->on($journal)->field('boards')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SUBSCRIPTION_MANAGER'))
                ->permit($viewEdit)->save();
            $aclManager->on($journal)->field('sections')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SUBSCRIPTION_MANAGER'))
                ->permit($viewEdit)->save();
            $aclManager->on($journal)->field('issues')->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SUBSCRIPTION_MANAGER'))
                ->permit($viewEdit)->save();
        }
        /** @var Article[] $articles */
        $articles = $em->getRepository('OjsJournalBundle:Article')->findAll();
        foreach ($articles as $article) {
            $output->writeln($sb.'Article fix for : '.$article->getTitle().$se);
            $journal = $article->getJournal();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_JOURNAL_MANAGER'))
                ->permit(MaskBuilder::MASK_OWNER)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_EDITOR'))
                ->permit(MaskBuilder::MASK_OWNER)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_AUTHOR'))
                ->permit(MaskBuilder::MASK_VIEW)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_PROOFREADER'))
                ->permit($viewEdit)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_COPYEDITOR'))
                ->permit($viewEdit)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_LAYOUT_EDITOR'))
                ->permit($viewEdit)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SECTION_EDITOR'))
                ->permit($viewEdit)->save();

            $aclManager->on($article)->to(new JournalRoleSecurityIdentity($journal, 'ROLE_SUBSCRIPTION_MANAGER'))
                ->permit($viewEdit)->save();
        }

        // Every JOURNAL MANAGER and EDITOR must be AUTHOR


        $authorRole = $em->getRepository('OjsUserBundle:Role')->findOneBy(array('role' => 'ROLE_AUTHOR'));

        $userJournalRoles = $em->getRepository('OjsUserBundle:UserJournalRole')->findAllByJournalRole(array('ROLE_JOURNAL_MANAGER', 'ROLE_EDITOR'));
        foreach ($userJournalRoles as $userJournalRole) {
            if (!$em->getRepository('OjsUserBundle:User')->hasJournalRole($userJournalRole->getUser(), $authorRole, $userJournalRole->getJournal())) {
                $newUserJournalRole = new UserJournalRole();
                $newUserJournalRole->setRole($authorRole);
                $newUserJournalRole->setUser($userJournalRole->getUser());
                $newUserJournalRole->setJournal($userJournalRole->getJournal());
                $em->persist($newUserJournalRole);
                $output->writeln($sb.'Author added : '.$userJournalRole->getUser().' - '.$userJournalRole->getJournal().$se);
            }
        }

        $em->flush();
    }

    protected function printWelcome()
    {
        return '';
    }
}
