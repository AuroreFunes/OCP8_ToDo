<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

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

    private $client;
    private HttpBrowser $browser;
    //private UrlGeneratorInterface $router;
    //private RouterInterface $router;
    private $router;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = self::getContainer();

        $this->browser = new HttpBrowser(HttpClient::create());

        //$this->router = new RouterInterface();

        $this->router = $container->get('router');

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        //$this->service = $container->get(ChangePasswordService::class);

        $this->uniqid = uniqid();

        // Init Users
        $this->anonymous = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Anonymous']);
        $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'Admin']);
        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'User']);
    }


    public function loginUser()
    {
        $crawler = $this->browser->request('GET', 'http://localhost:8000/login');
        $form = $crawler->selectButton('Se connecter')->form();
        $this->browser->submit($form, [
            "_username" => "User",
            "_password" => "Abcd1234"
        ]);
    }

//    public function testListActionWithUnauthenticatedUser()
//    {
//        $crawler = $this->browser->request('GET', $this->router->generate('app_task_list'));
//        $this->assertSame("Vous devez être authentifié pour accéder à cette fonctionnalité.",
//            $crawler->filter('div.alert')->text()
//    );
//
//    }

    public function testListAction()
    {
        // authenticate User
        $this->loginUser();

        // get the task list
        $crawler = $this->browser->request('GET', $this->router->generate('app_task_list'));
        
        $this->assertSame(200, $this->browser->getResponse()->getStatusCode());
        $this->assertSame("Créer une tâche", $crawler->filter('a.btn-info')->text());
    }

    public function testClosedListActionOk()
    {
        // authenticate User
        $this->loginUser();

        // get the task list
        $crawler = $this->browser->request('GET', $this->router->generate('app_task_list_closed'));
        
        $this->assertSame(200, $this->browser->getResponse()->getStatusCode());
        $this->assertSame("Créer une tâche", $crawler->filter('a.btn-info')->text());
    }

}