<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 31.08.14
 * Time: 16:13
 */

namespace Cundd\PersistentObjectStore\DataAccess;

use Cundd\PersistentObjectStore\AbstractDataBasedCase;
use Cundd\PersistentObjectStore\Configuration\ConfigurationManager;
use Cundd\PersistentObjectStore\Domain\Model\Database;
use Cundd\PersistentObjectStore\Domain\Model\DatabaseInterface;
use Cundd\PersistentObjectStore\Domain\Model\Document;

/**
 * Test for Cundd\PersistentObjectStore\DataAccess\Coordinator
 *
 * @package Cundd\PersistentObjectStore\DataAccess
 */
class CoordinatorTest extends AbstractDataBasedCase
{
    /**
     * @var \Cundd\PersistentObjectStore\DataAccess\Coordinator
     */
    protected $fixture;

    /**
     * Number of persons in the people database
     *
     * @var int
     */
    protected $numberOfPersons = 4998;

    /**
     * Number of persons in the contacts database
     *
     * @var int
     */
    protected $numberOfContacts = 5;

    /**
     * @var \Cundd\PersistentObjectStore\DataAccess\Reader
     */
    protected $databaseReader;

    /**
     * @test
     */
    public function listDatabasesTest()
    {
        $allDatabases       = $this->fixture->listDatabases();
        $persistedDatabases = $this->fixture->listPersistedDatabases();
        $inMemoryDatabases  = $this->fixture->listInMemoryDatabases();
        $this->assertNotEmpty($allDatabases);
        $this->assertNotEmpty($persistedDatabases);
        $this->assertEmpty($inMemoryDatabases);
    }

    /**
     * @test
     */
    public function createDatabaseTest()
    {
        $databaseIdentifier = 'test-db-' . time();
        $expectedPath       = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('writeDataPath') . $databaseIdentifier . '.json';

        $this->fixture->createDatabase($databaseIdentifier);

        $this->assertFileExists($expectedPath);
        unlink($expectedPath);
    }

    /**
     * @test
     */
    public function dropDatabaseTest()
    {
        $databaseIdentifier = 'test-db-' . time();
        $expectedPath       = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('dataPath') . $databaseIdentifier . '.json';

        file_put_contents($expectedPath, '[]');
        $this->assertFileExists($expectedPath);

        $this->fixture->dropDatabase($databaseIdentifier);

        $this->assertFileNotExists($expectedPath);
    }

    /**
     * @test
     * @expectedException     \Cundd\PersistentObjectStore\Domain\Model\Exception\InvalidDatabaseException
     */
    public function dropNotExistingDatabaseTest()
    {
        $databaseIdentifier = 'test-db-' . time();
        $this->fixture->dropDatabase($databaseIdentifier);
    }

    /**
     * @test
     */
    public function readPersonsTests()
    {
        $this->checkPersonFile();

        /** @var Database $database */


//		$m = memory_get_peak_usage();
//		$database = $this->fixture->getDataByDatabase('people');
//		DebugUtility::pl('Ratio peak A/B: %0.4f peak/current %0.4f' . PHP_EOL, memory_get_peak_usage() / $m, memory_get_peak_usage() / memory_get_usage());

//		$memoryPeakUsage = memory_get_peak_usage();
//		$database = $this->fixture->getDatabase('contacts');
//		$path = __DIR__ . '/../../../Tests/Resources/contacts.json';
//		printf('Ratio peak A/B: %0.4f peak/current %0.4f' . PHP_EOL, memory_get_peak_usage() / $memoryPeakUsage, memory_get_peak_usage() / memory_get_usage());
//		printf('Ratio peak/size: %0.4f current/size: %0.4f' . PHP_EOL . PHP_EOL, (memory_get_peak_usage() - $memoryPeakUsage) / filesize($path), memory_get_usage() / filesize($path));

//		$memoryPeakUsage = memory_get_peak_usage();
//		$database = $this->fixture->getDatabase('people-small');
//		$path = __DIR__ . '/../../../Tests/Resources/people-small.json';
//		printf('Ratio peak A/B: %0.4f peak/current %0.4f' . PHP_EOL, memory_get_peak_usage() / $memoryPeakUsage, memory_get_peak_usage() / memory_get_usage());
//		printf('Ratio peak/size: %0.4f current/size: %0.4f' . PHP_EOL . PHP_EOL, (memory_get_peak_usage() - $memoryPeakUsage) / filesize($path), memory_get_usage() / filesize($path));

//		$memoryPeakUsage = memory_get_peak_usage();
        $database = $this->fixture->getDatabase('people');
//		$path = __DIR__ . '/../../../Tests/Resources/people.json';
//		printf('Ratio peak A/B: %0.4f peak/current %0.4f' . PHP_EOL, memory_get_peak_usage() / $memoryPeakUsage, memory_get_peak_usage() / memory_get_usage());
//		printf('Ratio peak/size: %0.4f current/size: %0.4f' . PHP_EOL . PHP_EOL, (memory_get_peak_usage() - $memoryPeakUsage) / filesize($path), memory_get_usage() / filesize($path));


        $this->assertEquals($this->numberOfPersons, $database->count());
        $this->assertEquals($this->numberOfPersons, count(iterator_to_array($database)));
    }

    /**
     * @test
     */
    public function readTests()
    {
        /** @var Database $database */
        $database = $this->fixture->getDatabase('contacts');
        $this->assertEquals(5, $database->count());
    }

    /**
     * @test
     */
    public function readMoreDatabasesTests()
    {
        /** @var Database $database */
        $database = $this->fixture->getDatabase('contacts');

        $loadCars   = function () {
            $this->fixture->getDatabase('cars');
        };
        $loadPeople = function () {
            $this->fixture->getDatabase('people');
        };

        if ($this->fixture->databaseExists('cars')) {
            $loadCars();
        }
        $loadPeople();

        $this->assertEquals(5, $database->count());
    }

    /**
     * @test
     */
    public function commitDatabaseTest()
    {
        /** @var Database $database */
        $database = $this->fixture->getDatabase('contacts');

        $dataInstance = new Document();
        $dataInstance->setData(array(
            'firstName' => 'Oliver',
            'lastName'  => 'Kane',
            'email'     => 'o@kane.net'
        ));
        $database->add($dataInstance);
        $this->fixture->commitDatabase($database);

        $expectedPath = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('writeDataPath') . 'contacts.json';
        $this->assertTrue(file_exists($expectedPath));
        unlink($expectedPath);
    }

    /**
     * @test
     */
    public function commitBigDatabaseTest()
    {
        $this->checkPersonFile();

        /** @var Database $database */
        $database = $this->fixture->getDatabase('people');

        $dataInstance = new Document();
        $dataInstance->setData(array(
            '_id'           => '541f004ef8f4d2df32ca60c2',
            'index'         => 5000,
            'isActive'      => false,
            'balance'       => '$2,925.56',
            'picture'       => 'http://placehold.it/32x32',
            'age'           => 31,
            'eyeColor'      => 'brown',
            'name'          => 'Daniel Corn',
            'gender'        => 'male',
            'company'       => 'FARMEX',
            'email'         => 'info@cundd.net',
            'phone'         => '+1 (973) 480-3194',
            'address'       => '125 Stone Avenue, Worton, Alabama, 6669',
            'about'         => 'Dolore in excepteur nisi dolor laboris ipsum proident cupidatat proident. Aliquip commodo culpa adipisicing ullamco ad. Ut ex duis tempor do id enim. Proident exercitation officia veniam magna mollit nostrud duis do qui reprehenderit. Ea culpa anim ullamco aliqua culpa nulla ex nisi irure qui incididunt reprehenderit. Labore do velit amet duis aute occaecat. Et sunt ex Lorem qui do deserunt ullamco labore.\r\n',
            'registered'    => '2014-06-29T15:29:47 -02:00',
            'latitude'      => 51.372838,
            'longitude'     => -71.88925,
            'tags'          => [
                'id',
                'consequat',
                'aute',
                'deserunt',
                'in',
                'enim',
                'veniam'
            ],
            'friends'       => [
                array(
                    'id'   => 0,
                    'name' => 'Bray Ruiz'
                ),
                array(
                    'id'   => 1,
                    'name' => 'Carr Kerr'
                ),
                array(
                    'id'   => 2,
                    'name' => 'Carter Dejesus'
                )
            ],
            'greeting'      => 'Hello, Conway Burch! You have 3 unread messages.',
            'favoriteFruit' => 'apple'
        ));

        $database->add($dataInstance);
        $this->assertEquals($this->numberOfPersons + 1, $database->count());

        $this->fixture->commitDatabase($database);

        $expectedPath = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('writeDataPath') . 'people.json';
        $this->assertTrue(file_exists($expectedPath));
        unlink($expectedPath);
    }

    /**
     * @test
     */
    public function addToDatabaseTest()
    {
        $this->checkPersonFile();

        /** @var Database $database */
        $database = $this->fixture->getDatabase('people');

        $dataInstance = new Document();
        $dataInstance->setData(array(
            '_id'           => '541f004ef8f4d2df32ca60c2',
            'index'         => 5000,
            'isActive'      => false,
            'balance'       => '$2,925.56',
            'picture'       => 'http://placehold.it/32x32',
            'age'           => 31,
            'eyeColor'      => 'brown',
            'name'          => 'Daniel Corn',
            'gender'        => 'male',
            'company'       => 'FARMEX',
            'email'         => 'support@cundd.net',
            'phone'         => '+1 (973) 480-3194',
            'address'       => '125 Stone Avenue, Worton, Alabama, 6669',
            'about'         => 'Dolore in excepteur nisi dolor laboris ipsum proident cupidatat proident. Aliquip commodo culpa adipisicing ullamco ad. Ut ex duis tempor do id enim. Proident exercitation officia veniam magna mollit nostrud duis do qui reprehenderit. Ea culpa anim ullamco aliqua culpa nulla ex nisi irure qui incididunt reprehenderit. Labore do velit amet duis aute occaecat. Et sunt ex Lorem qui do deserunt ullamco labore.\r\n',
            'registered'    => '2014-06-29T15:29:47 -02:00',
            'latitude'      => 52.372848,
            'longitude'     => -70.88935,
            'tags'          => [
                'id',
                'consequat',
                'aute',
                'deserunt',
                'in',
                'enim',
                'veniam'
            ],
            'friends'       => [
                array(
                    'id'   => 0,
                    'name' => 'Bray Ruiz'
                ),
                array(
                    'id'   => 1,
                    'name' => 'Carr Kerr'
                ),
                array(
                    'id'   => 2,
                    'name' => 'Carter Dejesus'
                )
            ],
            'greeting'      => 'Hello, Conway Burch! You have 3 unread messages.',
            'favoriteFruit' => 'apple'
        ));

        $database->add($dataInstance);
        $this->assertEquals($this->numberOfPersons + 1, $database->count());

        // A database just loaded from the filesystem should only contain the original number of entries
        /** @var DatabaseInterface $newlyLoadedDatabase */
        $newlyLoadedDatabase = $this->databaseReader->loadDatabase('people');
        $this->assertEquals($this->numberOfPersons, $newlyLoadedDatabase->count());
        $this->assertEquals($database->count() - 1, $newlyLoadedDatabase->count());

        // A database again retrieved from the coordinator should contain the added entry
        /** @var DatabaseInterface $databaseRetrievedFromTheCoordinator */
        $databaseRetrievedFromTheCoordinator = $this->fixture->getDatabase('people');
        $this->assertEquals($this->numberOfPersons + 1, $databaseRetrievedFromTheCoordinator->count());
        $this->assertEquals($database->count(), $databaseRetrievedFromTheCoordinator->count());


    }

    /**
     * @test
     */
    public function removeFromDatabaseTest()
    {
        $databaseIdentifier = 'contacts';
        $testEmail          = 'paul@mckenzy.net';

        /** @var Database $database */
        $database = $this->fixture->getDatabase($databaseIdentifier);

        $dataInstance = new Document();
        $dataInstance->setData(array(
            'firstName' => 'Paul',
            'lastName'  => 'McKenzy',
            'email'     => $testEmail,
        ));

        $database->remove($dataInstance);
        $this->assertEquals($this->numberOfContacts - 1, $database->count());


        // A database just loaded from the filesystem should only contain the original number of entries
        /** @var DatabaseInterface $newlyLoadedDatabase */
        $newlyLoadedDatabase = $this->databaseReader->loadDatabase($databaseIdentifier);
        $this->assertEquals($this->numberOfContacts, $newlyLoadedDatabase->count());
        $this->assertEquals($database->count() + 1, $newlyLoadedDatabase->count());

        // A database again retrieved from the coordinator should contain the added entry
        /** @var DatabaseInterface $databaseRetrievedFromTheCoordinator */
        $databaseRetrievedFromTheCoordinator = $this->fixture->getDatabase($databaseIdentifier);
        $this->assertEquals($this->numberOfContacts - 1, $databaseRetrievedFromTheCoordinator->count());
        $this->assertEquals($database->count(), $databaseRetrievedFromTheCoordinator->count());


        $expectedPath = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('writeDataPath') . $databaseIdentifier . '.json';
        $this->fixture->commitDatabase($database);
        $this->assertFileExists($expectedPath);

        $writtenData = json_decode(file_get_contents($expectedPath), true);
        $this->assertTrue($writtenData !== false);

        foreach ($writtenData as $data) {
            $this->assertNotEquals($testEmail, isset($data['email']) ? $data['email'] : 'no-email-at-all');
        }
//		$this->assertEquals($database->count(), count($writtenData));
//		$this->assertEquals($this->numberOfContacts - 1, count($writtenData));
        unlink($expectedPath);
    }

    protected function setUp()
    {
        parent::setUp();
        $this->databaseReader = $this->getDiContainer()->get('\Cundd\PersistentObjectStore\DataAccess\Reader');
    }

    protected function tearDown()
    {
        unset($this->fixture);
        parent::tearDown();
    }
}
