<?php

namespace Ojs\JournalBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Ojs\JournalBundle\Entity\InstitutionTypes;

class LoadInstitutionTypesData extends AbstractFixture implements OrderedFixtureInterface
{

    public function load(ObjectManager $manager)
    {
        $itype = new InstitutionTypes();
        $itype->setName("University");
        $itype->setDescription("Demo intsitution desc.");
        $manager->persist($itype);

        $itype2 = new InstitutionTypes();
        $itype2->setName("Government");
        $itype2->setDescription("Demo intsitution desc.");
        $manager->persist($itype2);

        $this->addReference('ref-institution-type-1', $itype);
        $this->addReference('ref-institution-type-2', $itype2);
        $manager->flush();
    }

    public function getOrder()
    {
        return 4;
    }

}
