<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Service\Task\CreateTaskService;
use App\Service\Task\DeleteTaskService;
use App\Service\Task\EditTaskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class TaskController extends AbstractController
{

    private const ERR_AUTHENTICATION_REQUIRED = "Vous devez être authentifié pour accéder à cette fonctionnalité.";

    /**
     * @Route("/tasks", name="app_task_list")
     */
    public function listAction(TaskRepository $taskRepository)
    {
        if (null === $this->getUser()) {
            $this->addFlash('error', self::ERR_AUTHENTICATION_REQUIRED);
            return $this->redirectToRoute('app_home');
        }

        return $this->render('task/list.html.twig', ['tasks' => $taskRepository->findAll()]);
    }

    /**
     * @Route("/tasks/create", name="app_task_create")
     */
    public function createAction(Request $request, CreateTaskService $service)
    {
        if (null === $this->getUser()) {
            $this->addFlash('error', self::ERR_AUTHENTICATION_REQUIRED);
            return $this->redirectToRoute('app_home');
        }

        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $service->createTask($task, $this->getUser());

            if (true === $service->getStatus()) {
                $this->addFlash('success', 'La tâche a été bien été ajoutée.');
                return $this->redirectToRoute('app_task_list');
            }

            // status = false !
            foreach ($service->getErrorsMessages() as $message) {
                $this->addFlash('error', $message);
            }
        }

        return $this->render('task/taskForm.html.twig', ['form' => $form->createView(), 'mode' => "create"]);
    }

    /**
     * @Route("/tasks/{id}/edit", name="app_task_edit")
     */
    public function editAction(?Task $task, Request $request, EditTaskService $service)
    {
        if (null === $this->getUser()) {
            $this->addFlash('error', self::ERR_AUTHENTICATION_REQUIRED);
            return $this->redirectToRoute('app_home');
        }

        if (null === $task) {
            $this->addFlash('error', "La tâche demandée n'a pas été trouvée.");
            return $this->redirectToRoute('app_task_list');
        }

        // If the author of the task is "Anonymous", an administrator can modify it
        if ($task->getAuthor()->getUserIdentifier() === "Anonymous") {
            if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
                $this->addFlash('error', "Seul un administrateur peut modifier cette tâche.");
                return $this->redirectToRoute('app_task_list');
            }
        }

        // Modification is only possible if the user is the author of the task
        if ($task->getAuthor() !== $this->getUser()) {
            $this->addFlash('error', "Seul L'auteur d'une tâche est autorisé à la modifier.");
            return $this->redirectToRoute('app_task_list');
        }

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $service->editTask($task, $this->getUser());

            if (true === $service->getStatus()) {
                $this->addFlash('success', 'La tâche a été bien été modifiée.');
                return $this->redirectToRoute('app_task_list');
            }

            // status = false !
            foreach ($service->getErrorsMessages() as $message) {
                $this->addFlash('error', $message);
            }
        }

        return $this->render('task/taskForm.html.twig', [
            'form' => $form->createView(),
            'task' => $task,
            'mode' => "edit"
        ]);
    }

    /**
     * @Route("/tasks/{id}/toggle", name="app_task_toggle")
     */
    public function toggleTaskAction(Task $task, EntityManagerInterface $em, DeleteTaskService $service)
    {
        $task->toggle(!$task->isDone());
        $em->persist($task);
        $em->flush();


        

        $this->addFlash('success', sprintf('La tâche %s a bien été marquée comme faite.', $task->getTitle()));

        return $this->redirectToRoute('app_task_list');
    }

    /**
     * @Route("/tasks/{id}/delete", name="app_task_delete")
     */
    public function deleteTaskAction(Task $task, EntityManagerInterface $em)
    {
        $em->remove($task);
        $em->flush();

        $this->addFlash('success', 'La tâche a bien été supprimée.');

        return $this->redirectToRoute('app_task_list');
    }
}
