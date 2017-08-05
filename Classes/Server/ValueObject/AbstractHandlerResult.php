<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 11.10.14
 * Time: 14:28
 */

namespace Cundd\PersistentObjectStore\Server\ValueObject;


use Cundd\PersistentObjectStore\Server\Handler\HandlerResultInterface;

/**
 * Abstract result implementation
 *
 * @package Cundd\PersistentObjectStore\Server\ValueObject
 */
abstract class AbstractHandlerResult implements HandlerResultInterface
{
    /**
     * @var integer
     */
    protected $statusCode;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * Creates a new result with the given data and status
     *
     * @param integer $statusCode
     * @param mixed   $data
     */
    public function __construct($statusCode, $data = null)
    {
        $this->statusCode = $statusCode;
        $this->data       = $data;
    }


    /**
     * Returns the request's response data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the status code for the response
     *
     * @return integer
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
