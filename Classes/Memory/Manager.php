<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 17.10.14
 * Time: 12:52
 */

namespace Cundd\PersistentObjectStore\Memory;

use Cundd\PersistentObjectStore\Memory\Exception\ManagerException;

/**
 * The Memory Manager tries to help managing the used and available memory
 *
 * @package Cundd\PersistentObjectStore
 */
abstract class Manager implements ManagerInterface
{
    /**
     * A collection of objects that are managed by the Memory Manager
     *
     * @var array
     */
    protected static $managedObjects = array();

    /**
     * A collection tags
     *
     * @var array
     */
    protected static $managedObjectTags = array();

    /**
     * Returns all registered objects
     *
     * @return array
     */
    public static function getAllObjects()
    {
        return self::$managedObjects;
    }

    /**
     * Register the given object for the given identifier
     *
     * @param object $object
     * @param string $identifier
     * @param array  $tags
     */
    public static function registerObject($object, $identifier, $tags = array())
    {
        if (!is_string($identifier)) {
            throw new ManagerException('Given identifier is not of type string. Maybe the arguments are swapped',
                1413544400);
        }
        $identifier                        = self::prepareIdentifier($identifier);
        self::$managedObjects[$identifier] = $object;

        foreach ($tags as $tag) {
            self::addIdentifierForTag($identifier, $tag);
        }
    }

    /**
     * Prepares the given identifier
     *
     * @param string $identifier
     * @return string
     */
    public static function prepareIdentifier($identifier)
    {
        if (!is_scalar($identifier)) {
            throw new ManagerException(sprintf(
                    'Invalid identifier type %s',
                    $identifier === null ? 'null' : gettype($identifier)
                ),
                1413543979);
        }
        return (string)$identifier;
    }

    /**
     * @param string $identifier
     * @param string $tag
     */
    protected static function addIdentifierForTag($identifier, $tag)
    {
        $identifier = self::prepareIdentifier($identifier);
        if (!isset(self::$managedObjectTags[$tag])) {
            self::$managedObjectTags[$tag] = array();
        }
        self::$managedObjectTags[$tag][$identifier] = true;
    }

    /**
     * Returns all objects with a given tag
     *
     * @param string $tag
     * @return array
     */
    public static function getObjectsByTag($tag)
    {
        $foundObjects     = array();
        $foundIdentifiers = self::getIdentifiersByTag($tag, true);
        foreach ($foundIdentifiers as $identifier) {
            $foundObjects[$identifier] = self::getObject($identifier);
        }
        return $foundObjects;
    }

    /**
     * Get identifiers with the given tag
     *
     * @param string $tag
     * @param bool   $graceful
     * @return array
     */
    public static function getIdentifiersByTag($tag, $graceful = false)
    {
        if (!isset(self::$managedObjectTags[$tag])) {
            if (!$graceful) {
                throw new ManagerException(sprintf('Tag %s is not found', $tag), 1413544961);
            }
            return array();
        }
        return array_keys(self::$managedObjectTags[$tag]);
    }

    /**
     * Returns the object for the given identifier or FALSE if it was not found
     *
     * @param string $identifier
     * @return object|bool
     */
    public static function getObject($identifier)
    {
        $identifier = self::prepareIdentifier($identifier);
        if (!self::hasObject($identifier)) {
            return false;
        }
        return self::$managedObjects[$identifier];
    }

    /**
     * Returns if an object for the given identifier is registered
     *
     * @param string $identifier
     * @return object|bool
     */
    public static function hasObject($identifier)
    {
        $identifier = self::prepareIdentifier($identifier);
        return isset(self::$managedObjects[$identifier]);
    }

    /**
     * Free all objects with a given tag
     *
     * @param string $tag
     * @return array
     */
    public static function freeObjectsByTag($tag)
    {
        $foundIdentifiers = self::getIdentifiersByTag($tag, true);
        foreach ($foundIdentifiers as $identifier) {
            self::free($identifier);
        }
    }

    /**
     * Frees the object with the given identifier from the Memory Manager
     *
     * @param string $identifier
     */
    public static function free($identifier)
    {
        $identifier = self::prepareIdentifier($identifier);
        if (!isset(self::$managedObjects[$identifier])) {
            throw new ManagerException(sprintf('No object registered for identifier "%s"', $identifier), 1413543979);
        }

        self::$managedObjects[$identifier] = null;
        unset(self::$managedObjects[$identifier]);

        self::removeIdentifier($identifier);
        self::cleanup();
    }

    /**
     * @param string $identifier
     */
    protected static function removeIdentifier($identifier)
    {
        $identifier = self::prepareIdentifier($identifier);
        foreach (self::$managedObjectTags as $tag => $values) {
            unset(self::$managedObjectTags[$tag][$identifier]);
        }
    }

    /**
     * Tells the Memory Manager to clean up the memory
     */
    public static function cleanup()
    {
        gc_collect_cycles();
    }

    /**
     * Frees all managed objects
     *
     * @internal
     */
    public static function freeAll()
    {
        $identifiers = array_keys(self::$managedObjects);
        foreach ($identifiers as $identifier) {
            self::free($identifier);
        }
        self::$managedObjects = array();
        self::cleanup();
    }
} 