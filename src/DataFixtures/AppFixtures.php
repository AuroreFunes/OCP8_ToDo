<?php

namespace App\DataFixtures;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $pwdHasher;

    public function __construct(UserPasswordHasherInterface $pwdHasher)
    {
        $this->pwdHasher = $pwdHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // users creation
        $user = new User();
        $user
            ->setUsername('Anonyme')
            ->setEmail('anonyme@todo.co')
            ->setPassword($this->pwdHasher->hashPassword($user, "Abcd1234"));
        $manager->persist($user);

        $user = new User();
        $user
            ->setUsername('Admin')
            ->setEmail('admin@todo.co')
            ->setPassword($this->pwdHasher->hashPassword($user, "Abcd1234"))
            ->setRoles(['ROLE_ADMIN']);
        $manager->persist($user);

        $user = new User();
        $user
            ->setUsername('User')
            ->setEmail('user@todo.co')
            ->setPassword($this->pwdHasher->hashPassword($user, "Abcd1234"))
            ->setRoles(['ROLE_USER']);
        $manager->persist($user);

        // tasks creation
        for ($i = 1; $i < 6; $i++) {
            $task = new Task();
            $task
                ->setTitle('Titre tâche n° ' . $i)
                ->setContent('Description de la tâche ' . $i)
                ->setCreatedAt(new \DateTime())
                ->toggle(false);
            $manager->persist($task);
        }

        $manager->flush();
    }
}
