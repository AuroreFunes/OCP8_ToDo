<?php

namespace App\Test;

use App\Entity\Task;
use App\Entity\User;
use App\Service\Task\DeleteTaskService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false, with at least 2 tasks
 *  - "Admin" User, is_active = true, with at least 2 tasks
 *  - "User" User, is_active = true, with at least 2 tasks
 */
class DeleteTaskServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private DeleteTaskService $service;
    
    // UTILITIES
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

        $this->service = $container->get(DeleteTaskService::class);

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case: a user delete a task he has created
     */
    public function testOkDeleteTaskWithSimpleUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToDeleted */
        $taskToDeleted = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $taskTitle = $taskToDeleted->getTitle();
        $taskId = $taskToDeleted->getId();

        // run service
        $this->service->deleteTask($taskToDeleted, $this->user);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        
        // one task have been deleted
        $this->assertCount($taskNb - 1, $this->entityManager->getRepository(Task::class)->findAll());
        // the task no longer exists
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => $taskTitle]));
        $this->assertNull($this->entityManager->getRepository(Task::class)->find($taskId));
    }

    /**
     * Case ok: an administrator deletes a task of the anonymous user
     */
    public function testOkDeleteTaskWithAdminUserForAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToDeleted */
        $taskToDeleted = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $taskTitle = $taskToDeleted->getTitle();
        $taskId = $taskToDeleted->getId();

        // run service
        $this->service->deleteTask($taskToDeleted, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        
        // one task have been deleted
        $this->assertCount($taskNb - 1, $this->entityManager->getRepository(Task::class)->findAll());
        // the task no longer exists
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => $taskTitle]));
        $this->assertNull($this->entityManager->getRepository(Task::class)->find($taskId));
    }

    /**
     * Case KO: the anonymous user tries to deleted a task
     */
    public function testKoDeleteTaskWithAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToDeleted */
        $taskToDeleted = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $taskTitle = $taskToDeleted->getTitle();
        $taskId = $taskToDeleted->getId();

        // run service
        $this->service->deleteTask($taskToDeleted, $this->anonymous);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($taskId);
        $this->assertNotNull($unchangedTask);
        $this->assertEquals($taskId, $unchangedTask->getId());
        $this->assertEquals($taskTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: a user deletes a task from the anonymous author
     */
    public function testKoUserDeleteTaskFromAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToDeleted */
        $taskToDeleted = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $taskTitle = $taskToDeleted->getTitle();
        $taskId = $taskToDeleted->getId();

        // run service
        $this->service->deleteTask($taskToDeleted, $this->user);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Seul un administrateur peut effectuer cette opération.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($taskId);
        $this->assertNotNull($unchangedTask);
        $this->assertEquals($taskId, $unchangedTask->getId());
        $this->assertEquals($taskTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: the user is not authenticated
     */
    public function testKoDeleteTaskWitUnauthenticatedUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToDeleted */
        $taskToDeleted = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $taskTitle = $taskToDeleted->getTitle();
        $taskId = $taskToDeleted->getId();

        // run service
        $this->service->deleteTask($taskToDeleted, null);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas authentifié.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($taskId);
        $this->assertNotNull($unchangedTask);
        $this->assertEquals($taskId, $unchangedTask->getId());
        $this->assertEquals($taskTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: the task does not belong to the authenticated user
     */
    public function testKoDeleteTaskUserIsNotTaskOwner()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToDeleted */
        $taskToDeleted = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $taskTitle = $taskToDeleted->getTitle();
        $taskId = $taskToDeleted->getId();

        // run service
        $this->service->deleteTask($taskToDeleted, $this->admin);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas l'auteur de cette tâche.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($taskId);
        $this->assertNotNull($unchangedTask);
        $this->assertEquals($taskId, $unchangedTask->getId());
        $this->assertEquals($taskTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: the task is not found
     */
    public function testKoInvalidTask()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        // run service
        $this->service->deleteTask(null, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("La tâche n'a pas été trouvée.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
    }

}