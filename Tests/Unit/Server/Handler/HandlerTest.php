<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 11.10.14
 * Time: 20:18
 */

namespace Cundd\PersistentObjectStore\Server\Handler;


use Cundd\PersistentObjectStore\AbstractCase;
use Cundd\PersistentObjectStore\Configuration\ConfigurationManager;
use Cundd\PersistentObjectStore\Constants;
use Cundd\PersistentObjectStore\Domain\Model\DatabaseInterface;
use Cundd\PersistentObjectStore\Domain\Model\Document;
use Cundd\PersistentObjectStore\Domain\Model\DocumentInterface;
use Cundd\PersistentObjectStore\Memory\Manager;
use Cundd\PersistentObjectStore\Server\ValueObject\RequestInfoFactory;
use React\Http\Request;

/**
 * Handler test
 *
 * @package Cundd\PersistentObjectStore\Server\Handler
 */
class HandlerTest extends AbstractCase
{
    /**
     * @var HandlerInterface
     */
    protected $fixture;

    /**
     * @var DatabaseInterface
     */
    protected $database;

    /**
     * @var RequestInfoFactory
     */
    protected $requestInfoFactory;

    /**
     * @var string
     */
    protected $publicResourcesPath = '';

    /**
     * @test
     */
    public function noRouteTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/'));
        $handlerResult = $this->fixture->noRoute($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(200, $handlerResult->getStatusCode());
        $this->assertEquals(Constants::MESSAGE_JSON_WELCOME, $handlerResult->getData());
    }

    /**
     * @test
     */
    public function createTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('POST', '/contacts/'));
        $data          = array('email' => 'info-for-me@cundd.net', 'name' => 'Daniel');
        $handlerResult = $this->fixture->create($requestInfo, $data);

        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(201, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Domain\\Model\\DocumentInterface',
            $handlerResult->getData()
        );

        /** @var DocumentInterface $dataInstance */
        $dataInstance = $handlerResult->getData();
        $this->assertEquals('info-for-me@cundd.net', $dataInstance->valueForKey('email'));

        $this->assertTrue($this->database->contains($dataInstance));

        $i = 0;
        do {
            $data          = array('email' => "info$i-for-me@cundd.net", 'name' => 'Daniel');
            $handlerResult = $this->fixture->create($requestInfo, $data);
        } while (++$i < 10000);
        // Validate the last result
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(201, $handlerResult->getStatusCode());
        $dataInstance = $handlerResult->getData();
        $this->assertEquals(sprintf('info%d-for-me@cundd.net', $i - 1), $dataInstance->valueForKey('email'));


        $iHalf = intval($i / 2);
        $this->assertTrue($this->database->contains("info$iHalf-for-me@cundd.net"));
    }

    /**
     * @test
     */
    public function createDatabaseTest()
    {
        $databaseIdentifier = 'test-db-' . time();
        $expectedPath       = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('writeDataPath') . $databaseIdentifier . '.json';

        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('PUT', sprintf('/%s/', $databaseIdentifier))
        );
        $databaseOptions = array();
        // Todo: Enable Database creation parameters
        // $databaseOptions = array('type' => 'memory');
        $handlerResult   = $this->fixture->create($requestInfo, $databaseOptions);

        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(201, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());

        $this->assertFileExists($expectedPath);
        unlink($expectedPath);
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidRequestParameterException
     */
    public function createWithDataIdentifierShouldFailTest()
    {
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('POST', '/contacts/info@cundd.net')
        );
        $data          = array('email' => 'info-for-me@cundd.net', 'name' => 'Daniel');
        $handlerResult = $this->fixture->create($requestInfo, $data);

        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(201, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Domain\\Model\\DocumentInterface',
            $handlerResult->getData()
        );

        /** @var DocumentInterface $dataInstance */
        $dataInstance = $handlerResult->getData();
        $this->assertEquals('info-for-me@cundd.net', $dataInstance->valueForKey('email'));

    }

    /**
     * @test
     */
    public function readTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET',
            '/contacts/info@cundd.net'));
        $handlerResult = $this->fixture->read($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(200, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Domain\\Model\\DocumentInterface',
            $handlerResult->getData()
        );

        /** @var DocumentInterface $dataInstance */
        $dataInstance = $handlerResult->getData();
        $this->assertEquals('info@cundd.net', $dataInstance->valueForKey('email'));
    }

    /**
     * @test
     */
    public function readDatabaseTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/contacts/'));
        $handlerResult = $this->fixture->read($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(200, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Domain\\Model\\DatabaseInterface',
            $handlerResult->getData()
        );

        /** @var DocumentInterface $dataInstance */
        $dataInstance = $handlerResult->getData()->current();
        $this->assertEquals('info@cundd.net', $dataInstance->valueForKey('email'));
    }

    /**
     * @test
     */
    public function readWithSearchTest()
    {
        parse_str('firstName=Daniel', $query);
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/contacts/', $query));
        $handlerResult = $this->fixture->read($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(200, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Filter\\FilterResultInterface',
            $handlerResult->getData()
        );
        $this->assertEquals(1, $handlerResult->getData()->count());

        /** @var DocumentInterface $dataInstance */
        $dataInstance = $handlerResult->getData()->current();
        $this->assertEquals('info@cundd.net', $dataInstance->valueForKey('email'));
    }

    /**
     * @test
     */
    public function readWithEmptyResultSearchTest()
    {
        parse_str('firstName=Some-thing-not-existing', $query);
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/contacts/', $query));
        $handlerResult = $this->fixture->read($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(404, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Filter\\FilterResultInterface',
            $handlerResult->getData()
        );
        $this->assertEquals(0, $handlerResult->getData()->count());


        parse_str('some-thing-not-existing=Daniel', $query);
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/contacts/', $query));
        $handlerResult = $this->fixture->read($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(404, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Filter\\FilterResultInterface',
            $handlerResult->getData()
        );
        $this->assertEquals(0, $handlerResult->getData()->count());
    }

    /**
     * @test
     */
    public function updateTest()
    {
        $newName     = 'Steve ' . time();
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('PUT', '/contacts/info@cundd.net'));
        $data        = array('email' => 'info@cundd.net', 'name' => $newName);

        $handlerResult = $this->fixture->update($requestInfo, $data);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(200, $handlerResult->getStatusCode());
        $this->assertNotNull($handlerResult->getData());
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Domain\\Model\\DocumentInterface',
            $handlerResult->getData()
        );

        /** @var DocumentInterface $dataInstance */
        $dataInstance = $handlerResult->getData();
        $this->assertEquals('info@cundd.net', $dataInstance->valueForKey('email'));
        $this->assertEquals($newName, $dataInstance->valueForKey('name'));


        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('PUT', '/contacts/email@does-not-exist.net')
        );
        $data        = array(
            'what ever' => 'this will not be updated',
            'email'     => 'info@cundd.net',
            'name'      => 'Daniel'
        );

        $handlerResult = $this->fixture->update($requestInfo, $data);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(404, $handlerResult->getStatusCode());

    }

    /**
     * @test
     */
    public function deleteTest()
    {
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('DELETE', '/contacts/info@cundd.net')
        );
        $handlerResult = $this->fixture->delete($requestInfo);

        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertEquals(204, $handlerResult->getStatusCode());
        $this->assertEquals('Document "info@cundd.net" deleted', $handlerResult->getData());

        /** @var DocumentInterface $dataInstance */
        $dataInstance = new Document(['email' => 'info@cundd.net']);

        $this->assertFalse($this->database->contains($dataInstance));
    }

    /**
     * @test
     */
    public function deleteDatabaseTest()
    {
        // Running this test would remove our test data :(
        /*
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('DELETE', '/contacts/'));
        $handlerResult = $this->fixture->delete($requestInfo);

        $this->assertInstanceOf('Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult);
        $this->assertEquals(204, $handlerResult->getStatusCode());
        $this->assertEquals('Database "contacts" deleted', $handlerResult->getData());
        */
    }

    /**
     * @test
     */
    public function getStatsActionTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/_stats/'));
        $handlerResult = $this->fixture->getStatsAction($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
    }

    /**
     * @test
     */
    public function getAssetActionTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/_asset/book.json'));
        $handlerResult = $this->fixture->getAssetAction($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertSame(200, $handlerResult->getStatusCode());
        $this->assertContains('Beltz', $handlerResult->getData());

    }

    /**
     * @test
     */
    public function doNotGetAssetActionTest()
    {
        $requestInfo   = $this->requestInfoFactory->buildRequestFromRawRequest(new Request('GET', '/_asset/not-existing.jpg'));
        $handlerResult = $this->fixture->getAssetAction($requestInfo);
        $this->assertInstanceOf(
            'Cundd\\PersistentObjectStore\\Server\\Handler\\HandlerResultInterface',
            $handlerResult
        );
        $this->assertSame(404, $handlerResult->getStatusCode());
    }

    protected function setUp()
    {
        Manager::freeAll();

        $configurationManager = ConfigurationManager::getSharedInstance();
        $this->publicResourcesPath = $configurationManager->getConfigurationForKeyPath('publicResources');
        $configurationManager->setConfigurationForKeyPath('publicResources', __DIR__ . '/../../../Resources/');

        $diContainer = $this->getDiContainer();
        $server      = $diContainer->get('Cundd\\PersistentObjectStore\\Server\\DummyServer');
        $diContainer->set('Cundd\\PersistentObjectStore\\Server\\ServerInterface', $server);
        
        $coordinator = $diContainer->get('Cundd\\PersistentObjectStore\\DataAccess\\CoordinatorInterface');

        $this->requestInfoFactory = $diContainer->get('Cundd\\PersistentObjectStore\\Server\\ValueObject\\RequestInfoFactory');


        parent::setUp();
        $this->database = $coordinator->getDatabase('contacts');
    }

    protected function tearDown()
    {
        Manager::freeAll();
        ConfigurationManager::getSharedInstance()->setConfigurationForKeyPath(
            'publicResources',
            $this->publicResourcesPath
        );
    }
}
