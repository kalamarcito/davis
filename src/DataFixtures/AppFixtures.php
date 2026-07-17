<?php

namespace App\DataFixtures;

use App\Entity\AddressBook;
use App\Entity\AddressBookInstance;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\Principal;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Test User 1
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $user = (new User())
            ->setUsername('test_user')
            ->setPassword($hash);
        $manager->persist($user);

        $principal = (new Principal())
            ->setUri(Principal::PREFIX.$user->getUsername())
            ->setEmail('test@test.com')
            ->setDisplayName('Test User')
            ->setIsAdmin(true);
        $manager->persist($principal);

        // Create all the default calendar / addressbook
        $calendarInstance = new CalendarInstance();
        $calendar = new Calendar();
        $calendarInstance->setPrincipalUri(Principal::PREFIX.$user->getUsername())
                    ->setUri('default')
                    ->setDisplayName('default.calendar.title')
                    ->setDescription('default.calendar.description')
                    ->setCalendar($calendar);
        $manager->persist($calendarInstance);

        // Enable delegation by default
        $principalProxyRead = new Principal();
        $principalProxyRead->setUri($principal->getUri().Principal::READ_PROXY_SUFFIX)
                            ->setIsMain(false);
        $manager->persist($principalProxyRead);

        $principalProxyWrite = new Principal();
        $principalProxyWrite->setUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX)
                            ->setIsMain(false);
        $manager->persist($principalProxyWrite);

        $addressbook = new AddressBook();
        $addressbookInstance = new AddressBookInstance();
        $addressbookInstance->setPrincipalUri(Principal::PREFIX.$user->getUsername())
                    ->setUri('default')
                    ->setDisplayName('default.addressbook.title')
                    ->setDescription('default.addressbook.description')
                    ->setAddressBook($addressbook);
        $manager->persist($addressbook);
        $manager->persist($addressbookInstance);

        $manager->flush();

        // Test User 2 - For API testing
        $hash = password_hash('password2', PASSWORD_DEFAULT);
        $user = (new User())
            ->setUsername('test_user2')
            ->setPassword($hash);
        $manager->persist($user);

        $principal = (new Principal())
            ->setUri(Principal::PREFIX.$user->getUsername())
            ->setEmail('test2@test.com')
            ->setDisplayName('Test User 2')
            ->setIsAdmin(false);
        $manager->persist($principal);

        // Create all the default calendar / addressbook
        $calendarInstance = new CalendarInstance();
        $calendar = new Calendar();
        $calendarInstance->setPrincipalUri(Principal::PREFIX.$user->getUsername())
                    ->setUri('default')
                    ->setDisplayName('default.calendar.title2')
                    ->setDescription('default.calendar.description2')
                    ->setCalendar($calendar);
        $manager->persist($calendarInstance);

        // Enable delegation by default
        $principalProxyRead = new Principal();
        $principalProxyRead->setUri($principal->getUri().Principal::READ_PROXY_SUFFIX)
                            ->setIsMain(false);
        $manager->persist($principalProxyRead);

        $principalProxyWrite = new Principal();
        $principalProxyWrite->setUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX)
                            ->setIsMain(false);
        $manager->persist($principalProxyWrite);

        $addressbook = new AddressBook();
        $addressbookInstance = new AddressBookInstance();
        $addressbookInstance->setPrincipalUri(Principal::PREFIX.$user->getUsername())
                    ->setUri('default')
                    ->setDisplayName('default.addressbook.title2')
                    ->setDescription('default.addressbook.description2')
                    ->setAddressBook($addressbook);
        $manager->persist($addressbook);
        $manager->persist($addressbookInstance);

        $manager->flush();
    }
}
