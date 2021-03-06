<?php
declare(strict_types=1);

namespace Cundd\Stairtower\Tests\Unit\Expand;


use Cundd\Stairtower\Constants;
use Cundd\Stairtower\Expand\ExpandConfigurationBuilderInterface;
use Cundd\Stairtower\Expand\ExpandConfigurationInterface;
use Cundd\Stairtower\Tests\Unit\AbstractCase;

/**
 * ExpandConfigurationBuilder test
 */
class ExpandConfigurationBuilderTest extends AbstractCase
{
    /**
     * @var ExpandConfigurationBuilderInterface
     */
    protected $fixture;

    /**
     * @test
     */
    public function buildConfigurationWithExpandTest()
    {
        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%scontacts%semail',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(ExpandConfigurationInterface::class, $expandConfigurations[0]);
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[0]->getAsKey());
    }

    /**
     * @test
     */
    public function buildConfigurationWithExpandIdTest()
    {
        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%scontacts%s%s',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::DATA_ID_KEY,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals(Constants::DATA_ID_KEY, $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[0]->getAsKey());
    }

    /**
     * @test
     */
    public function buildConfigurationWithMoreThanOneExpandsTest()
    {
        // Query '$expand=person/contacts/email/-/book/book/isbn_10'
        $queryString = vsprintf(
            '%s=person%scontacts%semail%sbook%sbook%sisbn_10',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_DELIMITER,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(2, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[0]->getAsKey());

        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[1]
        );
        $this->assertEquals('book', $expandConfigurations[1]->getLocalKey());
        $this->assertEquals('book', $expandConfigurations[1]->getDatabaseIdentifier());
        $this->assertEquals('isbn_10', $expandConfigurations[1]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[1]->getAsKey());
    }

    /**
     * @test
     */
    public function buildConfigurationWithExpandAndAsPropertyTest()
    {
        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%scontacts%semail%sperson-data',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('person-data', $expandConfigurations[0]->getAsKey());
    }

    /**
     * @test
     */
    public function buildConfigurationWithExpandIdAndAsPropertyTest()
    {
        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%scontacts%s%s%sperson-data',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::DATA_ID_KEY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals(Constants::DATA_ID_KEY, $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('person-data', $expandConfigurations[0]->getAsKey());
    }

    /**
     * @test
     */
    public function buildConfigurationWithMoreThanOneExpandsAndAsPropertyTest()
    {
        // Query '$expand=person/contacts/email/-/book/book/isbn_10'
        $queryString = vsprintf(
            '%s=person%scontacts%semail%sperson-data%sbook%sbook%sisbn_10',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_DELIMITER,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(2, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('person-data', $expandConfigurations[0]->getAsKey());

        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[1]
        );
        $this->assertEquals('book', $expandConfigurations[1]->getLocalKey());
        $this->assertEquals('book', $expandConfigurations[1]->getDatabaseIdentifier());
        $this->assertEquals('isbn_10', $expandConfigurations[1]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[1]->getAsKey());
    }


    /**
     * @test
     */
    public function buildConfigurationWithExpandToManyTest()
    {
        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%s%scontacts%semail',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_TO_MANY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[0]->getAsKey());
        $this->assertTrue($expandConfigurations[0]->getExpandToMany());

        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%s%scontacts%s%s',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_TO_MANY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::DATA_ID_KEY,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals(Constants::DATA_ID_KEY, $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[0]->getAsKey());
        $this->assertTrue($expandConfigurations[0]->getExpandToMany());


        // Query '$expand=person/contacts/email/-/book/book/isbn_10'
        $queryString = vsprintf(
            '%s=person%s%scontacts%semail%sbook%sbook%sisbn_10',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_TO_MANY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_DELIMITER,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(2, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[0]->getAsKey());

        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[1]
        );
        $this->assertEquals('book', $expandConfigurations[1]->getLocalKey());
        $this->assertEquals('book', $expandConfigurations[1]->getDatabaseIdentifier());
        $this->assertEquals('isbn_10', $expandConfigurations[1]->getForeignKey());
        $this->assertEquals('', $expandConfigurations[1]->getAsKey());
        $this->assertTrue($expandConfigurations[0]->getExpandToMany());


        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%s%scontacts%semail%sperson-data',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_TO_MANY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals('email', $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('person-data', $expandConfigurations[0]->getAsKey());
        $this->assertTrue($expandConfigurations[0]->getExpandToMany());


        // Query '$expand=person/contacts/email'
        $queryString = vsprintf(
            '%s=person%s%scontacts%s%s%sperson-data',
            [
                Constants::EXPAND_KEYWORD,
                Constants::EXPAND_REQUEST_TO_MANY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
                Constants::DATA_ID_KEY,
                Constants::EXPAND_REQUEST_SPLIT_CHAR,
            ]
        );
        parse_str($queryString, $query);
        $expandConfigurations = $this->fixture->buildExpandConfigurations($query[Constants::EXPAND_KEYWORD]);
        $this->assertEquals(1, count($expandConfigurations));
        $this->assertInstanceOf(
            ExpandConfigurationInterface::class,
            $expandConfigurations[0]
        );
        $this->assertEquals('person', $expandConfigurations[0]->getLocalKey());
        $this->assertEquals('contacts', $expandConfigurations[0]->getDatabaseIdentifier());
        $this->assertEquals(Constants::DATA_ID_KEY, $expandConfigurations[0]->getForeignKey());
        $this->assertEquals('person-data', $expandConfigurations[0]->getAsKey());
    }
}
