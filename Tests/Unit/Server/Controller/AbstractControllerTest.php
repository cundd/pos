<?php
declare(strict_types=1);

namespace Server\Controller;

use Cundd\Stairtower\AbstractCase;
use Cundd\Stairtower\Server\Controller\AbstractController;
use Cundd\Stairtower\Server\Controller\ControllerInterface;
use Cundd\Stairtower\Server\ValueObject\ControllerResult;
use Cundd\Stairtower\Server\ValueObject\RequestInfoFactory;
use React\Http\Request;
use React\Http\Response;
use React_ConnectionStub;

/**
 * Tests for the abstract Controller implementation
 */
class AbstractControllerTest extends AbstractCase
{
    /**
     * @var ControllerInterface
     */
    protected $fixture;

    /**
     * @var RequestInfoFactory
     */
    protected $requestInfoFactory;

    protected function setUp()
    {
        parent::setUp();

        $this->fixture = $this->getMockForAbstractClass(
            AbstractController::class
        );

        $this->requestInfoFactory = $this->getDiContainer()->get(
            RequestInfoFactory::class
        );
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('GET', '/_cundd-test-application/my_method')
        );
        $this->fixture->setRequest($requestInfo);
    }

    protected function tearDown()
    {
        $this->fixture->unsetRequest();
        unset($this->fixture);
        parent::tearDown();
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function initializeTest()
    {
        $this->fixture->initialize();
    }


    /**
     * @test
     */
    public function getRequestTest()
    {
        $this->assertInstanceOf(
            \Cundd\Stairtower\Server\ValueObject\Request::class,
            $this->fixture->getRequest()
        );
    }

    /**
     * @test
     */
    public function setRequestTest()
    {
        $request = new Request('GET', '/loaned/');
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest($request);
        $this->fixture->setRequest($requestInfo);
        $this->assertSame($requestInfo, $this->fixture->getRequest());
    }


    /**
     * @test
     */
    public function unsetRequestTest()
    {
        $request = new Request('GET', '/loaned/');
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest($request);
        $this->fixture->setRequest($requestInfo);

        $this->fixture->unsetRequest();
        $this->assertNull($this->fixture->getRequest());
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function willInvokeActionTest()
    {
        $this->fixture->willInvokeAction('test');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function didInvokeActionTest()
    {
        $result = new ControllerResult(200);
        $this->fixture->didInvokeAction('test', $result);
    }

    /**
     * @test
     */
    public function processRequestTest()
    {

        /** @var ControllerInterface $controller */
        $controller = $this->getMockBuilder(AbstractController::class)
            ->setMethods(['getHelloAction'])
            ->getMock();
        $controller
            ->expects($this->any())
            ->method('getHelloAction')
            ->will($this->returnValue(true));

        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('GET', '/_cundd-test-application/hello')
        );
        $response = new Response(new React_ConnectionStub());

        /** @var \Cundd\Stairtower\Server\ValueObject\ControllerResult $result */
        $result = $controller->processRequest($requestInfo, $response);
        $this->assertNotNull($result);
        $this->assertInstanceOf(ControllerResult::class, $result);
        $this->assertSame(true, $result->getData());
    }

    /**
     * @test
     */
    public function processRequestWithLongerActionNameTest()
    {
        /** @var ControllerInterface $controller */
        $controller = $this->getMockBuilder(AbstractController::class)
            ->setMethods(['getHelloWorldAction'])
            ->getMock();
        $controller
            ->expects($this->any())
            ->method('getHelloWorldAction')
            ->will($this->returnValue(true));

        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('GET', '/_cundd-test-application/hello_world')
        );
        $response = new Response(new React_ConnectionStub());

        /** @var \Cundd\Stairtower\Server\ValueObject\ControllerResult $result */
        $result = $controller->processRequest($requestInfo, $response);
        $this->assertNotNull($result);
        $this->assertInstanceOf(ControllerResult::class, $result);
        $this->assertSame(true, $result->getData());
    }

    /**
     * @test
     */
    public function processRequestWithMultipleArgumentsTest()
    {
        /** @var ControllerInterface $controller */
        $controller = $this->getMockBuilder(AbstractController::class)
            ->setMethods(['getHelloWorldAction'])
            ->getMock();
        $controller
            ->expects($this->any())
            ->method('getHelloWorldAction')
            ->will($this->returnValue(true));

        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('GET', '/_cundd-test-application/hello_world/another_argument')
        );
        $response = new Response(new React_ConnectionStub());

        /** @var \Cundd\Stairtower\Server\ValueObject\ControllerResult $result */
        $result = $controller->processRequest($requestInfo, $response);
        $this->assertNotNull($result);
        $this->assertInstanceOf(ControllerResult::class, $result);
        $this->assertSame(true, $result->getData());
    }

    /**
     * @test
     * @expectedException \Cundd\Stairtower\Server\Exception\RequestMethodNotImplementedException
     */
    public function processRequestNotImplementedMethodTest()
    {
        $requestInfo = $this->requestInfoFactory->buildRequestFromRawRequest(
            new Request('GET', '/_cundd-test-application/my_method')
        );
        $response = new Response(new React_ConnectionStub());
        $this->fixture->processRequest($requestInfo, $response);
    }
}
