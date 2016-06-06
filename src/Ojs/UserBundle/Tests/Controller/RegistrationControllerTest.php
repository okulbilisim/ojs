<?php

namespace Ojs\UserBundle\Tests\Controller;

use Ojs\CoreBundle\Tests\BaseTestSetup as BaseTestCase;

class RegistrationControllerTest extends BaseTestCase
{
    public function testRegister()
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/register');
        $this->assertStatusCode(301, $client);
    }
}