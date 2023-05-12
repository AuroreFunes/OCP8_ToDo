<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Task;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;


class TaskControllerTest extends WebTestCase
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    // UTILITIES
    private string  $uniqid;
    private User    $anonymous;
    private User    $admin;
    private User    $user;

    private ?KernelBrowser $client = null;

    //private HttpBrowser $browser;
    //private UrlGeneratorInterface $router;
    //private RouterInterface $router;
    private $router;

    protected function setUp(): void
    {
        
        $this->client = static::createClient();
        $container = self::getContainer();

        $this->router = $container->get('router');

        $this->entityManager = $this->client->getContainer()
        ->get('doctrine')
        ->getManager();

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);

    }


    public function testListAction()
    {
        // authenticate User
        $this->client->loginUser($this->user);
        // send request
        $this->client->request(Request::METHOD_GET, "/tasks");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }


    public function testListMyTasksAction()
    {
        // authenticate User
        $this->client->loginUser($this->user);
        // send request
        $this->client->request(Request::METHOD_GET, "/tasks/user");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }


    public function testClosedListActionOk()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        // get the task list
        //$crawler = $this->client->request('GET', $this->router->generate('app_task_list_closed'));
        $crawler = $this->client->request(Request::METHOD_GET, "/tasks/closed");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame("Créer une tâche", $crawler->filter('a.btn-info')->text());
    }


    public function testCreateTaskAction()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        $crawler = $this->client->request(Request::METHOD_GET, '/tasks/create');
        $form = $crawler->selectButton('btn-submit')->form();

        $form['task[title]'] = "Title " . $this->uniqid;
        $form['task[content]'] = "Contenu de la tâche";
        $form['task[actor]'] = $this->user->getId();
        $form['task[progress]'] = 42;
        $form['task[deadLine][month]'] = 6;
        $form['task[deadLine][day]'] = 3;
        $form['task[deadLine][year]'] = 2023;

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success','La tâche a été bien été ajoutée.');
    }

    public function testEditTaskAction()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        /** @var Task $lastTask */
        $lastTask = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);

        $crawler = $this->client->request(Request::METHOD_GET, '/tasks/' . $lastTask->getId() . '/edit');
        $form = $crawler->selectButton('btn-submit')->form();

        $form['task[title]'] = "Edit " . $lastTask->getTitle();
        $form['task[content]'] = "Edit content --- " . $lastTask->getContent();
        $form['task[actor]'] = $this->user->getId();
        $form['task[progress]'] = 51;
        $form['task[deadLine][month]'] = 7;
        $form['task[deadLine][day]'] = 21;
        $form['task[deadLine][year]'] = 2023;

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success','La tâche a été bien été modifiée.');
    }

    public function testEditTaskActionAnonymous()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        /** @var Task $lastTask */
        $lastTask = $this->anonymous->getTasks()->get(count($this->user->getTasks()) - 1);

        $crawler = $this->client->request(Request::METHOD_GET, '/tasks/' . $lastTask->getId() . '/edit');
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-danger','Vous ne pouvez pas effectuer cette action');
    }

    public function testDeleteTaskAction()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        /** @var Task $lastTask */
        $lastTask = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);

        $crawler = $this->client->request(Request::METHOD_GET, '/tasks/' . $lastTask->getId() . '/delete');

        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success','La tâche a été bien été supprimée.');
    }

    // ============================================================================================
    // XHR
    // ============================================================================================
    public function testXhrDeleteTask()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        /** @var Task $lastTask */
        $lastTask = $this->user->getTasks()->get(count($this->user->getTasks()) - 1);

        //$crawler = $this->client->xmlHttpRequest('POST', '/deleteTask', ['id' => $lastTask->getId()]);
        $crawler = $this->client->xmlHttpRequest('POST', '/deleteTask', []);

        //$this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertResponseIsSuccessful();

    }

}