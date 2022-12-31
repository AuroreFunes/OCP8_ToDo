<?php

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateTaskService extends TaskHelper {

    protected ValidatorInterface $validator;

    public function __construct(ManagerRegistry $manager, ValidatorInterface $validator)
    {
        parent::__construct($manager, $validator);
    }

    // ============================================================================================
    // ENTRYPOINT
    // ============================================================================================
    public function createTask(?Task $task, ?User $user): self
    {
        $this->initHelper();

        // Save parameters
        $this->functArgs->set('user', $user);
        $this->functArgs->set('task', $task);

        // Check the user
        if (false === $this->checkUser($user)) {
            return $this;
        }

        // Check the task
        if (false === $this->checkTask($task)) {
            return $this;
        }

        // Make creation
        if (false === $this->makeTaskCreation()) {
            return false;
        }

        $this->status = true;
        return $this;
    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeTaskCreation(): bool
    {
        $this->functArgs->get('task')
            ->setAuthor($this->functArgs->get('user'))
            ->setCreatedAt(new \DateTime())
            ->setIsDone(false);

        try {
            $this->manager->persist($this->functArgs->get('task'));
            $this->manager->flush();
        } catch (\Exception $e) {
            $this->errMessages->add(self::ERR_DB_ACCESS);
            return false;
        }

        return true;
    }

}