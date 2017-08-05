<?php
declare(strict_types=1);

namespace Cundd\PersistentObjectStore\Server;

use Cundd\PersistentObjectStore\AbstractDatabaseBasedCase;
use Cundd\PersistentObjectStore\Constants;
use Cundd\PersistentObjectStore\Domain\Model\Document;

/**
 * Tests for creating URIs
 */
class UriBuilderTest extends AbstractDatabaseBasedCase
{
    /**
     * @var UriBuilderInterface
     */
    protected $fixture;

    protected function makeInstance($className)
    {
        if (!ctype_alnum(str_replace(['\\', '_'], '', $className))) {
            die;
        }

        if (!class_exists($className)) {

            if (strpos($className, '\\') !== false) {
                $classParts = explode('\\', $className);
                $class = array_pop($classParts);
                $namespace = implode('\\', $classParts);
                eval("namespace $namespace; class $class extends \\Test_Application_Controller {}");
            } else {
                eval("class $className extends \\Test_Application_Controller {}");
            }
        }

        return new $className;
    }

    /**
     * @test
     */
    public function buildUriTests()
    {
        $document = new Document();
        $document->setValueForKey('0b5e3637477c', Constants::DATA_ID_KEY);
        $database = $this->getSmallPeopleDatabase();

        $this->assertEquals(
            '/_cundd-sa-hello/blur',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Sa\\Controller\\HelloController'
            )
        );
        $this->assertEquals(
            '/_cundd-sa-hello/blur/database',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Sa\\Controller\\HelloController',
                'database'
            )
        );
        $this->assertEquals(
            '/_cundd-sa-hello/blur/people-small',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Sa\\Controller\\HelloController',
                $database
            )
        );
        $this->assertEquals(
            '/_cundd-sa-hello/blur/database/document-id',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Sa\\Controller\\HelloController',
                'database',
                'document-id'
            )
        );
        $this->assertEquals(
            '/_cundd-sa-hello/blur/database/0b5e3637477c',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Sa\\Controller\\HelloController',
                'database',
                $document
            )
        );
        $this->assertEquals(
            '/_cundd-sa-hello/blur/people-small/0b5e3637477c',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Sa\\Controller\\HelloController',
                $database,
                $document
            )
        );
        $this->assertEquals(
            '/_cundd-sa-hello/blur',
            $this->fixture->buildUriFor(
                'blur',
                $this->makeInstance('Cundd\\Sa\\Controller\\HelloController')
            )
        );


        $this->assertEquals(
            '/_cundd-stair-application/blur',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Stair\\Controller\\ApplicationController'
            )
        );
        $this->assertEquals(
            '/_cundd-stair-application/blur/database',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Stair\\Controller\\ApplicationController',
                'database'
            )
        );
        $this->assertEquals(
            '/_cundd-stair-application/blur/people-small',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Stair\\Controller\\ApplicationController',
                $database
            )
        );
        $this->assertEquals(
            '/_cundd-stair-application/blur/database/document-id',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Stair\\Controller\\ApplicationController',
                'database',
                'document-id'
            )
        );
        $this->assertEquals(
            '/_cundd-stair-application/blur/database/0b5e3637477c',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Stair\\Controller\\ApplicationController',
                'database',
                $document
            )
        );
        $this->assertEquals(
            '/_cundd-stair-application/blur/people-small/0b5e3637477c',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\Stair\\Controller\\ApplicationController',
                $database,
                $document
            )
        );
        $this->assertEquals(
            '/_cundd-stair-application/blur',
            $this->fixture->buildUriFor(
                'blur',
                $this->makeInstance('Cundd\\Stair\\Controller\\ApplicationController')
            )
        );


        $this->assertEquals(
            '/_cundd-stair_way-application/blur',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController'
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur/database',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController',
                'database'
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur/people-small',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController',
                $database
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur/database/document-id',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController',
                'database',
                'document-id'
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur/database/0b5e3637477c',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController',
                'database',
                $document
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur/people-small/0b5e3637477c',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController',
                $database,
                $document
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur',
            $this->fixture->buildUriFor(
                'blur',
                $this->makeInstance('Cundd\\StairWay\\Controller\\ApplicationController')
            )
        );
        $this->assertEquals(
            '/_cundd-stair_way-application/blur/people-small/0b5e3637477c?a=1&b=2',
            $this->fixture->buildUriFor(
                'blur',
                'Cundd\\StairWay\\Controller\\ApplicationController',
                $database,
                $document,
                ['a' => 1, 'b' => 2]
            )
        );
    }

    /**
     * @test
     */
    public function getControllerNamespaceForControllerTests()
    {
        $this->assertEquals(
            '_cundd-sa-hello',
            $this->fixture->getControllerNamespaceForController(
                'Cundd\\Sa\\Controller\\HelloController'
            )
        );
        $this->assertEquals(
            '_cundd-sa-hello',
            $this->fixture->getControllerNamespaceForController(
                $this->makeInstance('Cundd\\Sa\\Controller\\HelloController')
            )
        );

        $this->assertEquals(
            '_cundd-stair-application',
            $this->fixture->getControllerNamespaceForController(
                'Cundd\\Stair\\Controller\\ApplicationController'
            )
        );
        $this->assertEquals(
            '_cundd-stair-application',
            $this->fixture->getControllerNamespaceForController(
                $this->makeInstance('Cundd\\Stair\\Controller\\ApplicationController')
            )
        );

        $this->assertEquals(
            '_cundd-stair_way-application',
            $this->fixture->getControllerNamespaceForController(
                'Cundd\\StairWay\\Controller\\ApplicationController'
            )
        );
        $this->assertEquals(
            '_cundd-stair_way-application',
            $this->fixture->getControllerNamespaceForController(
                $this->makeInstance('Cundd\\StairWay\\Controller\\ApplicationController')
            )
        );
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidUriBuilderArgumentException
     * @expectedExceptionCode 1422475362
     */
    public function noActionNameTest()
    {
        $this->fixture->buildUriFor('', 'HelloController', 'database', 'document-id');
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidUriBuilderArgumentException
     * @expectedExceptionCode 1422475419
     */
    public function noControllerTest()
    {
        $this->fixture->buildUriFor('blur', '', 'database', 'document-id');
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidUriBuilderArgumentException
     * @expectedExceptionCode 1422472522
     */
    public function invalidActionNameTest()
    {
        $this->fixture->buildUriFor(new \stdClass(), 'HelloController', 'database', 'document-id');
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidUriBuilderArgumentException
     * @expectedExceptionCode 1422472579
     */
    public function invalidDatabaseTest()
    {
        $this->fixture->buildUriFor('blur', 'HelloController', new \stdClass(), 'document-id');
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidUriBuilderArgumentException
     * @expectedExceptionCode 1422472633
     */
    public function invalidDocumentTest()
    {
        $this->fixture->buildUriFor('blur', 'HelloController', 'database', new \stdClass());
    }

    /**
     * @test
     * @expectedException \Cundd\PersistentObjectStore\Server\Exception\InvalidUriBuilderArgumentException
     * @expectedExceptionCode 1422472650
     */
    public function noControllerForGetControllerNamespaceForControllerTests()
    {
        $this->fixture->getControllerNamespaceForController('');
    }

}
 