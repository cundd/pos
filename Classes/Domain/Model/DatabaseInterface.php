<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 20.09.14
 * Time: 10:44
 */

namespace Cundd\PersistentObjectStore\Domain\Model;

use Cundd\PersistentObjectStore\ArrayableInterface;
use Cundd\PersistentObjectStore\Filter\Comparison\ComparisonInterface;
use Cundd\PersistentObjectStore\Index\IndexableInterface;


/**
 * Interface for Database implementations
 *
 * @package Cundd\PersistentObjectStore\Domain\Model
 */
interface DatabaseInterface extends DatabaseStateInterface, ArrayableInterface, IndexableInterface, \Iterator, \Countable, \SeekableIterator
{
    /**
     * Returns the database identifier
     *
     * @return string
     */
    public function getIdentifier();

    /**
     * Filters the database using the given comparison
     *
     * @param ComparisonInterface $comparison
     * @return \Cundd\PersistentObjectStore\Filter\FilterResultInterface
     */
    public function filter($comparison);

    /**
     * Returns the object with the given identifier
     *
     * @param string $identifier
     * @return DocumentInterface|NULL
     */
    public function findByIdentifier($identifier);

    /**
     * Sets the raw data
     *
     * [Optional]
     *
     * @param $rawData
     */
    // public function setRawData($rawData);


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    // MANAGING OBJECTS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
    /**
     * Adds the given Document to the database
     *
     * @param DocumentInterface $document
     */
    public function add($document);

    /**
     * Updates the given Document in the database
     *
     * @param DocumentInterface $document
     */
    public function update($document);

    /**
     * Removes the given Document from the database
     *
     * @param DocumentInterface $document
     */
    public function remove($document);

    /**
     * Returns if the database contains the given Document
     *
     * @param DocumentInterface|string $document Actual Document instance or it's GUID
     * @return boolean
     */
    public function contains($document);

} 