<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CreateUserType;
use App\Form\EditUserType;
use App\Repository\UserRepository;
use App\Service\User\CreateUserService;
use App\Service\User\DeleteUserService;
use App\Service\User\EditUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class UserController extends AbstractController
{
    /**
     * @IsGranted("ROLE_ADMIN")
     * @Route("/users", name="app_user_list")
     */
    public function listAction(UserRepository $userRepository)
    {
        return $this->render('user/list.html.twig', ['users' => $userRepository->findAll()]);
    }

    /**
     * @IsGranted("ROLE_ADMIN")
     * @Route("/users/create", name="app_user_create")
     */
    public function createAction(Request $request, CreateUserService $service)
    {
        $user = new User();
        $form = $this->createForm(CreateUserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $service->createUser($user, $this->getUser());

            if (true === $service->getStatus()) {
                $this->addFlash('success', 'Le nouvel utilisateur a été bien été ajouté.');
                return $this->redirectToRoute('app_user_list');
            }

            // status = false !
            foreach ($service->getErrorsMessages() as $message) {
                $this->addFlash('error', $message);
            }
        }

        return $this->render('user/form.html.twig', ['form' => $form->createView(), 'mode' => "create"]);
    }

    /**
     * @IsGranted("ROLE_ADMIN")
     * @Route("/users/{id}/edit", name="app_user_edit")
     */
    public function editAction(?User $user, Request $request, EditUserService $service)
    {
        $form = $this->createForm(EditUserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $service->editUser($user, $this->getUser());

            $this->addFlash('success', "L'utilisateur a bien été modifié");

            return $this->redirectToRoute('app_user_list');
        }

        return $this->render('user/form.html.twig', ['form' => $form->createView(), 'user' => $user, 'mode' => "edit"]);
    }

    /**
     * @IsGranted("ROLE_ADMIN")
     * @Route("/users/{id}/delete", name="app_user_delete")
     */
    public function deleteAction(?User $user, DeleteUserService $service)
    {
        $service->deleteUser($user, $this->getUser());

            if (true === $service->getStatus()) {
                $this->addFlash('success', "L'utilisateur a bien été supprimé.");
                return $this->redirectToRoute('app_user_list');
            }

            // status = false !
            foreach ($service->getErrorsMessages() as $message) {
                $this->addFlash('error', $message);
            }

        return $this->redirectToRoute('app_user_list');
    }

}
