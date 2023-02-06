<?php

namespace App\Test\Service\Task;

use App\Entity\Task;
use App\Entity\User;
use App\Service\Task\SetTaskProgressService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Required Fixtures :
 *  - "Anonymous" User, is_active = false, with at least 1 task
 *  - "Admin" User, is_active = true, with at least 1 task
 *  - "User" User, is_active = true, with at least 1 task
 *  - at least one task whose actor is Admin
 *  - at least one task whose actor is User
 */
class SetTaskProgressServiceTest extends KernelTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    private SetTaskProgressService $service;
    
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

        $this->service = $container->get(SetTaskProgressService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }

    /**
     * Normal case: a user modifies a task he has created
     */
    public function testOkSetProgressWithSimpleUser()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $oldProgress = $taskToModify->getProgress();
        $oldId = $taskToModify->getId();
        $oldTitle = $taskToModify->getTitle();
        do {
            $newProgress = rand(0, 100);
        } while ($newProgress === $oldProgress);

        // run service
        $this->service->setProgress($taskToModify, $this->user, $newProgress);
        // close the manager to reload the entities
        $this->entityManager->close();

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $modifiedTask */
        $modifiedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]);
        // it is the same entity
        $this->assertEquals($taskToModify->getId(), $oldId);
        // the progress has been modified
        $this->assertSame($newProgress, $modifiedTask->getProgress());
        // the created date has NOT been modified
        $this->assertEquals($taskToModify->getCreatedAt(), $modifiedTask->getCreatedAt());
        // the updated date has been modified
        $this->assertNotEquals($taskToModify->getUpdatedAt(), $modifiedTask->getUpdatedAt());
    }

    /**
     * Case ok: a user modifies a task in which he is an actor
     */
    public function testOkActorSetProgress()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        $tasks = $this->entityManager->getRepository(Task::class)->findBy(['actor' => $this->user]);
        /** @var Task $taskToModify */
        foreach ($tasks as $task) {
            /** @var Task $task */
            if ($task->getAuthor() !== $this->user) {
                $taskToModify = $task;
                break;
            }
        }

        $oldProgress = $taskToModify->getProgress();
        $oldId = $taskToModify->getId();
        $oldTitle = $taskToModify->getTitle();
        do {
            $newProgress = rand(0, 100);
        } while ($newProgress === $oldProgress);

        // run service
        $this->service->setProgress($taskToModify, $this->user, $newProgress);
        // close the manager to reload the entities
        $this->entityManager->close();

        // check status
        $this->assertTrue($this->service->getStatus());

        // count errors
        $this->assertEmpty($this->service->getErrorsMessages());

        // check result
        
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $modifiedTask */
        $modifiedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]);
        // it is the same entity
        $this->assertEquals($taskToModify->getId(), $oldId);
        // the progress has been modified
        $this->assertSame($newProgress, $modifiedTask->getProgress());
        // the created date has NOT been modified
        $this->assertEquals($taskToModify->getCreatedAt(), $modifiedTask->getCreatedAt());
        // the updated date has been modified
        $this->assertNotEquals($taskToModify->getUpdatedAt(), $modifiedTask->getUpdatedAt());
    }

    /**
     * Case KO: the anonymous user tries to modify a task
     */
    public function testKoSetProgressWithAnonymous()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->anonymous->getTasks()->get(count($this->anonymous->getTasks()) - 1);
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();
        $oldProgress = $taskToModify->getProgress();
        do {
            $newProgress = rand(0, 100);
        } while ($newProgress === $oldProgress);

        // run service
        $this->service->setProgress($taskToModify, $this->anonymous, $newProgress);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("L'utilisateur a été désactivé.", $this->service->getErrorsMessages()->get(0));

        // check result
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]);
        // it is the same entity
        $this->assertEquals($unchangedTask->getId(), $oldId);
        // the progress has NOT been modified
        $this->assertSame($oldProgress, $unchangedTask->getProgress());
        // the created date has NOT been modified
        $this->assertEquals($taskToModify->getCreatedAt(), $unchangedTask->getCreatedAt());
        // the updated date has NOT been modified
        $this->assertEquals($taskToModify->getUpdatedAt(), $unchangedTask->getUpdatedAt());
    }

    /**
     * Case KO: a user modifies a task in which he is not an actor
     */
    public function testKoUserIsNotActor()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        foreach ($this->user->getTasks() as $task) {
            /** @var Task $task */
            if ($task->getActor() !== $this->admin) {
                $taskToModify = $task;
                break;
            }
        }
        $oldTitle = $taskToModify->getTitle();
        $oldId = $taskToModify->getId();
        $oldProgress = $taskToModify->getProgress();
        do {
            $newProgress = rand(0, 100);
        } while ($newProgress === $oldProgress);

        // run service
        $this->service->setProgress($taskToModify, $this->admin, $newProgress);
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("Vous n'êtes pas acteur pour cette tâche.", $this->service->getErrorsMessages()->get(0));

        // check result
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]);
        // it is the same entity
        $this->assertEquals($unchangedTask->getId(), $oldId);
        // the progress has NOT been modified
        $this->assertSame($oldProgress, $unchangedTask->getProgress());
        // the created date has NOT been modified
        $this->assertEquals($taskToModify->getCreatedAt(), $unchangedTask->getCreatedAt());
        // the updated date has NOT been modified
        $this->assertEquals($taskToModify->getUpdatedAt(), $unchangedTask->getUpdatedAt());
    }

    /**
     * Test KO: progress is invalid
     */
    public function testKOInvalidProgress()
    {
        // init before run
        $taskNb = count($this->entityManager->getRepository(Task::class)->findAll());

        /** @var Task $taskToModify */
        $taskToModify = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);
        $oldProgress = $taskToModify->getProgress();
        $oldId = $taskToModify->getId();
        $oldTitle = $taskToModify->getTitle();

        // run service
        $this->service->setProgress($taskToModify, $this->user, -1);
        // close the manager to reload the entities
        $this->entityManager->close();

        // check status
        $this->assertFalse($this->service->getStatus());

        // count errors
        $this->assertCount(1, $this->service->getErrorsMessages());

        // read errors
        $this->assertEquals("La progression doit être exprimée en pourcentage.", $this->service->getErrorsMessages()->get(0));

        // check result
        // no tasks have been added or deleted
        $this->assertCount($taskNb, $this->entityManager->getRepository(Task::class)->findAll());
        /** @var Task $unchangedTask */
        $unchangedTask = $this->entityManager->getRepository(Task::class)->findOneBy(['title' => $oldTitle]);
        // it is the same entity
        $this->assertEquals($unchangedTask->getId(), $oldId);
        // the progress has NOT been modified
        $this->assertSame($oldProgress, $unchangedTask->getProgress());
        // the created date has NOT been modified
        $this->assertEquals($taskToModify->getCreatedAt(), $unchangedTask->getCreatedAt());
        // the updated date has NOT been modified
        $this->assertEquals($taskToModify->getUpdatedAt(), $unchangedTask->getUpdatedAt());
    }

}