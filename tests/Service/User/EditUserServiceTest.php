<?php

namespace App\Test\Service\User;

use App\Entity\User;
use App\Service\User\EditUserService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false
 *  - "Admin" User, is_active = true
 *  - "User" User, is_active = true
 *  - At least one other user
 */
class EditUserServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private EditUserService $service;

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

        $this->service = $container->get(EditUserService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case : an admin updates a user
     */
    public function testOkAdminEditUser()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        unset($users);

        $newUsername = "Updated " . $this->uniqid;
        $userToUpdate
            ->setUsername($newUsername)
            ->setEmail($this->uniqid . "@test-todo.co");

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $newUsername])
        );

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        // No creation or deletion
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        // The old user name no longer exists
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNull($oldUser);

        // Check the update
        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        $this->assertNotNull($updatedUser);
        // It is the same entity
        $this->assertEquals($userToUpdate->getId(), $updatedUser->getId());
        // The user name has been updated
        $this->assertEquals($newUsername, $updatedUser->getUserIdentifier());
        // The email has been updated
        $this->assertEquals($this->uniqid . "@test-todo.co", $updatedUser->getEmail());
        // The password has NOT been updated
        $this->assertEquals($userToUpdate->getPassword(), $updatedUser->getPassword());
    }

    /**
     * Normal case : an admin updates a user
     */
    public function testOkAdminEditUserWithoutRole()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        unset($users);

        $newUsername = "Updated " . $this->uniqid;
        $userToUpdate
            ->setUsername($newUsername)
            ->setEmail($this->uniqid . "@test-todo.co")
            ->setRoles([]);

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $newUsername])
        );

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        // No creation or deletion
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());

        // The old user name no longer exists
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNull($oldUser);

        // Check the update
        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        $this->assertNotNull($updatedUser);
        // It is the same entity
        $this->assertEquals($userToUpdate->getId(), $updatedUser->getId());
        // The user name has been updated
        $this->assertEquals($newUsername, $updatedUser->getUserIdentifier());
        // The email has been updated
        $this->assertEquals($this->uniqid . "@test-todo.co", $updatedUser->getEmail());
        // The password has NOT been updated
        $this->assertEquals($userToUpdate->getPassword(), $updatedUser->getPassword());
    }

    /**
     * Test KO : a user who is not an administrator tries to update a user
     */
    public function testEditUserKoUserIsNotAdmin()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        unset($users);

        $newUsername = "NotUpdated " . $this->uniqid;
        $userToUpdate
            ->setUsername($newUsername)
            ->setEmail($this->uniqid . "@testko.co");

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $newUsername])
        );

        // run service
        $this->service->editUser($userToUpdate, $this->user);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Seul un administrateur peut effectuer cette opération.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        $this->assertNull($updatedUser);

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($userToUpdate->getUserIdentifier(), $oldUser->getUserIdentifier());
        $this->assertEquals($userToUpdate->getEmail(), $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }


    /**
     * Test KO : a disabled user tries to update a user
     */
    public function testEditUserKoUserIsInactive()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        unset($users);

        $newUsername = "NotUpdated " . $this->uniqid;
        $userToUpdate
            ->setUsername($newUsername)
            ->setEmail($this->uniqid . "@testko.co");

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $newUsername])
        );

        // run service
        $this->service->editUser($userToUpdate, $this->anonymous);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        $this->assertNull($updatedUser);

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($userToUpdate->getUserIdentifier(), $oldUser->getUserIdentifier());
        $this->assertEquals($userToUpdate->getEmail(), $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }

    /**
     * Test KO : an unauthenticated user tries to update a user
     */
    public function testEditUserKoUserIsUnauthenticated()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        unset($users);

        $newUsername = "User not updated " . $this->uniqid;
        $userToUpdate
            ->setUsername($newUsername)
            ->setEmail($this->uniqid . "@testko.co");

        // check before run
        $this->assertCount(
            0, $this->entityManager->getRepository(User::class)->findBy(['username' => $newUsername])
        );

        // run service
        $this->service->editUser($userToUpdate, null);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas authentifié.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        $this->assertNull($updatedUser);

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($userToUpdate->getUserIdentifier(), $oldUser->getUserIdentifier());
        $this->assertEquals($userToUpdate->getEmail(), $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }

    /**
     * Test KO : the username and email are already used
     */
    public function testEditUserKoDatasAlreadyExists()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        $oldMail = $userToUpdate->getEmail();
        unset($users);

        $userToUpdate
            ->setUsername($this->user->getUserIdentifier())
            ->setEmail($this->user->getEmail());

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Cet e-mail est déjà utilisé.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Ce nom d'utilisateur déjà utilisé.", $this->service->getErrorsMessages()->get(1));

        // check result
        $this->entityManager->close();

        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        /** @var User $notUpdatedUser */
        $notUpdatedUser = $this->entityManager->getRepository(User::class)->findOneBy(
            ['username' => $this->user->getUserIdentifier()]);
        $this->assertNotNull($notUpdatedUser);
        $this->assertEquals($this->user->getId(), $notUpdatedUser->getId());

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($oldUsername, $oldUser->getUserIdentifier());
        $this->assertEquals($oldMail, $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }

    /**
     * Test KO : wrong datas
     */
    public function testEditUserKoInvalidDatasTooShort()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        $oldMail = $userToUpdate->getEmail();
        unset($users);

        $userToUpdate
            ->setUsername("a")
            ->setEmail("test.ko");

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le nom d'utilisateur doit contenir au moins deux caractères.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Le format de l'adresse n'est pas correct.", $this->service->getErrorsMessages()->get(1));

        // check result
        $this->entityManager->close();

        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($oldUsername, $oldUser->getUserIdentifier());
        $this->assertEquals($oldMail, $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }

    /**
     * Test KO : wrong datas
     */
    public function testEditUserKoInvalidDatasTooLong()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        $oldMail = $userToUpdate->getEmail();
        unset($users);

        $newUsername = $this->giveUsernameTooLong();
        $newEmail = $this->giveEmailTooLong();

        $userToUpdate
            ->setUsername($newUsername)
            ->setEmail($newEmail);

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le nom d'utilisateur ne peut pas dépasser 25 caractères.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("L'adresse e-mail ne peut pas dépasser 60 caractères.", $this->service->getErrorsMessages()->get(1));

        // check result
        $this->entityManager->close();

        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($oldUsername, $oldUser->getUserIdentifier());
        $this->assertEquals($oldMail, $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }

    public function testEditUserKoInvalidRole()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);
        /** @var User $userToUpdate */
        $userToUpdate = $users[$userNb - 1];
        $oldUsername = $userToUpdate->getUserIdentifier();
        $oldMail = $userToUpdate->getEmail();
        unset($users);

        $userToUpdate
            ->setUsername("TestKO")
            ->setEmail("test.ko@todo.co")
            ->setRoles(['ROLE_INVALID']);

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Le rôle choisi n'est pas valide : ROLE_INVALID", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->entityManager->close();

        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        /** @var ?User $oldUser */
        $oldUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $oldUsername]);
        $this->assertNotNull($oldUser);

        // No changes have been made
        $this->assertEquals($userToUpdate->getId(), $oldUser->getId());
        $this->assertEquals($oldUsername, $oldUser->getUserIdentifier());
        $this->assertEquals($oldMail, $oldUser->getEmail());
        $this->assertEquals($userToUpdate->getPassword(), $oldUser->getPassword());
    }

    /**
     * Test KO : wrong datas : user undefined
     */
    public function testEditUserKoUserUndefined()
    {
        // Init before run
        $userNb = count($this->entityManager->getRepository(User::class)->findAll());

        // run service
        $this->service->editUser(null, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // read errors
        $this->assertEquals("L'utilisateur n'est pas défini.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
    }

    /**
     * Test KO : the administrator tries to change the anonymous user
     */
    public function testEditUserKoAnonymous()
    {
        // Init before run
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $userNb = count($users);

        $userToUpdate = ($this->anonymous)->setUsername("Ko !" . $this->uniqid);

        // run service
        $this->service->editUser($userToUpdate, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur Anonymous ne peut pas être modifié.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->entityManager->close();

        $this->assertCount($userNb, $this->entityManager->getRepository(User::class)->findAll());
        $this->assertNull($this->entityManager->getRepository(User::class)->findOneBy(
            ['username' => "Ko !" . $this->uniqid]));

        /** @var ?User $notUpdatedUser */
        $notUpdatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => "Anonymous"]);
        $this->assertNotNull($notUpdatedUser);

        // No changes have been made
        $this->assertEquals($this->anonymous->getId(), $notUpdatedUser->getId());
        $this->assertEquals("Anonymous", $notUpdatedUser->getUserIdentifier());
    }

    
    // ============================================================================================
    // DATAS FOR TESTS
    // ============================================================================================
    protected function giveUsernameTooLong(): string
    {
        $username = "";
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // generate username
        for ($i = 0; $i < 30; $i++)
        {
            $username .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $username;
    }

    protected function giveEmailTooLong(): string
    {
        $email = "";
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';

        // generate email
        for ($i = 0; $i < 60; $i++)
        {
            $email .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $email . "@todo.co";
    }

}