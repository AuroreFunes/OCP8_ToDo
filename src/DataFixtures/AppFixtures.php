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
        // anonymous user creation
        $anonymous = new User();
        $anonymous
            ->setUsername('Anonymous')
            ->setEmail('anonymous@todo.co')
            ->setPassword($this->pwdHasher->hashPassword($anonymous, "Abcd1234"))
            ->setIsActive(false);
        $manager->persist($anonymous);

        // admin user
        $admin = new User();
        $admin
            ->setUsername('Admin')
            ->setEmail('admin@todo.co')
            ->setPassword($this->pwdHasher->hashPassword($admin, "Abcd1234"))
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true);
        $manager->persist($admin);

        // simple user
        $user = new User();
        $user
            ->setUsername('User')
            ->setEmail('user@todo.co')
            ->setPassword($this->pwdHasher->hashPassword($user, "Abcd1234"))
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true);
        $manager->persist($user);

        // other users
        $userTab = [];
        for ($i = 0; $i < 4; $i++) {
            $otherUser = new User();
            $otherUser
                ->setUsername('User ' . $i + 1)
                ->setEmail('user' . $i + 1 . '@todo.co')
                ->setPassword($this->pwdHasher->hashPassword($otherUser, "Abcd1234"))
                ->setRoles(['ROLE_USER'])
                ->setIsActive(true);
            $manager->persist($otherUser);

            $userTab[] = $otherUser;
        }

        // tasks creation
        for ($i = 1; $i < 20; $i++) {
            $task = new Task();
            $task
                ->setTitle('Titre tâche n° ' . $i)
                ->setContent('Description de la tâche ' . $i)
                ->setCreatedAt(new \DateTime())
                ->toggle(false)
                ->setAuthor($anonymous)
                ->setProgress(rand(0, 100))
                ->setActor($userTab[array_rand($userTab)]);

            if ($i < 6) {
                $task->setAuthor($anonymous);
            }
            elseif ($i < 8) {
                $task->setAuthor($admin);

                if ($i === 7) {
                    $task->setActor($user);
                }
            }
            elseif ($i < 10) {
                $task->setAuthor($user);
            }
            else {
                $task->setAuthor($userTab[$i % 4]);
            }

            $manager->persist($task);
        }

        $manager->flush();
    }
}
