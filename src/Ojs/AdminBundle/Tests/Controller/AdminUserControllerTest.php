<?php

namespace Ojs\AdminBundle\Tests\Controller;

use Ojs\CoreBundle\Tests\BaseTestSetup as BaseTestCase;

class AdminUserControllerTest extends BaseTestCase
{
    public function testIndex()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/admin/user/');

        $this->assertStatusCode(200, $client);
    }

    public function testNew()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/admin/user/new');

        $this->assertStatusCode(200, $client);
    }

    public function testEdit()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/admin/user/1/edit');

        $this->assertStatusCode(200, $client);
    }

    public function testShow()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/admin/user/1/show');

        $this->assertStatusCode(200, $client);
    }

    public function testChangePassword()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/admin/user/1/password');

        $this->assertStatusCode(200, $client);
    }
}