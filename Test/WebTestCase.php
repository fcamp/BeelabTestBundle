<?php

namespace Beelab\Test;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader as Loader;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as SymfonyWebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * WebTestCase
 */
abstract class WebTestCase extends SymfonyWebTestCase
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $em;

    /**
     * @var Client
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $environment = 'test';
        if (getenv('TEST_TOKEN') !== false) {
            $environment = 'test' . getenv('TEST_TOKEN');
        }
        if (empty($this->container)) {
            $kernel = static::createKernel(['environment' => $environment]);
            $kernel->boot();
            $this->container = $kernel->getContainer();
            $this->em = $this->container->get('doctrine.orm.entity_manager');
        }
        if (empty(static::$admin)) {
            $this->client = static::createClient(['environment' => $environment]);
        } else {
            $this->client = static::createClient(['environment' => $environment], [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => $this->container->getParameter('admin_password'),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->em->getConnection()->close();
        parent::tearDown();
    }

    /**
     * Save request output and show it in the browser
     * See http://giorgiocefaro.com/blog/test-symfony-and-automatically-open-the-browser-with-the-response-content
     * You need a "domain" parameter, defining the current domain of your app
     *
     * @param string  $browser
     * @param boolean $delete
     */
    protected function saveOutput($browser = '/usr/bin/firefox', $delete = true)
    {
        $file = $this->container->get('kernel')->getRootDir() . '/../web/test.html';
        $url = $this->container->getParameter('domain') . '/test.html';
        if (false !== $profile = $this->client->getProfile()) {
            $url .= '?' . $profile->getToken();
        }
        file_put_contents($file, $this->client->getResponse()->getContent());
        $process = new Process($browser . ' ' . $url);
        $process->start();
        sleep(3);
        if ($delete) {
            unlink($file);
        }
    }

    /**
     * Login
     * See http://blog.bee-lab.net/login-automatico-con-fosuserbundle/
     * Be sure that $firewall match the entry in your security.yml configuration
     *
     * @param string $username
     * @param string $firewall
     */
    protected function login($username = 'admin1@example.org', $firewall = 'main')
    {
        $userManager = $this->container->get('beelab_user.manager');
        $user = $userManager->find($username);
        $token = new UsernamePasswordToken($user, null, $firewall, $user->getRoles());
        $session = $this->container->get('session');
        $session->set('_security_' . $firewall, serialize($token));
        $session->save();
        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    /**
     * Get an image file to be used in a form
     *
     * @param  int          $file
     * @return UploadedFile
     */
    protected function getImageFile($file = 0)
    {
        $data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12P4//8/AAX+Av7czFnnAAAAAElFTkSuQmCC';

        return $this->getFile($file, $data, 'png', 'image/png');
    }

    /**
     * Get a pdf file to be used in a form
     *
     * @param  int          $file
     * @return UploadedFile
     */
    protected function getPdfFile($file = 0)
    {
        $data = <<<EOF
JVBERi0xLjEKJcKlwrHDqwoKMSAwIG9iagogIDw8IC9UeXBlIC9DYXRhbG9nCiAgICAgL1BhZ2VzIDIgMCBSCiAgPj4KZW5kb2JqCgoyIDAgb2JqCiAgP
DwgL1R5cGUgL1BhZ2VzCiAgICAgL0tpZHMgWzMgMCBSXQogICAgIC9Db3VudCAxCiAgICAgL01lZGlhQm94IFswIDAgMzAwIDE0NF0KICA+PgplbmRvYm
oKCjMgMCBvYmoKICA8PCAgL1R5cGUgL1BhZ2UKICAgICAgL1BhcmVudCAyIDAgUgogICAgICAvUmVzb3VyY2VzCiAgICAgICA8PCAvRm9udAogICAgICA
gICAgIDw8IC9GMQogICAgICAgICAgICAgICA8PCAvVHlwZSAvRm9udAogICAgICAgICAgICAgICAgICAvU3VidHlwZSAvVHlwZTEKICAgICAgICAgICAg
ICAgICAgL0Jhc2VGb250IC9UaW1lcy1Sb21hbgogICAgICAgICAgICAgICA+PgogICAgICAgICAgID4+CiAgICAgICA+PgogICAgICAvQ29udGVudHMgN
CAwIFIKICA+PgplbmRvYmoKCjQgMCBvYmoKICA8PCAvTGVuZ3RoIDU1ID4+CnN0cmVhbQogIEJUCiAgICAvRjEgMTggVGYKICAgIDAgMCBUZAogICAgKE
hlbGxvIFdvcmxkKSBUagogIEVUCmVuZHN0cmVhbQplbmRvYmoKCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAxOCAwMDAwMCBuIAo
wMDAwMDAwMDc3IDAwMDAwIG4gCjAwMDAwMDAxNzggMDAwMDAgbiAKMDAwMDAwMDQ1NyAwMDAwMCBuIAp0cmFpbGVyCiAgPDwgIC9Sb290IDEgMCBSCiAg
ICAgIC9TaXplIDUKICA+PgpzdGFydHhyZWYKNTY1CiUlRU9GCg==
EOF;

        return $this->getFile($file, $data, 'pdf', 'application/pdf');
    }

    /**
     * Get a pdf file to be used in a form
     *
     * @param  int          $file
     * @return UploadedFile
     */
    protected function getZipFile($file = 0)
    {
        $data = <<<EOF
UEsDBAoAAgAAAM5RjEVOGigMAgAAAAIAAAAFABwAaC50eHRVVAkAA/OxilTzsYpUdXgLAAEE6AMAAARkAAAAaApQSwECHgMKAAIAAADOUYxF
ThooDAIAAAACAAAABQAYAAAAAAABAAAApIEAAAAAaC50eHRVVAUAA/OxilR1eAsAAQToAwAABGQAAABQSwUGAAAAAAEAAQBLAAAAQQAAAAAA
EOF;

        return $this->getFile($file, $data, 'zip', 'application/zip');
    }

    /**
     * Load fixtures as an array of "names"
     * This is inspired by https://github.com/liip/LiipFunctionalTestBundle
     *
     * @param array  $fixtures  e.g. ['UserData', 'OrderData']
     * @param string $namespace
     */
    protected function loadFixtures(array $fixtures, $namespace = 'AppBundle\\DataFixtures\\ORM\\')
    {
        $this->em->getConnection()->exec('SET foreign_key_checks = 0');
        $loader = new Loader($this->container);
        foreach ($fixtures as $fixture) {
            $this->loadFixtureClass($loader, $namespace . $fixture);
        }
        $executor = new ORMExecutor($this->em, new ORMPurger());
        $executor->execute($loader->getFixtures());
        $this->em->getConnection()->exec('SET foreign_key_checks = 1');
    }

    /**
     * Assert that $num mail has been sent
     * Need $this->client->enableProfiler() before calling
     *
     * @param integer $num
     * @param string  $message
     */
    protected function assertMailSent($num, $message = '')
    {
        if (false !== $profile = $this->client->getProfile()) {
            $collector = $profile->getCollector('swiftmailer');
            $this->assertEquals($num, $collector->getMessageCount(), $message);
        } else {
            $this->markTestSkipped('Profiler not enabled.');
        }
    }

    /**
     * Get a form field value, from its id
     * Useful for POSTs
     *
     * @param  Crawler $crawler
     * @param  string  $field
     * @return string
     */
    protected function getFormValue(Crawler $crawler, $fieldId)
    {
        return $crawler->filter('#' . $fieldId)->attr('value');
    }

    /**
     * Load a single fixture class
     * (with possible other dependent fixture classes)
     *
     * @param Loader $loader
     * @param string $className
     */
    private function loadFixtureClass(Loader $loader, $className)
    {
        $fixture = new $className();
        if ($loader->hasFixture($fixture)) {
            unset($fixture);

            return;
        }
        $loader->addFixture($fixture);
        if ($fixture instanceof DependentFixtureInterface) {
            foreach ($fixture->getDependencies() as $dependency) {
                $this->loadFixtureClass($loader, $dependency);
            }
        }
    }

    /**
     * Get a file to be used in a form
     *
     * @param  int          $file
     * @param  string       $data
     * @param  string       $ext
     * @param  string       $mime
     * @return UploadedFile
     */
    private function getFile($file, $data, $ext, $mime)
    {
        $name = 'file_' . $file . '.' . $ext;
        $path = tempnam(sys_get_temp_dir(), 'sf_test_') . $name;
        file_put_contents($path, base64_decode($data));

        return new UploadedFile($path, $name, $mime, 1234);
    }
}