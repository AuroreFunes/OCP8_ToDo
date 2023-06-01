<?php

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SetTaskProgressService extends TaskHelper
{
    private const ERR_INVALID_PROGRESS = "La progression doit être exprimée en pourcentage.";

    public function __construct(ManagerRegistry $manager, ValidatorInterface $validator)
    {
        parent::__construct($manager, $validator);
    }

    // ============================================================================================
    // ENTRYPOINT
    // ============================================================================================
    public function setProgress(?Task $task, ?User $user, int $progress): self
    {
        $this->initHelper();

        // Save parameters
        $this->functArgs->set('user', $user);
        $this->functArgs->set('task', $task);
        $this->functArgs->set('progress', $progress);

        // Check the user
        if (false === $this->checkUser($user)) {
            return $this;
        }

        // Check the task
        if (false === $this->checkTask($task)) {
            return $this;
        }

        // Check the actor of the task
        if (false === $this->checkTaskActor($user, $task)) {
            $this->errMessages->add(self::ERR_USER_IS_NOT_ACTOR);
            return $this;
        }

        // Check the progress
        if (false === $this->checkParameters()) {
            return $this;
        }

        // Make update
        if (false === $this->makeTaskUpdate()) {
            return false;
        }

        $this->status = true;
        return $this;
    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeTaskUpdate(): bool
    {
        $this->functArgs->get('task')
            ->setProgress($this->functArgs->get('progress'))
            ->setUpdatedAt(new \DateTime());

        try {
            $this->manager->persist($this->functArgs->get('task'));
            $this->manager->flush();
        } catch (\Exception $e) {
            $this->errMessages->add(self::ERR_DB_ACCESS);
            return false;
        }

        return true;
    }

    // ============================================================================================
    // CHECK PARAMETERS
    // ============================================================================================
    protected function checkParameters()
    {
        if (false === filter_var($this->functArgs->get('progress'), FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 0,
            'max_range' => 100
        ]])) {
            $this->errMessages->add(self::ERR_INVALID_PROGRESS);
            return false;
        }

        return true;
    }

}