<?php

namespace App\Test\Service\User;

use App\Entity\User;
use App\Service\User\CreateUserService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false
 *  - "Admin" User, is_active = true
 *  - "User" User, is_active = true
 */
class CreateUserServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private CreateUserService $service;

    // UTILITIES
    private string $uniqid;
    private User     $anonymous;
    private User     $admin;
    private User     $user;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = self::getContainer();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->service = $container->get(CreateUserService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case : an admin creates a new user
     */
    public function testCreateUserOkAdminCreateUser()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $username = "New OK " . $this->uniqid;
        /** @var User $userDatas */
        $userDatas = $this->giveValidUser($username);

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $username])
        );

        // run service
        $this->service->createUser($userDatas, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        $this->assertCount($userNb + 1, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertNotNull($newUser);
        $this->assertEquals($username, $newUser->getUserIdentifier());
        $this->assertEquals($userDatas->getEmail(), $newUser->getEmail());
        $this->assertTrue($newUser->isActive());
        $this->assertEmpty($newUser->getTasks());
    }

    /**
     * Normal case : an admin creates a new user (without role)
     */
    public function testCreateUserOkAdminCreateUserWithoutRole()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $username = "New OK 2 " . $this->uniqid;
        /** @var User $userDatas */
        $userDatas = $this->giveValidUserWithoutRole($username);

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $username])
        );

        // run service
        $this->service->createUser($userDatas, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        $this->assertCount($userNb + 1, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertNotNull($newUser);
        $this->assertEquals($username, $newUser->getUserIdentifier());
        $this->assertEquals($userDatas->getEmail(), $newUser->getEmail());
        $this->assertTrue($newUser->isActive());
        $this->assertEmpty($newUser->getTasks());
    }

    /**
     * Test KO : a user who is not an administrator tries to create a new user
     */
    public function testCreateUserKoUserIsNotAdmin()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $username = "New user NOK " . $this->uniqid;

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $username])
        );

        // run service
        $this->service->createUser($this->giveValidUser($username), $this->user);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Seul un administrateur peut effectuer cette opération.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertNull($newUser);
    }


    /**
     * Test KO : a disabled user tries to create a new user
     */
    public function testCreateUserKoUserIsInactive()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $username = "New user NOK " . $this->uniqid;

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $username])
        );

        // run service
        $this->service->createUser($this->giveValidUser($username), $this->anonymous);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertNull($newUser);
    }

    /**
     * Test KO : an unauthenticated user tries to create a new user
     */
    public function testCreateUserKoUserIsUnauthenticated()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $username = "New user NOK " . $this->uniqid;

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $username])
        );

        // run service
        $this->service->createUser($this->giveValidUser($username), null);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas authentifié.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertNull($newUser);
    }

    /**
     * Test KO : the user already exists
     */
    public function testCreateUserKoUserAlreadyExists()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $newUserDatas = (new User)
            ->setUsername($this->admin->getUserIdentifier())
            ->setEmail($this->admin->getEmail())
            ->setPassword('Abcd1234');

        // check before run
        $this->assertCount(
            1, $this->entityManager->getRepository(User::class)->findBy(
                ['username' => $newUserDatas->getUserIdentifier()])
        );

        // run service
        $this->service->createUser($newUserDatas, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Cet e-mail est déjà utilisé.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Ce nom d'utilisateur déjà utilisé.", $this->service->getErrorsMessages()->get(1));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(
            ['username' => $newUserDatas->getUserIdentifier()]);
        $this->assertEquals($this->admin->getId(), $newUser->getId());
    }

    /**
     * Test KO : wrong datas
     */
    public function testCreateUserKoInvalidDatasTooShort()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $newUserDatas = $this->giveInvalidUserDatasTooShort();

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(
                ['username' => $newUserDatas->getUserIdentifier()])
        );

        // run service
        $this->service->createUser($newUserDatas, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(4, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le nom d'utilisateur doit contenir au moins deux caractères.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Le mot de passe doit faire entre 8 et 254 caractères.", $this->service->getErrorsMessages()->get(1));
        $this->assertEquals("Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.", $this->service->getErrorsMessages()->get(2));
        $this->assertEquals("Le format de l'adresse n'est pas correct.", $this->service->getErrorsMessages()->get(3));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(
            ['username' => $newUserDatas->getUserIdentifier()]);
        $this->assertNull($newUser);
    }

    /**
     * Test KO : wrong datas
     */
    public function testCreateUserKoInvalidDatasTooLong()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $newUserDatas = $this->giveInvalidUserDatasTooLong();

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(
                ['username' => $newUserDatas->getUserIdentifier()])
        );

        // run service
        $this->service->createUser($newUserDatas, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(3, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le nom d'utilisateur ne peut pas dépasser 25 caractères.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Le mot de passe doit faire entre 8 et 254 caractères.", $this->service->getErrorsMessages()->get(1));
        $this->assertEquals("L'adresse e-mail ne peut pas dépasser 60 caractères.", $this->service->getErrorsMessages()->get(2));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        /** @var User $newUser */
        $newUser = $this->entityManager->getRepository(User::class)->findOneBy(
            ['username' => $newUserDatas->getUserIdentifier()]);
        $this->assertNull($newUser);
    }

    /**
     * Test KO : wrong datas, user empty
     */
    public function testCreateUserKoUserEmpty()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $newUserDatas = new User();

        // run service
        $this->service->createUser($newUserDatas, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(3, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous devez saisir un nom d'utilisateur.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Vous devez choisir un mot de passe.", $this->service->getErrorsMessages()->get(1));
        $this->assertEquals("Vous devez saisir une adresse email.", $this->service->getErrorsMessages()->get(2));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
    }

    /**
     * Test KO : wrong datas : user undefined
     */
    public function testCreateUserKoUserUndefined()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());

        // run service
        $this->service->createUser(null, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur n'est pas défini.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
    }

    /**
     * Test KO : wrong datas, invalid role
     */
    public function testCreateUserKoInvalidRole()
    {
        // init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());
        $newUserDatas = $this->giveInvalidUserDatasInvalidRole();

        // run service
        $this->service->createUser($newUserDatas, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le rôle choisi n'est pas valide : ROLE_INVALID", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
    }

    
    // ============================================================================================
    // DATAS FOR TESTS
    // ============================================================================================
    protected function giveValidUser(string $username): User
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmail($this->uniqid . "@testtodo.co")
            ->setPassword('Abcd1234')
            ->setRoles(['ROLE_USER']);
        return $user;
    }

    protected function giveValidUserWithoutRole(string $username): User
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmail($this->uniqid . "-2@testtodo.co")
            ->setPassword('Abcd1234');
        return $user;
    }

    protected function giveInvalidUserDatasTooShort(): User
    {
        $user = new User();
        $user
            ->setUsername('a')
            ->setEmail('ab.test')
            ->setPassword('ab')
            ->setRoles(['ROLE_USER']);
        return $user;
    }

    protected function giveInvalidUserDatasTooLong(): User
    {
        $username = "";
        $email = "";
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // generate username
        for ($i = 0; $i < 30; $i++)
        {
            $username .= $chars[rand(0, strlen($chars) - 1)];
        }

        // generate email
        for ($i = 0; $i < 60; $i++)
        {
            $email .= $chars[rand(10, 36)];
        }

        $user = new User();
        $user
            ->setUsername($username)
            ->setEmail($email . "@todo.co")
            ->setPassword('Ab1')
            ->setRoles(['ROLE_ADMIN']);
        return $user;
    }

    protected function giveInvalidUserDatasInvalidRole(): User
    {
        $user = new User();
        $user
            ->setUsername('Ok' . $this->uniqid)
            ->setEmail('ok-' . $this->uniqid . '@todo.co')
            ->setPassword('Abcd1234')
            ->setRoles(['ROLE_INVALID']);
        return $user;
    }

}