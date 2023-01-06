<?php

namespace App\Test\Task;

use App\Entity\Task;
use App\Entity\User;
use App\Service\Task\EditTaskService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false, with at least 1 task
 *  - "Admin" User, is_active = true, with at least 1 task
 *  - "User" User, is_active = true, with at least 1 task
 */
class EditTaskServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private EditTaskService $service;
    
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

        $this->service = $container->get(EditTaskService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case: a user modifies a task he has created
     */
    public function testOkEditTaskWithSimpleUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();

        // new value for task
        $taskToModify
            ->setTitle("Modified title " . $this->uniqid)
            ->setContent("Modified content " . $this->uniqid);

        // run service
        $this->service->editTask($taskToModify, $this->user);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $modifiedTask */
        $modifiedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => "Modified title " . $this->uniqid]);
        // it is the same entity
        $this->assertEquals($taskToModify->getId(), $oldId);
        // the title has been modified
        $this->assertEquals("Modified title " . $this->uniqid, $modifiedTask->getTitle());
        // the content has been modified
        $this->assertEquals("Modified content " . $this->uniqid, $modifiedTask->getContent());

        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]));
    }

    /**
     * Case ok: an administrator modifies a task of the anonymous user
     */
    public function testOkEditTaskWithAdminUserForAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();

        // new value for task
        $taskToModify
            ->setTitle("Still modified title " . $this->uniqid)
            ->setContent("Still modified content " . $this->uniqid);

        // run service
        $this->service->editTask($taskToModify, $this->admin);

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $modifiedTask */
        $modifiedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => "Still modified title " . $this->uniqid]);
        // it is the same entity
        $this->assertEquals($taskToModify->getId(), $oldId);
        // the title has been modified
        $this->assertEquals("Still modified title " . $this->uniqid, $modifiedTask->getTitle());
        // the content has been modified
        $this->assertEquals("Still modified content " . $this->uniqid, $modifiedTask->getContent());

        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]));
    }

    /**
     * Case KO: the anonymous user tries to modify a task
     */
    public function testKoEditTaskWithAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();

        // new value for task
        $taskToModify
            ->setTitle("Forbidden " . $this->uniqid)
            ->setContent("Forbidden " . $this->uniqid);

        // run service
        $this->service->editTask($taskToModify, $this->anonymous);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => "Forbidden " . $this->uniqid]));
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($oldId);
        // the title has not been modified
        $this->assertEquals($oldTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: a user modify a task from the anonymous author
     */
    public function testKoUserDeleteTaskFromAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();

        // new value for task
        $taskToModify
            ->setTitle("Forbidden " . $this->uniqid)
            ->setContent("Forbidden " . $this->uniqid);

        // run service
        $this->service->editTask($taskToModify, $this->user);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Seul un administrateur peut effectuer cette opération.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => "Forbidden " . $this->uniqid]));
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($oldId);
        // the title has not been modified
        $this->assertEquals($oldTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: the user is not authenticated
     */
    public function testKoEditTaskWitUnauthenticatedUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();

        // new value for task
        $taskToModify
            ->setTitle("Forbidden " . $this->uniqid)
            ->setContent("Forbidden " . $this->uniqid);

        // run service
        $this->service->editTask($taskToModify, null);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas authentifié.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => "Forbidden " . $this->uniqid]));
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($oldId);
        // the title has not been modified
        $this->assertEquals($oldTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: the task does not belong to the authenticated user
     */
    public function testKoEditTaskUserIsNotTaskOwner()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();

        // new value for task
        $taskToModify
            ->setTitle("Forbidden " . $this->uniqid)
            ->setContent("Forbidden " . $this->uniqid);

        // run service
        $this->service->editTask($taskToModify, $this->admin);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas l'auteur de cette tâche.", $this->service->getErrorsMessages()->get(0));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        $this->assertNull($this->entityManager->getRepository(Task::class)->findOneBy(['title' => "Forbidden " . $this->uniqid]));
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->find($oldId);
        // the title has not been modified
        $this->assertEquals($oldTitle, $unchangedTask->getTitle());
    }

    /**
     * Case KO: the task is not valid
     */
    public function testKoInvalidTask()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        // new value for task
        $taskToModify = new Task();

        // run service
        $this->service->editTask($taskToModify, $this->admin);

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(2, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous devez saisir un titre.", $this->service->getErrorsMessages()->get(0));
        $this->assertEquals("Vous devez saisir du contenu.", $this->service->getErrorsMessages()->get(1));

        // check result
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
    }

}