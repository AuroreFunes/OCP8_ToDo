<?php

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\User;
use App\Service\ServiceHelper;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TaskHelper extends ServiceHelper {

    protected const ERR_UNDEFINED_TASK          = "La tâche n'est pas renseignée.";
    protected const ERR_TASK_NOT_FOUND          = "La tâche n'a pas été trouvée.";
    protected const ERR_USER_IS_NOT_TASK_OWNER  = "Vous n'êtes pas l'auteur de cette tâche.";
    protected const ERR_ACTOR_IS_INACTIVE       = "La tâche ne peut pas être affectée à un utilisateur inactif.";
    protected const ERR_USER_IS_NOT_ACTOR       = "Vous n'êtes pas acteur pour cette tâche.";

    protected ValidatorInterface $validator;

    public function __construct(ManagerRegistry $manager, ValidatorInterface $validator)
    {
        parent::__construct($manager);

        $this->validator = $validator;
    }

    // ============================================================================================
    // CHECKING JOBS
    // ============================================================================================
    /**
     * Returns false if the task is null or invalid, otherwise returns true.
     */
    protected function checkTask(?Task $task): bool
    {
        if (null === $task) {
            $this->errMessages->add(self::ERR_UNDEFINED_TASK);
            return false;
        }

        // Validate task
        $taskIsValid = true;

        $errors = $this->validator->validate($task);

        if ($errors->count() > 0) {
            // Add errors messages
            foreach ($errors as $error) {
                $this->errMessages->add($error->getMessage());
            }

            $taskIsValid = false;
        }

        if (null !== $task->getActor()) {
            // The task actor must not be an inactive user
            if (false === $this->userIsActive($task->getActor())) {
                $this->errMessages->add(self::ERR_ACTOR_IS_INACTIVE);
                $taskIsValid = false;
            }
        }

        return $taskIsValid;
    }

    /**
     * If the author of the task is anonymous, returns true if the user is an administrator, otherwise returns false.
     * Otherwise, returns true if the user is the author of the task, otherwise returns false.
     */
    protected function checkTaskOwner(User $user, Task $task): bool
    {
        // If the author of the task is "Anonymous", an administrator can modify or delete it
        if ($task->getAuthor()->getUserIdentifier() === "Anonymous") {
            if (!$this->userIsAdmin($user)) {
                $this->errMessages->add(self::ERR_USER_IN_NOT_ADMIN);
                return false;
            }

            return true;
        }

        // Modification is only possible if the user is the author of the task
        if ($task->getAuthor() !== $user) {
            $this->errMessages->add(self::ERR_USER_IS_NOT_TASK_OWNER);
            return false;
        }

        return true;
    }

    /**
     * Returns true if the user is the author or actor of the task.
     * Otherwise returns false.
     */
    protected function checkTaskActor(User $user, Task $task): bool
    {
        // Modification is only possible if the user is the author of the task
        if ($task->getAuthor() === $user) {
            return true;
        }

        // Modification is only possible if the user is the actor of the task
        if ($task->getActor() === $user) {
            return true;
        }

        return false;
    }

}