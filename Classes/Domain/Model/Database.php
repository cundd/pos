<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 25.08.14
 * Time: 21:30
 */

namespace Cundd\PersistentObjectStore\Domain\Model;

use Cundd\PersistentObjectStore\Core\ArrayException\IndexOutOfRangeException;
use Cundd\PersistentObjectStore\Core\ArrayException\InvalidIndexException;
use Cundd\PersistentObjectStore\Domain\Model\Exception\DatabaseMismatchException;
use Cundd\PersistentObjectStore\Filter\Comparison\ComparisonInterface;
use Cundd\PersistentObjectStore\Filter\Filter;
use Cundd\PersistentObjectStore\LogicException;
use Cundd\PersistentObjectStore\RuntimeException;
use Cundd\PersistentObjectStore\Utility\DebugUtility;
use Cundd\PersistentObjectStore\Utility\GeneralUtility;
use Doctrine\Common\Util\Debug;

/**
 * Database class which holds the Data instances
 *
 * Implementation with object creation on demand.
 *
 * @package Cundd\PersistentObjectStore\Domain\Model
 */
class Database implements DatabaseInterface, \Iterator, \Countable {
	/**
	 * Object collection key for the mapping of the GUID to the object
	 */
	const OBJ_COL_KEY_GUID_TO_OBJECT = 'objects';

	/**
	 * Object collection key for the mapping of the index to the GUID
	 */
	const OBJ_COL_KEY_INDEX_TO_GUID = 'index_to_identifier_map';

	/**
	 * Object collection key for the reference count
	 */
	const OBJ_COL_KEY_REFERENCE_COUNT = 'ref_count';

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
	 * Total object count
	 *
	 * @var int
	 */
	protected $totalCount = -1;

	/**
	 * Raw data array
	 *
	 * @var array
	 */
	protected $rawData = array();

	/**
	 * Collection of converted objects mapped to their database identifier
	 *
	 * @var array
	 */
	static protected $objectCollectionMap = array();


	/**
	 * Creates a new database
	 *
	 * @param string $identifier
	 * @param array  $rawData
	 */
	function __construct($identifier, $rawData = array()) {
		GeneralUtility::assertDatabaseIdentifier($identifier);
		$this->identifier = $identifier;

		if ($rawData) {
			$this->setRawData($rawData);
		}

		$this->_prepareObjectCollectionMap();
		$this->_increaseObjectCollectionReferenceCount();
	}

	function __clone() {
		$this->_increaseObjectCollectionReferenceCount();
	}

	function __wakeup() {
		$this->_increaseObjectCollectionReferenceCount();
	}

	function __destruct() {
		$this->_decreaseObjectCollectionReferenceCount();
		$this->_deleteObjectCollectionIfNecessary();
	}

	function __sleep() {
		$this->_decreaseObjectCollectionReferenceCount();
		$this->_deleteObjectCollectionIfNecessary();
	}


	/**
	 * Returns the database identifier
	 *
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * Sets the raw data
	 *
	 * @param $rawData
	 */
	public function setRawData($rawData) {
		$this->rawData    = $rawData;
		$this->totalCount = count($rawData);
	}

	/**
	 * Returns the raw data
	 *
	 * @return array
	 * @internal
	 */
	public function getRawData() {
		return $this->rawData;
	}


	/**
	 * Filters the database using the given comparisons
	 *
	 * @param array|ComparisonInterface $comparisons
	 * @return \Cundd\PersistentObjectStore\Filter\FilterResultInterface
	 */
	public function filter($comparisons) {
		if (!is_array($comparisons)) {
			$comparisons = func_get_args();
		}
		$filter = new Filter($comparisons);
		return $filter->filterCollection($this);
	}

	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
	// MANAGING OBJECTS
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
	/**
	 * Returns if the database contains the given data instance
	 *
	 * @param DataInterface|string $dataInstance
	 * @return boolean
	 */
	public function contains($dataInstance) {
		return $this->_contains($dataInstance);
	}

	/**
	 * Adds the given data instance to the database
	 *
	 * @param DataInterface $dataInstance
	 */
	public function add($dataInstance) {
		$this->_assertDataInstancesDatabaseIdentifier($dataInstance);
		$newIndex = $this->count();
		DebugUtility::var_dump('Add data instance at', $newIndex);
		$this->_addDataInstanceAtIndex($dataInstance, $newIndex);
//		$this->objectCollection[$this->totalCount] = $dataInstance;
		$this->totalCount++;
	}

	/**
	 * Updates the given data instance in the database
	 *
	 * @param DataInterface $dataInstance
	 */
	public function update($dataInstance) {
		$this->_assertDataInstancesDatabaseIdentifier($dataInstance);

		$identifier = ($dataInstance instanceof DataInterface) ? $dataInstance->getGuid() : spl_object_hash($dataInstance);
		static::$objectCollectionMap[$this->identifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$identifier] = $dataInstance;
	}

	/**
	 * Removes the given data instance from the database
	 *
	 * @param DataInterface $dataInstance
	 */
	public function remove($dataInstance) {
		$this->_assertDataInstancesDatabaseIdentifier($dataInstance);

		$identifier = ($dataInstance instanceof DataInterface) ? $dataInstance->getGuid() : spl_object_hash($dataInstance);
		unset(static::$objectCollectionMap[$this->identifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$identifier]);
	}

	/**
	 * Returns the object with the given identifier
	 *
	 * @param string $identifier
	 * @return DataInterface|NULL
	 */
	public function findByIdentifier($identifier) {
		$foundObject = $this->_getObjectForIdentifier($identifier);
		if ($foundObject) {
			return $foundObject;
		}

		$lastIndex = $this->index;
		$this->rewind();

		while ($this->valid()) {
			/** @var DataInterface $element */
			$element = $this->current();
			if ($element->getId() === $identifier) {
				$foundObject = $element;
				break;
			}
			$this->next();
		}
		$this->index = $lastIndex;
		return $foundObject;
	}


	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
	// ITERATOR AND COUNTABLE
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
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
	public function count() {
		if ($this->totalCount === -1) {
			$this->totalCount = count($this->rawData);
		}
		return $this->totalCount;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 */
	public function current() {
		$currentIndex = $this->index;

		// Try to read the object from the object collection map
//		$this->_prepareObjectCollectionMap();
		$currentObject = $this->_getObjectForIndex($currentIndex);
		if ($currentObject) {
//			DebugUtility::pl('found one');
			return $currentObject;
		}

//		DebugUtility::pl('convert one');
//		DebugUtility::pl('need to convert for index %s', $currentIndex);
		$currentObject = $this->_convertDataAtIndexToObject($currentIndex);
//		DebugUtility::pl('converted object with GUID %s', $currentObject->getGuid());

		$this->_addDataInstanceAtIndex($currentObject, $currentIndex);

		return $currentObject;


//		if (!isset($this->objectCollection[$this->index])) {
//			$this->objectCollection[$this->index] = $this->_convertDataAtIndexToObject($this->index);
//		}
//		return $this->objectCollection[$this->index];
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 */
	public function currentRaw() {
		$index = $this->index;




		if (isset($this->rawData[$index]) && $this->rawData[$index]) {
			return $this->rawData[$index];
		}

		/** @var DataInterface $currentObject */
		$currentObject = $this->current();
		if ($currentObject instanceof DataInterface) {
			return $currentObject->getData();
		}
		throw new IndexOutOfRangeException('Invalid index ' . $index, 1411316363);

		return $dataObject;
	}

	/**
	 * Converts the raw data at the given index to a Data instance
	 *
	 * @param integer $index
	 * @return DataInterface
	 */
	protected function _convertDataAtIndexToObject($index) {
		if (!isset($this->rawData[$index])) {
			DebugUtility::var_dump(
				__METHOD__ . ' valid',
				$this->index < $this->count() || isset($this->rawData[$this->index]) || $this->_getObjectForIndex($this->index),
				$this->index < $this->count(),
				isset($this->rawData[$this->index]),
				!!$this->_getObjectForIndex($this->index)
			);
			DebugUtility::var_dump(
				$index,
				$index < $this->count() || isset($this->rawData[$index]) || $this->_getObjectForIndex($index),
				$index < $this->count(),
				isset($this->rawData[$index]),
				!!$this->_getObjectForIndex($index)
			);
			throw new IndexOutOfRangeException('Invalid index ' . $index, 1411316363);

		}
//		if (!isset($this->rawData[$index])) throw new IndexOutOfRangeException('Invalid index ' . $index);
		$rawData    = $this->rawData[$index];
		$dataObject = new Data();
		$dataObject->setData($rawData);

		$dataObject->setDatabaseIdentifier($this->identifier);
		$dataObject->setCreationTime(isset($rawMetaData['creation_time']) ? $rawMetaData['creation_time'] : NULL);
		$dataObject->setModificationTime(isset($rawMetaData['modification_time']) ? $rawMetaData['modification_time'] : NULL);

		return $dataObject;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Move forward to next element
	 *
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next() {
		$this->index++;

	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the key of the current element
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 */
	public function key() {
		return $this->index;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Checks if current position is valid
	 *
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 *       Returns true on success or false on failure.
	 */
	public function valid() {
		return
			($this->totalCount !== -1 && $this->index < $this->totalCount)
			|| $this->index < $this->count()
			|| isset($this->rawData[$this->index]) || $this->_getObjectForIndex($this->index);
//		return $this->index < $this->count() || isset($this->objectCollection[$this->index]) || isset($this->rawData[$this->index]);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Rewind the Iterator to the first element
	 *
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	public function rewind() {
		$this->index = 0;
	}

//	/**
//	 * Add all objects from the collection to the database
//	 *
//	 * @param array|\Traversable $collection
//	 */
//	public function attachAll($collection) {
//		foreach ($collection as $element) {
//			$this->attach($element);
//		}
//	}


	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
	// HELPER METHODS
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
	/**
	 * Returns the Data instance for the given index or NULL if it does not exist
	 *
	 * @param integer $index
	 * @return DataInterface|NULL
	 */
	protected function _getObjectForIndex($index) {
		if (!is_integer($index) && !((string)(int)$index === $index)) {
			throw new InvalidIndexException('Offset could not be converted to integer', 1410167582);
		}

		$identifier = $this->identifier;
		if (isset(static::$objectCollectionMap[$identifier][self::OBJ_COL_KEY_INDEX_TO_GUID][$index])) {
			$objectHash = static::$objectCollectionMap[$identifier][self::OBJ_COL_KEY_INDEX_TO_GUID][$index];
			return static::$objectCollectionMap[$identifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$objectHash];
		}
		return NULL;
	}

	/**
	 * Returns the Data instance for the given identifier or NULL if it does not exist
	 *
	 * @param string $identifier
	 * @return DataInterface|NULL
	 */
	protected function _getObjectForIdentifier($identifier) {
		if (isset(static::$objectCollectionMap[$this->identifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$identifier])) {
			return static::$objectCollectionMap[$this->identifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$identifier];
		}
		return NULL;
	}

	/**
	 * Adds the given data instance to the database with the given index
	 *
	 * @param DataInterface $dataInstance
	 * @param integer       $index
	 */
	public function _addDataInstanceAtIndex($dataInstance, $index) {
		$objectUid          = ($dataInstance instanceof DataInterface) ? $dataInstance->getGuid() : spl_object_hash($dataInstance);
		$databaseIdentifier = $this->identifier;
		if ($this->_contains($dataInstance)) {
			throw new RuntimeException(
				sprintf('Object with GUID %s already exists in the database. Maybe the values of the identifier %s are not unique', $objectUid, $dataInstance->getIdentifierKey()),
				1411205350
			);
		}
		static::$objectCollectionMap[$databaseIdentifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$objectUid] = $dataInstance;

		static::$objectCollectionMap[$databaseIdentifier][self::OBJ_COL_KEY_INDEX_TO_GUID][$index] = $objectUid;
//		$this->objectCollection[$index] = $dataInstance;
	}

	/**
	 * Returns if the database contains the given data instance
	 *
	 * @param DataInterface|string $dataInstance Actual Data instance or it'S object hash
	 * @return boolean
	 */
	protected function _contains($dataInstance) {
		$databaseIdentifier = $this->identifier;
//		$this->_prepareObjectCollectionMap();
		if (is_object($dataInstance)) {
			$objectId = ($dataInstance instanceof DataInterface) ? $dataInstance->getGuid() : spl_object_hash($dataInstance);
		} else {
			throw new RuntimeException("Given value $dataInstance is of type " .gettype($dataInstance));
			$objectId = $dataInstance;
		}

		return isset(static::$objectCollectionMap[$databaseIdentifier][self::OBJ_COL_KEY_GUID_TO_OBJECT][$objectId]);
	}

	/**
	 * Makes sure the object collection map contains an entry for the current database
	 */
	protected function _prepareObjectCollectionMap() {
		$databaseIdentifier = $this->identifier;
		if (!isset(static::$objectCollectionMap[$databaseIdentifier])) {
			static::$objectCollectionMap[$databaseIdentifier] = array(
				self::OBJ_COL_KEY_REFERENCE_COUNT         => 0,
				self::OBJ_COL_KEY_INDEX_TO_GUID => array(),
				self::OBJ_COL_KEY_GUID_TO_OBJECT           => array()
			);
		}
	}

	/**
	 * Checks if the Data instance's database identifier is correct
	 *
	 * @param DataInterface $dataInstance
	 */
	protected function _assertDataInstancesDatabaseIdentifier($dataInstance) {
		if (!$dataInstance->getDatabaseIdentifier()) {
			if ($dataInstance instanceof Data) {
				$dataInstance->setDatabaseIdentifier($this->identifier);
			}
		} else if ($dataInstance->getDatabaseIdentifier() !== $this->identifier) {
			throw new DatabaseMismatchException(
				sprintf(
					'The given Data instance does not belong to this database (Data instance database identifier: %s, Database identifier: %s',
					$dataInstance->getDatabaseIdentifier(),
					$this->identifier
				),
				1411315947
			);
		}
	}

	/**
	 * Increases the reference count of the object collection map for the given database
	 */
	protected function _increaseObjectCollectionReferenceCount() {
//		$this->_prepareObjectCollectionMap();
		static::$objectCollectionMap[$this->identifier][self::OBJ_COL_KEY_REFERENCE_COUNT]++;
	}

	/**
	 * Decreases the reference count of the object collection map for the given database
	 */
	protected function _decreaseObjectCollectionReferenceCount() {
//		$this->_prepareObjectCollectionMap();
		static::$objectCollectionMap[$this->identifier][self::OBJ_COL_KEY_REFERENCE_COUNT]--;
	}

	/**
	 * Removes the cached object collection for the given database if the reference count is less than 1
	 */
	protected function _deleteObjectCollectionIfNecessary() {
		$databaseIdentifier = $this->identifier;
		if (static::$objectCollectionMap[$databaseIdentifier][self::OBJ_COL_KEY_REFERENCE_COUNT] < 1) {
		#	unset(static::$objectCollectionMap[$databaseIdentifier]);
		}
	}
}