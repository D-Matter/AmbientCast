<?php

declare(strict_types=1);

namespace App\Entity\Fixture;

use App\Entity;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class Podcast extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Entity\Station $station */
        $station = $this->getReference('station');

        $podcastStorage = $station->getPodcastsStorageLocation();

        $podcast = new Entity\Podcast($podcastStorage);

        $podcast->setTitle('The AmbientTest Podcast');
        $podcast->setLink('https://ambient.kwafgroup.com/demo');
        $podcast->setLanguage('en');
        $podcast->setDescription('The unofficial testing podcast for the AmbientCast development team.');
        $podcast->setAuthor('AmbientCast');
        $podcast->setEmail('demo@ambient.kwafgroup.com');
        $manager->persist($podcast);

        $category = new Entity\PodcastCategory($podcast, 'Technology');
        $manager->persist($category);

        $manager->flush();

        $this->setReference('podcast', $podcast);
    }

    /**
     * @return string[]
     */
    public function getDependencies(): array
    {
        return [
            Station::class,
        ];
    }
}
