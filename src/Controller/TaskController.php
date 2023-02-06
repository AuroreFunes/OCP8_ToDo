<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskProgressType;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Service\Task\CreateTaskService;
use App\Service\Task\DeleteTaskService;
use App\Service\Task\EditTaskService;
use App\Service\Task\SetTaskProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends AbstractController
{

    private const ERR_AUTHENTICATION_REQUIRED = "Vous devez être authentifié pour accéder à cette fonctionnalité.";

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks", name="app_task_list")
     */
    public function listAction(TaskRepository $taskRepository)
    {

        $progressForm = $this->createForm(TaskProgressType::class, new Task());

        return $this->render('task/list.html.twig', [
            'tasks' => $taskRepository->findAll(),
            'taskProgressForm' => $progressForm->createView() 
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/user", name="app_task_list_user")
     */
    public function listMyTasksAction(TaskRepository $taskRepository)
    {

        $tasks = $taskRepository->findTasksByUser($this->getUser());
        $progressForm = $this->createForm(TaskProgressType::class, new Task());

        return $this->render('task/list.html.twig', [
            'tasks' => $tasks, 
            'taskProgressForm' => $progressForm->createView()
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/closed", name="app_task_list_closed")
     */
    public function listClosedAction(TaskRepository $taskRepository)
    {


        $task = $taskRepository->findBy(['isDone' => true]);

        return $this->render('task/list.html.twig', [
            'tasks' => $task,
            'taskProgressForm' => $this->createForm(TaskProgressType::class, new Task())->createView()
        ]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/create", name="app_task_create")
     */
    public function createAction(Request $request, CreateTaskService $service)
    {


        $task = new Task();
        // By default, the task actor is the author
        $task->setActor($this->getUser());

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

        return $this->render('task/taskForm.html.twig', [
            'form' => $form->createView(), 
            'mode' => "create"]);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/{id}/edit", name="app_task_edit")
     */
    public function editAction(?Task $task, Request $request, EditTaskService $service)
    {


        if (null === $task) {
            $this->addFlash('error', "La tâche demandée n'a pas été trouvée.");
            return $this->redirectToRoute('app_task_list');
        }

        // The task can only be modified if the author is Anonymous and the user is an administrator 
        // OR if the user is the author of the task
        if (!(
            ($task->getAuthor()->getUserIdentifier() === "Anonymous" && $this->isGranted('ROLE_ADMIN'))
            || $task->getAuthor() === $this->getUser())
        ) {
            $this->addFlash('error', 'Vous ne pouvez pas effectuer cette action');
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
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/{id}/toggle", name="app_task_toggle")
     */
    public function toggleTaskAction(Task $task, EntityManagerInterface $em)
    {
        $task->toggle(!$task->isDone());
        $em->persist($task);
        $em->flush();
        

        $this->addFlash('success', sprintf('La tâche %s a bien été marquée comme faite.', $task->getTitle()));

        return $this->redirectToRoute('app_task_list');
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/{id}/delete", name="app_task_delete")
     */
    public function deleteTaskAction(Task $task, DeleteTaskService $service)
    {
        if (null === $this->getUser()) {
            $this->addFlash('error', self::ERR_AUTHENTICATION_REQUIRED);
            return $this->redirectToRoute('app_home');
        }

        if (null === $task) {
            $this->addFlash('error', "La tâche demandée n'a pas été trouvée.");dd('oups');
            return $this->redirectToRoute('app_task_list');
        }

        // The task can only be deleted if the author is Anonymous and the user is an administrator 
        // OR if the user is the author of the task
        /*
        if (!(
            ($task->getAuthor()->getUserIdentifier() === "Anonymous" 
                && in_array("ROLE_ADMIN", $this->getUser()->getRoles()))
            || $task->getAuthor() === $this->getUser()
        )) {
            $this->addFlash('error', 'Vous ne pouvez pas effectuer cette action');
            return $this->redirectToRoute('app_task_list');
        }
        */

        $service->deleteTask($task, $this->getUser());

        if (true === $service->getStatus()) {
            $this->addFlash('success', 'La tâche a été bien été supprimée.');
            return $this->redirectToRoute('app_task_list');
        }

        // status = false !
        foreach ($service->getErrorsMessages() as $message) {
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('app_task_list');
    }

    // ============================================================================================
    // XML HTTP REQUEST
    // ============================================================================================

    /**
     * @Route("/deleteTask/", name="xhr_task_delete")
     */
    public function xhrDeleteTrick(
        Request $request,
        TaskRepository $taskRepository,
        DeleteTaskService $service
    ) {

        $params = $request->request->all();

        if (empty($params['id'])) {
            $this->addFlash('error', "La tâche à supprimer doit être spécifiée.");
            return new JsonResponse("La tâche doit être spécifiée", Response::HTTP_BAD_REQUEST);
        }

        $task = $taskRepository->find($params['id']);
        $service->deleteTask($task, $this->getUser());

        if (true === $service->getStatus()) {
            $this->addFlash('success', 'La tâche a été bien été supprimée.');
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        // status = false !
        foreach ($service->getErrorsMessages() as $message) {
            $this->addFlash('error', $message);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @IsGranted("ROLE_USER")
     * @Route("/tasks/progress", name="xhr_task_progress")
     */
    public function setProgressAction(
        Request $request, 
        TaskRepository $taskRepository, 
        SetTaskProgressService $service
    ) {
        if (null === $this->getUser()) {
            $this->addFlash('error', self::ERR_AUTHENTICATION_REQUIRED);
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $params = $request->request->all();

        if (empty($params['id'])) {
            $this->addFlash('error', "La tâche à supprimer doit être spécifiée.");
            return new JsonResponse("La tâche doit être spécifiée", Response::HTTP_BAD_REQUEST);
        }

        $task = $taskRepository->find($params['id']);

        if (null === $task) {
            $this->addFlash('error', "La tâche demandée n'a pas été trouvée.");
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        if (empty($params['progress'])) {
            $this->addFlash('error', "La progression doit être spécifiée.");
            return new JsonResponse("La progression doit être spécifiée.", Response::HTTP_BAD_REQUEST);
        }

        $service->setProgress($task, $this->getUser(), $params['progress']);

        if (true === $service->getStatus()) {
            $this->addFlash('success', 'La progression de la tâche a été bien été modifiée.');
            return new JsonResponse(null, Response::HTTP_OK);
        }

        // status = false !
        foreach ($service->getErrorsMessages() as $message) {
            $this->addFlash('error', $message);
        }

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
