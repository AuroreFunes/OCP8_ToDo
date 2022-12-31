<?php

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeleteTaskService extends TaskHelper {

    public function __construct(ManagerRegistry $manager, ValidatorInterface $validator)
    {
        parent::__construct($manager, $validator);
    }

    // ============================================================================================
    // ENTRYPOINT
    // ============================================================================================
    public function deleteTask(?Task $task, ?User $user): self
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
        if (false === $this->checkTaskIsNotEmpty($task)) {
            return $this;
        }

        // Check the owner of the task (or if the user is an administrator)
        if (false === $this->checkTaskOwner($user, $task)) {
            return $this;
        }

        // Make delete
        if (false === $this->makeTaskDelete()) {
            return false;
        }

        $this->status = true;
        return $this;

    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeTaskDelete(): bool
    {
        try {
            $this->manager->remove($this->functArgs->get('task'));
            $this->manager->flush();
        }
        catch (\Exception $e) {
            $this->errMessages->add(self::ERR_DB_ACCESS);
            return false;
        }

        return true;
    }



    // ============================================================================================
    // CHECKING JOBS
    // ============================================================================================
    protected function checkTaskIsNotEmpty(?Task $task): bool
    {
        if (null === $task) {
            $this->errMessages->add(self::ERR_TASK_NOT_FOUND);
            return false;
        }

        return true;
    }


}