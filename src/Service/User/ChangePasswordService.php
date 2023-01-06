<?php

namespace App\Service\User;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChangePasswordService extends UserHelper
{
    protected const ERR_UNABLE_TO_CHANGE_PASSWORD = 
        "Il n'est pas possible de changer le mot de passe d'un autre utilisateur.";

    protected ValidatorInterface $validator;
    protected UserPasswordHasherInterface $pwdHasher;

    public function __construct(
        ManagerRegistry $manager, 
        ValidatorInterface $validator,
        UserPasswordHasherInterface $pwdHasher
    )
    {
        parent::__construct($manager);

        $this->validator = $validator;
        $this->pwdHasher = $pwdHasher;
    }

    // ============================================================================================
    // ENTRYPOINT
    // ============================================================================================
    public function changePassword(?User $userToUpdate, ?User $authenticatedUser): self
    {
        $this->initHelper();

        // Save parameters
        $this->functArgs->set('authenticatedUser', $authenticatedUser);
        $this->functArgs->set('userToUpdate', $userToUpdate);

        // Check the authenticated user
        if (false === $this->checkUser($authenticatedUser)) {
            return $this;
        }

        // Check the new value
        if (false === $this->checkUserToUpdate($userToUpdate)) {
            return $this;
        }

        // The user can only change the password for their own account
        if (false === $this->actingUserIsAuthenticatedUser($userToUpdate, $authenticatedUser)) {
            $this->errMessages->add(self::ERR_UNABLE_TO_CHANGE_PASSWORD);
            return $this;
        }

        // Make update
        if (false === $this->makeChange()) {
            return false;
        }

        $this->status = true;
        return $this;
    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeChange(): bool
    {
        $this->functArgs->get('userToUpdate')
            ->setPassword($this->pwdHasher->hashPassword(
                $this->functArgs->get('userToUpdate'), 
                $this->functArgs->get('userToUpdate')->getPassword()));

        try {
            $this->manager->persist($this->functArgs->get('userToUpdate'));
            $this->manager->flush();
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
     * Returns false if the user is null or invalid, otherwise returns true.
     */
    protected function checkUserToUpdate(?User $user): bool
    {
        if (null === $user) {
            $this->errMessages->add(self::ERR_UNDEFINED_USER);
            return false;
        }

        // Validate user
        $errors = $this->validator->validate($user, null, ['changePassword']);

        if ($errors->count() > 0) {
            // Add errors messages
            foreach ($errors as $error) {
                $this->errMessages->add($error->getMessage());
            }

            return false;
        }

        return true;
    }

}