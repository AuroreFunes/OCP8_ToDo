<?php

namespace App\Service\User;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

class DeleteUserService extends UserHelper
{
    protected const ERR_OWN_ACCOUNT = "Vous ne pouvez pas supprimer votre propre compte.";
    protected const ERR_CANT_DELETE_ANONYMOUS = "L'utilisateur Anonymous ne peut pas être supprimé.";

    public function __construct(ManagerRegistry $manager)
    {
        parent::__construct($manager);
    }

    // ============================================================================================
    // ENTRYPOINT
    // ============================================================================================
    public function deleteUser(?User $userToDeleted, ?User $authenticatedUser): self
    {
        $this->initHelper();

        // Save parameters
        $this->functArgs->set('userToDeleted', $userToDeleted);

        // Check the authenticated user
        if (false === $this->checkUser($authenticatedUser)) {
            return $this;
        }

        // Check if the authenticated user is admin
        if (false === $this->userIsAdmin($authenticatedUser)) {
            $this->errMessages->add(self::ERR_USER_IN_NOT_ADMIN);
            return $this;
        }

        // Check the user to deleted
        if (false === $this->chekUserToDeleted($userToDeleted)) {
            return $this;
        }

        if (true === $this->actingUserIsAuthenticatedUser($userToDeleted, $authenticatedUser)) {
            $this->errMessages->add(self::ERR_OWN_ACCOUNT);
            return $this;
        }

        // Make deletion
        if (false === $this->makeUserDeletion()) {
            return $this;
        }

        $this->status = true;
        return $this;
    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeUserDeletion(): bool
    {
        /** @var User $anonymous */
        $anonymous = $this->manager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);

        // Link tasks to the Anonymous user
        foreach ($this->functArgs->get('userToDeleted')->getTasks() as $task) {
            /** @var Task $task */
            $anonymous->addTask($task);
        }
        $this->manager->persist($anonymous);

        // Remove actor
        foreach ($this->functArgs->get('userToDeleted')->getLinkedTasks() as $task) {
            /** @var Task $task */
            $task->setActor(null);
        }

        // Delete the user
        try {
            $this->manager->getRepository(User::class)->remove($this->functArgs->get('userToDeleted'), true);
        } catch (\Exception $e) {
            $this->errMessages->add(self::ERR_DB_ACCESS);
            return false;
        }

        return true;
    }

    // ============================================================================================
    // CHECKING JOBS
    // ============================================================================================
    /**
     * Returns false if the user is null, otherwise returns true.
     */
    private function chekUserToDeleted(?User $userToDeleted): bool
    {
        if (null === $userToDeleted) {
            $this->errMessages->add(self::ERR_USER_NOT_FOUND);
            return false;
        }

        if ($userToDeleted->getUserIdentifier() === "Anonymous") {
            $this->errMessages->add(self::ERR_CANT_DELETE_ANONYMOUS);
            return false;
        }

        return true;
    }

}