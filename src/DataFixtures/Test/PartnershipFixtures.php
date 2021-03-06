<?php

namespace App\DataFixtures\Test;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Partnership;
use App\DataFixtures\PartnershipTypeFixtures;

class PartnershipFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager)
    {
        $partnership = new Partnership();
        $partnership->setProject($this->getReference('project'));
        $partnership->setPartner($this->getReference('organisation'));
        $partnership->setPartnershipType($this->getReference('partnership-type'));
        $manager->persist($partnership);

        $manager->flush();

        $this->addReference('partnership', $partnership);
    }

    public function getDependencies(): array
    {
        return [
            ProjectFixtures::class,
            OrganisationFixtures::class,
            PartnershipTypeFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['api'];
    }
}
