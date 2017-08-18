<?php
declare(strict_types=1);

namespace Cundd\Stairtower\Aggregation;

use Closure;
use Cundd\Stairtower\Aggregation\Exception\InvalidEmitKeyException;
use Cundd\Stairtower\Domain\Model\DatabaseInterface;
use Cundd\Stairtower\Utility\GeneralUtility;
use Iterator;

/**
 * Class MapReduce
 */
class MapReduce extends AbstractAggregator implements MapReduceInterface
{
    /**
     * Undefined callback type
     */
    const CALLBACK_TYPE_NONE = 0;

    /**
     * Callback is a closure
     */
    const CALLBACK_TYPE_CLOSURE = 1;

    /**
     * Callback is an array
     */
    const CALLBACK_TYPE_SIGNATURE_ARRAY = 2;

    /**
     * Callback is a signature in string format
     */
    const CALLBACK_TYPE_SIGNATURE_STRING = 3;


    /**
     * Reduce callback
     *
     * @var Closure
     */
    protected $reduceCallback;

    /**
     * Dictionary of mapping results
     *
     * @var array
     */
    protected $mapResult = [];

    /**
     * Dictionary of reduce results
     *
     * @var array
     */
    protected $reduceResult;


    /**
     * Creates a new MapReduce instance with the given callbacks
     *
     * @param Closure $aggregationCallback
     * @param Closure $reduceCallback
     */
    public function __construct(Closure $aggregationCallback, Closure $reduceCallback)
    {
        parent::__construct($aggregationCallback);
        $this->reduceCallback = $this->prepareCallback($reduceCallback);
        $this->mapResult = [];
    }


    /**
     * Performs the MapReduce operations on the given collection
     *
     * @param DatabaseInterface|Iterator|object[] $collection A collection of objects
     * @return array
     */
    public function perform($collection)
    {
        $this->performAggregation($collection);
        $this->performReduce($this->mapResult);

        return $this->reduceResult;
    }

    /**
     * Adds the value for the given key to the results
     *
     * @param string $key
     * @param mixed  $value
     */
    public function emit($key, $value)
    {
        if (!is_string($key)) {
            throw new InvalidEmitKeyException(
                sprintf('Given key is not of type string but %s', GeneralUtility::getType($key)),
                1425132007
            );
        } elseif (!$key) {
            throw new InvalidEmitKeyException('Empty key given', 1425132007);
        }
        if (!isset($this->mapResult[$key])) {
            $this->mapResult[$key] = [$value];
        } else {
            $this->mapResult[$key][] = $value;
        }
    }

    /**
     * Perform the reduce part
     *
     * @param array $mapResult
     */
    protected function performReduce($mapResult)
    {
        $resultCallbackLocal = $this->reduceCallback;
        $reduceResultLocal = [];
        $i = 0;
        foreach ($mapResult as $key => $value) {
            $reduceResultLocal[$key] = $resultCallbackLocal($key, $value);
            $i++;
        }
        $this->reduceResult = $reduceResultLocal;
    }
}
