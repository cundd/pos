<?php
declare(strict_types=1);

namespace Cundd\Stairtower\Domain\Model;

use Cundd\Stairtower\Constants;
use Cundd\Stairtower\Core\ArrayException\IndexOutOfRangeException;
use Cundd\Stairtower\Core\ArrayException\InvalidIndexException;
use Cundd\Stairtower\DataAccess\Event;
use Cundd\Stairtower\Domain\Model\Exception\DatabaseMismatchException;
use Cundd\Stairtower\Domain\Model\Exception\InvalidDataException;
use Cundd\Stairtower\Event\SharedEventEmitter;
use Cundd\Stairtower\Filter\Comparison\ComparisonInterface;
use Cundd\Stairtower\Filter\Exception\InvalidCollectionException;
use Cundd\Stairtower\Filter\Filter;
use Cundd\Stairtower\Filter\FilterResultInterface;
use Cundd\Stairtower\Index\IdentifierIndex;
use Cundd\Stairtower\Index\IndexableTrait;
use Cundd\Stairtower\Index\IndexInterface;
use Cundd\Stairtower\RuntimeException;
use Cundd\Stairtower\Utility\DebugUtility;
use Cundd\Stairtower\Utility\DocumentUtility;
use Cundd\Stairtower\Utility\GeneralUtility;
use SplFixedArray;

/**
 * Database class which holds the Document instances
 *
 * Implementation with object creation on demand.
 */
class Database implements DatabaseInterface, DatabaseRawDataInterface, DatabaseObjectDataInterface
{
    use IndexableTrait, DatabaseStateTrait;

    /**
     * Raw data array
     *
     * @var \SplFixedArray
     */
    protected $rawData;

    /**
     * Converted objects
     *
     * @var \SplFixedArray
     */
    protected $objectData;

//	/**
//	 * Map of object identifiers to the index
//	 *
//	 * @var array
//	 */
//	protected $idToIndexMap = array();

    /**
     * Database identifier
     *
     * @var string
     */
    protected $identifier = '';

    /**
     * Current index
     *
     * @var int
     */
    protected $index = 0;

    /**
     * Creates a new database
     *
     * @param string $identifier
     * @param array  $rawData
     */
    public function __construct(string $identifier, $rawData = [])
    {
        GeneralUtility::assertDatabaseIdentifier($identifier);
        $this->identifier = $identifier;

        $this->indexes[] = new IdentifierIndex();

        if ($rawData) {
            $this->setRawData($rawData);
        } else {
            $this->rawData = new SplFixedArray(0);
            $this->objectData = new SplFixedArray(0);
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    // MANAGING OBJECTS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    public function findByIdentifier(string $identifier): ?DocumentInterface
    {
        // Query the Indexes and return the result if it is not an error
        $indexLookupResult = $this->queryIndexesForValueOfProperty($identifier, Constants::DATA_ID_KEY);
        if ($indexLookupResult > IndexInterface::NO_RESULT) {
            if ($indexLookupResult === IndexInterface::NOT_FOUND) {
                return null;
            }

            return $indexLookupResult[0];
        }

        $i = 0;
        $count = $this->count();
        if ($count === 0) {
            return null;
        }

        do {
            if (isset($this->rawData[$i]) && $this->rawData[$i] && $this->rawData[$i][Constants::DATA_ID_KEY]) {
                if (isset($this->objectData[$i])) {
                    $foundObject = $this->objectData[$i];
                } else {
                    $foundObject = $this->convertDataAtIndexToObject($i);
                    if ($foundObject !== null) {
                        $this->setObjectDataForIndex($foundObject, $i);
                    }
                }

                if ($foundObject instanceof DocumentInterface && $foundObject->getId() === $identifier) {
                    return $foundObject;
                }
            }
        } while (++$i < $count);

        return null;
    }

    public function contains($document): bool
    {
        if (is_string($document)) {
            $identifier = $document;
        } elseif ($document instanceof DocumentInterface) {
            $this->assertDataInstancesDatabaseIdentifier($document);
            DocumentUtility::assertDocumentIdentifier($document);
            $identifier = $document->getId();
        } else {
            throw new RuntimeException("Given value $document is of type " . gettype($document));
        }

        return $this->findByIdentifier($identifier) ? true : false;
    }

    public function filter(ComparisonInterface $comparison): FilterResultInterface
    {
        return (new Filter($comparison))->filterCollection($this);
    }

    public function add(DocumentInterface $document): DatabaseInterface
    {
        $this->assertDataInstancesDatabaseIdentifier($document);
        DocumentUtility::assertDocumentIdentifier($document);
        $currentCount = $this->count();

        if ($this->contains($document)) {
            throw new InvalidDataException(
                sprintf(
                    'Object with GUID %s already exists in the database. Maybe the values of the identifier is not expressive',
                    $document->getGuid()
                ),
                1411205350
            );
        }

        $this->objectData->setSize($currentCount + 1);
        $this->setObjectDataForIndex($document, $currentCount);

        $this->rawData->setSize($currentCount + 1);
        $this->setRawDataForIndex($document->getData(), $currentCount);

        $this->addToIndexesAtPosition($document, $currentCount);

        $this->state = self::STATE_DIRTY;
        SharedEventEmitter::emit(Event::DATABASE_DOCUMENT_ADDED, [$document]);

        return $this;
    }

    /**
     * Updates the given Document in the database
     *
     * @param DocumentInterface $document
     * @return DatabaseInterface
     */
    public function update(DocumentInterface $document): DatabaseInterface
    {
        $this->assertDataInstancesDatabaseIdentifier($document);
        DocumentUtility::assertDocumentIdentifier($document);

        if (!$this->contains($document)) {
            throw new InvalidDataException(
                sprintf(
                    'Object with GUID %s does not exist in the database. Maybe the values of the identifier is not expressive',
                    $document->getGuid()
                ),
                1412800596
            );
        }

        $index = $this->getIndexForIdentifier($document->getId());
        $oldDataInstance = $this->getObjectDataForIndexIfSet($index);
        if (!$oldDataInstance) {
            throw new InvalidDataException('No data instance found to replace', 1413711010);
        }
        if ($document->getId() !== $oldDataInstance->getId()) {
            throw new InvalidDataException(
                sprintf(
                    'Given identifier "%s" does not match the found instance\'s identifier "%s"',
                    $document->getId(),
                    $oldDataInstance->getId()
                ),
                1413711010
            );
        }

        $this->setObjectDataForIndex($document, $index);
        $this->setRawDataForIndex($document->getData(), $index);

        $this->updateIndexesForPosition($document, $index);

        $this->state = self::STATE_DIRTY;
        SharedEventEmitter::emit(Event::DATABASE_DOCUMENT_UPDATED, [$document]);

        return $this;
    }


    /**
     * Removes the given Document from the database
     *
     * @param DocumentInterface $document
     * @return DatabaseInterface
     */
    public function remove(DocumentInterface $document): DatabaseInterface
    {
        $this->assertDataInstancesDatabaseIdentifier($document);
        DocumentUtility::assertDocumentIdentifier($document);

        if (!$this->contains($document)) {
            throw new InvalidDataException(
                sprintf(
                    'Object with GUID %s does not exist in the database. Maybe the values of the identifier is not expressive',
                    $document->getGuid()
                ),
                1412800595
            );
        }

        $index = $this->getIndexForIdentifier($document->getId());
        if ($index === -1) {
            throw new RuntimeException(
                sprintf('Could not determine the index of object with GUID %s', $document->getId()),
                1412801014
            );
        }

        $this->removeObjectDataForIndex($index);
        $this->removeRawDataForIndex($index);

        $this->removeFromIndexes($document);

        if ($this->contains($document)) {
            throw new RuntimeException(sprintf('Database still contains object %s', $document->getGuid()), 1413290094);
        }
        $this->state = self::STATE_DIRTY;
        SharedEventEmitter::emit(Event::DATABASE_DOCUMENT_REMOVED, [$document]);

        return $this;
    }

    public function getObjectDataForIndex($index)
    {
        $document = $this->getObjectDataForIndexIfSet($index);
        if (!$document) {
            $document = $this->convertDataAtIndexToObject($index);
            if ($document === null) {
                return false;
            }
            $this->setObjectDataForIndex($document, $index);
        }

        return $document;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     *
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     *       </p>
     *       <p>
     *       The return value is cast to an integer.
     */
    public function count()
    {
        if ($this->rawData->count() !== $this->objectData->count()) {
            throw new RuntimeException(
                sprintf(
                    'Object and raw data count mismatch (%d/%d)',
                    $this->rawData->count(),
                    $this->objectData->count()
                ), 1413713529
            );
        }

        return $this->rawData->count();
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    // RAW DATA
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    public function getRawData()
    {
        if (!$this->rawData) {
            $this->rawData = new \SplFixedArray(0);
        }

        return $this->rawData;
    }

    public function setRawData($rawData)
    {
        if ($rawData instanceof SplFixedArray) {
            // Use the fixed array as is
        } elseif (is_array($rawData)) {
            $rawData = SplFixedArray::fromArray($rawData);
        } elseif ($rawData instanceof \Traversable) {
            $rawData = SplFixedArray::fromArray(iterator_to_array($rawData));
        } else {
            throw new InvalidCollectionException('Could not set raw data', 1412017652);
        }

        // Make sure all raw Documents have an ID
        $i = 0;
        $count = $rawData->getSize();
        if ($count > 0) {
            $tempRawData = new SplFixedArray($count);
            do {
                $tempRawData[$i] = DocumentUtility::setDocumentIdentifierOfData($rawData[$i]);
            } while (++$i < $count);
            $this->rawData = $tempRawData;
        } else {
            $this->rawData = new SplFixedArray(0);
        }

        $this->objectData = new SplFixedArray($count);
        $this->state = self::STATE_DIRTY;

        $this->rebuildIndexes();
    }

    public function currentRaw()
    {
        $document = $this->getRawDataForIndex($this->index);
        if ($document === false) {
            throw new IndexOutOfRangeException('Invalid index ' . $this->index, 1411316363);
        }

        return $document;
    }

    /**
     * Returns the raw data at the given index or FALSE if it is not set
     *
     * @param int $index
     * @return bool|mixed
     */
    protected function getRawDataForIndex($index)
    {
        if (isset($this->rawData[$index])) {
            $data = $this->rawData[$index];

            return DocumentUtility::setDocumentIdentifierOfData($data);
        }

        return false;
    }

    /**
     * Sets the raw data at the given index
     *
     * @param mixed $data
     * @param int   $index
     *
     * @return mixed Returns the given data
     */
    protected function setRawDataForIndex($data, $index)
    {
        $this->rawData[$index] = DocumentUtility::setDocumentIdentifierOfData($data);

        return $data;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    // MANAGING INDEXES
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    /**
     * Adds the given data instance to the Indexes
     *
     * @param DocumentInterface $document
     * @param int               $position
     */
    protected function addToIndexesAtPosition($document, $position)
    {
        /** @var IndexInterface $indexInstance */
        foreach ($this->indexes as $indexInstance) {
            $indexInstance->addEntryWithPosition($document, $position);
        }
    }

    /**
     * Updates the given data instance in the Indexes
     *
     * @param DocumentInterface $document
     * @param int               $position
     */
    protected function updateIndexesForPosition($document, $position)
    {
        /** @var IndexInterface $indexInstance */
        foreach ($this->indexes as $indexInstance) {
            $indexInstance->updateEntryForPosition($document, $position);
        }
    }

    /**
     * Removes the given data instance from the Indexes
     *
     * @param DocumentInterface $document
     */
    protected function removeFromIndexes($document)
    {
        $this->rebuildIndexes();
    }

    /**
     * Rebuild the Indexes
     */
    protected function rebuildIndexes()
    {
        /** @var IndexInterface $indexInstance */
        foreach ($this->indexes as $indexInstance) {
            $indexInstance->indexDatabase($this);
        }
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    // ARRAYABLE, ITERATOR, COUNTABLE AND SEEKABLE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    public function current()
    {
        return $this->getObjectDataForIndex($this->index);
    }

    public function next()
    {
        $this->index++;
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return $this->index < $this->rawData->count() || $this->index < $this->objectData->count();
    }

    public function rewind()
    {
        $this->index = 0;
    }

    public function seek($position)
    {
        $this->index = (int)$position;
    }

    public function toArray(): array
    {
        return $this->toFixedArray()->toArray();
    }

    public function toFixedArray(): SplFixedArray
    {
        $count = $this->count();
        $i = 0;

        if ($count === 0) {
            return new SplFixedArray(0);
        }
        do {
            $this->getObjectDataForIndex($i);
        } while (++$i < $count);

        return $this->objectData;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    // HELPER METHODS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    /**
     * Checks if the Document instance's database identifier is correct
     *
     * @param DocumentInterface $document
     */
    protected function assertDataInstancesDatabaseIdentifier($document)
    {
        if (!is_object($document)) {
            throw new InvalidDataException(
                sprintf(
                    'Given data instance is not of type object but %s',
                    gettype($document)
                ), 1412859398
            );
        }
        $databaseIdentifier = $document->getDatabaseIdentifier();
        if (!$databaseIdentifier) {
            if ($document instanceof Document) {
                $document->setDatabaseIdentifier($this->identifier);
            }
        } else {
            if ($databaseIdentifier !== $this->identifier) {
                throw new DatabaseMismatchException(
                    sprintf(
                        'The given Document instance does not belong to this database (Document instance database identifier: %s, Database identifier: %s',
                        $databaseIdentifier,
                        $this->identifier
                    ),
                    1411315947
                );
            }
        }
    }

    /**
     * Returns the index for the given identifier or -1 if it does not exist
     *
     * @param string $identifier
     * @return int
     */
    protected function getIndexForIdentifier($identifier)
    {
        $count = $this->count();
        $i = 0;
        $matchingIndex = -1;

        do {
            $foundObject = $this->getObjectDataForIndexIfSet($i);
            if ($foundObject instanceof DocumentInterface && $foundObject->getId() === $identifier) {
                $matchingIndex = $i;
                break;
            }
            $rawData = $this->setRawDataIdentifierIfNotSetForIndex($i);
            if ($rawData[Constants::DATA_ID_KEY] === $identifier) {
                $matchingIndex = $i;
                break;
            }
        } while (++$i < $count);

        return $matchingIndex;
    }

    /**
     * Checks if the raw data's identifier is defined
     *
     * @param int $index
     * @return array Returns the prepared data
     */
    protected function setRawDataIdentifierIfNotSetForIndex($index)
    {
        $rawData = $this->rawData[$index];
        if (!isset($rawData[Constants::DATA_ID_KEY]) || $rawData[Constants::DATA_ID_KEY]) {
            $this->rawData[$index] = DocumentUtility::setDocumentIdentifierOfData($rawData);
        }

        return $rawData;
    }

    /**
     * Sets the Document instance at the given index
     *
     * @param DocumentInterface $object
     * @param int               $index
     * @return DocumentInterface Returns the given object
     */
    protected function setObjectDataForIndex($object, $index)
    {
        if ($index >= $this->objectData->getSize()) {
            throw new InvalidIndexException("Index $index out of range", 1413712508);
        }
        $this->objectData[$index] = $object;

        return $object;
    }

    /**
     * Converts the raw data at the given index to a Document instance
     *
     * @param integer $index
     * @return DocumentInterface
     */
    protected function convertDataAtIndexToObject($index)
    {
        if (isset($this->rawData[$index]) && $this->rawData[$index] === null) {
            return null;
        }
        if (!isset($this->rawData[$index])) {
            DebugUtility::var_dump(
                __METHOD__ . ' valid',
                $this->index < $this->count() || isset($this->rawData[$this->index]),
                $this->index < $this->count(),
                isset($this->rawData[$this->index])
            );
            DebugUtility::var_dump(
                $index,
                $index < $this->count() || isset($this->rawData[$index]),
                $index < $this->count(),
                isset($this->rawData[$index])
            );
            DebugUtility::var_dump($this->rawData);
            throw new IndexOutOfRangeException('Invalid index ' . $index, 1411316363);
        }

        $rawData = $this->rawData[$index];
        $rawData = DocumentUtility::setDocumentIdentifierOfData($rawData);
        $dataObject = new Document($rawData, $this->identifier);

        return $dataObject;
    }

    /**
     * Returns the Document instance at the given index or FALSE if it is not already set
     *
     * @param int $index
     * @return bool|DocumentInterface
     */
    protected function getObjectDataForIndexIfSet($index)
    {
        if (isset($this->objectData[$index])) {
            return $this->objectData[$index];
        }

        return false;
    }

    /**
     * Removes the Document instance at the given index
     *
     * @param int $index
     * @return void
     */
    protected function removeObjectDataForIndex($index)
    {
        $basicArray = $this->objectData->toArray();
        $newArray = array_merge(array_slice($basicArray, 0, $index), array_slice($basicArray, $index + 1));
        $this->objectData = SplFixedArray::fromArray($newArray);
    }

    /**
     * Removes the raw data at the given index
     *
     * @param int $index
     */
    protected function removeRawDataForIndex($index)
    {
        $basicArray = $this->rawData->toArray();
        $newArray = array_merge(array_slice($basicArray, 0, $index), array_slice($basicArray, $index + 1));
        $this->rawData = SplFixedArray::fromArray($newArray);
    }
}
