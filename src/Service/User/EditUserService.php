<?php

namespace App\Service\User;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EditUserService extends UserHelper
{
    protected const ERR_CANT_EDIT_ANONYMOUS = "L'utilisateur Anonymous ne peut pas être modifié.";

    protected ValidatorInterface $validator;
    protected UserPasswordHasherInterface $pwdHasher;

    public function __construct(
        ManagerRegistry $manager, 
        ValidatorInterface $validator,
        UserPasswordHasherInterface $pwdHasher
    ) {
        parent::__construct($manager);

        $this->validator = $validator;
        $this->pwdHasher = $pwdHasher;
    }

    // ============================================================================================
    // ENTRYPOINT
    // ============================================================================================
    public function editUser(?User $userToUpdate, ?User $authenticatedUser): self
    {
        $this->initHelper();

        // Save parameters
        $this->functArgs->set('authenticatedUser', $authenticatedUser);
        $this->functArgs->set('userToUpdate', $userToUpdate);

        // Check if the authenticated user is admin
        if (false === $this->checkUserIsValidAndAdmin($authenticatedUser)) {
            return $this;
        }

        // Check the new user
        if (false === $this->checkUserToUpdate($userToUpdate)) {
            return $this;
        }

        // Make creation
        if (false === $this->makeUserUpdate()) {
            return $this;
        }

        $this->status = true;
        return $this;
    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeUserUpdate(): bool
    {
        if (empty($this->functArgs->get('userToUpdate')->getRoles())) {
            $this->functArgs->get('userToUpdate')->addRole([self::ROLE_USER]);
        }

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

        // The Anonymous user cannot be modified
        /** @var User $anonymous */
        $anonymous = $this->manager->getRepository(User::class)->findOneBy(['username' => "Anonymous"]);
        if ($anonymous->getId() === $user->getId()) {
            $this->errMessages->add(self::ERR_CANT_EDIT_ANONYMOUS);
            return false;
        }

        /** @var bool $userIsValid */
        $userIsValid = true;

        // Validate user
        $errors = $this->validator->validate($user, null, ['update']);

        if ($errors->count() > 0) {
            $userIsValid = false;

            // Add errors messages
            foreach ($errors as $error) {
                $this->errMessages->add($error->getMessage());
            }
        }

        // Check roles
        if (empty($user->getRoles())) {
            $user->setRoles([self::ROLE_USER]);
        }

        if (false === $this->checkUserRoles($user)) {
            return false;
        }

        return $userIsValid;
    }

}