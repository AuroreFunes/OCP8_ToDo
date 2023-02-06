<?php

namespace App\Service\User;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateUserService extends UserHelper
{

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
    public function createUser(?User $newUser, ?User $authenticatedUser): self
    {
        $this->initHelper();

        // Save parameters
        $this->functArgs->set('authenticatedUser', $authenticatedUser);
        $this->functArgs->set('newUser', $newUser);

        // Check if the authenticated user is admin
        if (false === $this->checkUserIsValidAndAdmin($authenticatedUser)) {
            return $this;
        }

        // Check the new user
        if (false === $this->checkNewUser($newUser)) {
            return $this;
        }

        // Make creation
        if (false === $this->makeUserCreation()) {
            return $this;
        }

        $this->status = true;
        return $this;
    }

    // ============================================================================================
    // JOBS
    // ============================================================================================
    protected function makeUserCreation(): bool
    {
        $this->functArgs->get('newUser')
            ->setPassword($this->pwdHasher->hashPassword(
                $this->functArgs->get('newUser'), 
                $this->functArgs->get('newUser')->getPassword()))
            ->setIsActive(true);

        try {
            $this->manager->getRepository(User::class)->add($this->functArgs->get('newUser'), true);
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
    protected function checkNewUser(?User $user): bool
    {
        if (null === $user) {
            $this->errMessages->add(self::ERR_UNDEFINED_USER);
            return false;
        }

        /** @var bool $userIsValid */
        $userIsValid = true;

        // Validate user
        $errors = $this->validator->validate($user, null, ['create']);

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