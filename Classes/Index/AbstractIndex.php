<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 03.11.14
 * Time: 21:09
 */

namespace Cundd\PersistentObjectStore\Index;

use Cundd\PersistentObjectStore\Domain\Model\DatabaseInterface;

/**
 * Abstract Index implementation
 *
 * @package Cundd\PersistentObjectStore\Index
 */
abstract class AbstractIndex implements IndexInterface
{
    /**
     * Property key to index
     *
     * @var string
     */
    protected $property;

    /**
     * Returns a new Index for the given Database and property
     *
     * @param DatabaseInterface|\Iterator $database
     * @param string                      $property
     */
    public function __construct($database = null, $property = '')
    {
        if ($database) {
            $this->indexDatabase($database);
        }
        if ($property) {
            $this->property = $property;
        }
    }

    /**
     * Returns the property key to be indexed
     *
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * Sets the property key to be indexed
     *
     * @param string $key
     * @return $this
     */
    public function setProperty($key)
    {
        $this->property = $key;
        return $this;
    }

} 