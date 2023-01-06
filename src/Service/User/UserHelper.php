<?php

namespace App\Service\User;

use App\Entity\User;
use App\Service\ServiceHelper;
use Doctrine\Persistence\ManagerRegistry;

class UserHelper extends ServiceHelper {

    protected const ERR_UNDEFINED_USER  = "L'utilisateur n'est pas défini.";
    protected const ERR_USER_NOT_FOUND  = "L'utilisateur n'a pas été trouvé.";
    protected const ERR_INVALID_ROLE    = "Le rôle choisi n'est pas valide : ";


    public function __construct(ManagerRegistry $manager)
    {
        parent::__construct($manager);
    }

    /** 
     * Checks the user's roles. Returns false if one of the roles is invalid, otherwise returns true.
     * Adds error messages if necessary.
     */
    protected function checkUserRoles(User $user): bool
    {
        $userIsValid = true;

        foreach($user->getRoles() as $role) {
            if (!in_array($role, self::USER_ROLES_AVALIABLE)) {
                $userIsValid = false;
                $this->errMessages->add(self::ERR_INVALID_ROLE . $role);
            }
        }

        return $userIsValid;
    }

}