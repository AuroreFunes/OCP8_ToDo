<?php

namespace App\Test;

use App\Entity\Task;
use App\Entity\User;
use App\Service\Task\CreateTaskService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false, with at least 1 task
 *  - "Admin" User, is_active = true, with at least 1 task
 *  - "User" User, is_active = true, with at least 1 task
 */
class CreateTaskServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private CreateTaskService $service;
    
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

        $this->service = $container->get(CreateTaskService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case : an admin creates a new task
     */
    public function testAdminCreateTaskOk()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());
        $taskTitle = "New Task " . $this->admin->getUserIdentifier() . " " . $this->uniqid;

        // check before run
        $this->assertCount(
            0, 
            $this->entityManager->getRepository(Task::class)->findBy(['title' => $taskTitle])
        );

        // run service
        $this->service->createTask($this->giveValidTask($this->admin->getUserIdentifier()), $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        $this->assertCount($taskNb + 1, $this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $newTask */
        $newTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $taskTitle]);
        $this->assertNotNull($newTask);
        $this->assertEquals($taskTitle, $newTask->getTitle());
        $this->assertEquals($this->admin->getUserIdentifier(), $newTask->getAuthor()->getUserIdentifier());
    }

    /**
     * Normal case : one user creates a new task
     */
    public function testUserCreateTaskOk()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());
        $taskTitle = "New Task " . $this->user->getUserIdentifier() . " " . $this->uniqid;

        // check before run
        $this->assertCount(
            0, 
            $this->entityManager->getRepository(Task::class)->findBy(['title' => $taskTitle])
        );

        // run service
        $this->service->createTask($this->giveValidTask($this->user->getUserIdentifier()), $this->user);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        $this->assertCount($taskNb + 1, $this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $newTask */
        $newTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $taskTitle]);
        $this->assertNotNull($newTask);
        $this->assertEquals($taskTitle, $newTask->getTitle());
        $this->assertEquals($this->user->getUserIdentifier(), $newTask->getAuthor()->getUserIdentifier());
    }

    /**
     * Case KO: the anonymous user creates to modify a task
     */
    public function testCreateTaskWithInactiveUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());
        $taskTitle = "New Task test " . $this->anonymous->getUserIdentifier() . " " . $this->uniqid;

        // run service
        $this->service->createTask($this->giveValidTask($this->anonymous->getUserIdentifier()), $this->anonymous);

        // check status
        $this->assertFalse($this->service->getStatus());

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => $taskTitle]));

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));
    }

    /**
     * Case KO: the user is not authenticated
     */
    public function testCreateTaskWithUnauthenticatedUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        // run service
        $this->service->createTask($this->giveValidTask(""), null);

        // check status
        $this->assertFalse($this->service->getStatus());

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas authentifié.", $this->service->getErrorsMessages()->get(0));
    }

    /**
     * Case KO: the task is not valid
     */
    public function testCreateTaskEmpty()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        // run service
        $this->service->createTask(new Task(), $this->user);

        // check status
        $this->assertFalse($this->service->getStatus());

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous devez saisir un titre.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Vous devez saisir du contenu.", $this->service->getErrorsMessages()->get(1));
    }

    /**
     * Case KO: the task is null
     */
    public function testCreateTaskNull()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        // run service
        $this->service->createTask(null, $this->user);

        // check status
        $this->assertFalse($this->service->getStatus());

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("La tâche n'est pas renseignée.", $this->service->getErrorsMessages()->get(0));
    }

    // ============================================================================================
    // DATAS FOR TESTS
    // ============================================================================================
    protected function giveValidTask(string $username)
    {
        $task = new Task();
        $task
            ->setTitle("New Task " . $username . " " . $this->uniqid)
            ->setContent("Valid content for new task " . $this->uniqid);
        return $task;
    }

}