<?php

namespace App\Test\Service\User;

use App\Entity\User;
use App\Service\User\ChangePasswordService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false
 *  - "Admin" User, is_active = true
 *  - "User" User, is_active = true
 */
class ChangePasswordServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private ChangePasswordService $service;

    // UTILITIES
    private string  $uniqid;
    private User    $anonymous;
    private User    $admin;
    private User    $user;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = self::getContainer();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->service = $container->get(ChangePasswordService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case : an administrator changes his password
     */
    public function testChagePasswordOkAdmin()
    {
        // Init before run
        $oldPwd = $this->admin->getPassword();

        // run service
        $this->admin->setPassword('Abcd1234');
        $this->service->changePassword($this->admin, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        $this->entityManager->close();
        /** @var User $admin */
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->assertNotEquals($oldPwd, $admin->getPassword());
        $this->assertNotEquals("Abcd1234", $admin->getPassword());
    }

    /**
     * Normal case : an user changes his password
     */
    public function testChagePasswordOkUser()
    {
        // Init before run
        $oldPwd = $this->user->getPassword();

        // run service
        $this->user->setPassword('Abcd1234');
        $this->service->changePassword($this->user, $this->user);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        $this->entityManager->close();
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
        $this->assertNotEquals($oldPwd, $user->getPassword());
        $this->assertNotEquals("Abcd1234", $user->getPassword());
    }

    /**
     * Test KO : anonymous user changes his password
     */
    public function testChagePasswordKoAnonymous()
    {
        // Init before run
        $oldPwd = $this->anonymous->getPassword();

        // run service
        $this->anonymous->setPassword('Abcd1234');
        $this->service->changePassword($this->anonymous, $this->anonymous);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1 ,$this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->entityManager->close();
        /** @var User $anonymous */
        $anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->assertEquals($oldPwd, $anonymous->getPassword());
    }


    /**
     * Test KO : a user changes the password of another user
     */
    public function testChagePasswordKoWrongUser()
    {
        // Init before run
        $oldPwd = $this->user->getPassword();

        // run service
        $this->user->setPassword('Abcd1234');
        $this->service->changePassword($this->admin, $this->user);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Il n'est pas possible de changer le mot de passe d'un autre utilisateur.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->entityManager->close();
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
        $this->assertEquals($oldPwd, $user->getPassword());
    }

    /**
     * Test KO : invalid password
     */
    public function testChagePasswordKoInvalidPassword()
    {
        // Init before run
        $oldPwd = $this->admin->getPassword();

        // run service
        $this->admin->setPassword('aa');
        $this->service->changePassword($this->admin, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le mot de passe doit faire entre 8 et 254 caractères.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.", $this->service->getErrorsMessages()->get(1));

        // check result
        $this->entityManager->close();
        /** @var User $admin */
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->assertEquals($oldPwd, $admin->getPassword());
    }

    /**
     * Test KO : user not found
     */
    public function testChagePasswordKoUndefinedUser()
    {
        // run service
        $this->service->changePassword(null, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur n'est pas défini.", $this->service->getErrorsMessages()->get(0));
    }

    /**
     * Test KO : unauthenticated user
     */
    public function testChagePasswordKoUnauthenticated()
    {
        // Init before run
        $oldPwd = $this->admin->getPassword();

        // run service
        $this->admin->setPassword('Abcd1234');
        $this->service->changePassword($this->admin, null);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas authentifié.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->entityManager->close();
        /** @var User $admin */
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->assertEquals($oldPwd, $admin->getPassword());
    }

}