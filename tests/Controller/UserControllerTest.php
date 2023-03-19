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


class UserControllerTest extends WebTestCase
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

    public function testUsersListAction()
    {
        // authenticate User
        $this->client->loginUser($this->admin);
        // send request
        $this->client->request(Request::METHOD_GET, "/users");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testCreateUserAction()
    {
        // authenticate User
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/users/create');
        $form = $crawler->selectButton('btn-submit')->form();

        $form['create_user[username]'] = "New user " . $this->uniqid;
        $form['create_user[email]'] = "test" . $this->uniqid . "@todotest.com";
        $form['create_user[roles]'] = "ROLE_USER";
        $form['create_user[password][first]'] = "Abcd1234!";
        $form['create_user[password][second]'] = "Abcd1234!";

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success','Le nouvel utilisateur a été bien été ajouté.');
    }

    public function testEditUserAction()
    {
        // authenticate User
        $this->client->loginUser($this->admin);

        $users = $this->entityManager->getRepository(User::class)->findAll();
        /** @var User $userToUpdate */
        $userToUpdate = $users[count($users) - 1];
        unset($users);

        $crawler = $this->client->request(Request::METHOD_GET, '/users/' . $userToUpdate->getId() . '/edit');
        $form = $crawler->selectButton('btn-submit')->form();

        $form['edit_user[username]'] = "Edit user " . $userToUpdate->getUserIdentifier();
        $form['edit_user[email]'] = "edit-" . $userToUpdate->getEmail();
        $form['edit_user[roles]'] = "ROLE_USER";

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success',"L'utilisateur a bien été modifié");
    }

    public function testDeleteUserAction()
    {
        // authenticate User
        $this->client->loginUser($this->admin);

        $users = $this->entityManager->getRepository(User::class)->findAll();
        /** @var User $userToDelete */
        $userToDelete = $users[count($users) - 1];
        unset($users);

        $crawler = $this->client->request(Request::METHOD_GET, '/users/' . $userToDelete->getId() . '/delete');

        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success',"L'utilisateur a bien été supprimé.");
    }

    public function testChangePasswordAction()
    {
        // authenticate User
        $this->client->loginUser($this->user);

        $crawler = $this->client->request(Request::METHOD_GET, '/users/' . $this->user->getId() . '/changePassword');
        $form = $crawler->selectButton('btn-submit')->form();

        $form['user_password[password][first]'] = "Abcd1234!";
        $form['user_password[password][second]'] = "Abcd1234!";

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-success',"Le mot de passe a été changé.");
    }

    public function testChangePasswordActionWrongUser()
    {
        // authenticate User
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request(Request::METHOD_GET, '/users/' . $this->user->getId() . '/changePassword');
        $this->client->followRedirect();

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('div.alert.alert-danger',"Vous ne pouvez pas modifier le mot de passe d'un autre utilisateur.");
    }

}