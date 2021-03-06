<?php
declare(strict_types=1);

namespace Cundd\Stairtower\Filter\Comparison;


use Cundd\Stairtower\Filter\Exception\InvalidComparisonException;


/**
 * Nested logical comparison
 */
class LogicalComparison implements LogicalComparisonInterface
{
    /**
     * Type of the comparison from the comparison value against the given test data's property
     *
     * @var string One of the ComparisonInterface::TYPE constants
     */
    protected $operator;

    /**
     * If strict is true the perform method will throw an InvalidComparisonException if one of the constraints is not an
     * instance of ComparisonInterface
     *
     * This may be useful when building complex nested filters
     *
     * @var bool
     */
    protected $strict = false;

    /**
     * Collection of constraints
     *
     * @var array
     */
    protected $constraints = [];

    /**
     * Creates a new comparison
     *
     * @param string                $operator One of the ComparisonInterface::TYPE constants
     * @param ComparisonInterface[] ...$constraints
     */
    public function __construct(string $operator, ComparisonInterface...$constraints)
    {
        $this->operator = $operator;
        $this->constraints = $constraints;
    }

    /**
     * Build a new 'and' comparison
     *
     * @param ComparisonInterface[] ...$constraints
     * @return LogicalComparison
     */
    public static function logicalAnd(ComparisonInterface...$constraints): LogicalComparison
    {
        return new static(self::TYPE_AND, ...$constraints);
    }

    /**
     * Build a new 'or' comparison
     *
     * @param ComparisonInterface[] ...$constraints
     * @return LogicalComparison
     */
    public static function logicalOr(ComparisonInterface...$constraints): LogicalComparison
    {
        return new static(self::TYPE_OR, ...$constraints);
    }

    /**
     * Performs the comparison against the given test value
     *
     * @param mixed $testValue
     * @return bool
     */
    public function perform($testValue): bool
    {
        $strict = $this->isStrict();
        $operator = $this->getOperator();
        $isOr = $operator === ComparisonInterface::TYPE_OR;
        if ($operator !== ComparisonInterface::TYPE_AND && $operator !== ComparisonInterface::TYPE_OR) {
            throw new InvalidComparisonException(
                sprintf('Can not perform logical comparison with operator %s', $operator),
                1410704637
            );
        }

        $constraints = $this->getConstraints();
        if (!$constraints) {
            throw new InvalidComparisonException('No constraints given', 1410710918);
        }

        foreach ($constraints as $constraint) {
            if ($strict && !($constraint instanceof ComparisonInterface)) {
                throw new InvalidComparisonException(
                    sprintf(
                        'Current constraint is no Comparison Interface instance but %s',
                        is_object($constraint) ? get_class($constraint) : gettype($constraint)
                    ), 1418037096
                );
            }
            $constraintResult = !!($constraint instanceof ComparisonInterface ? $constraint->perform(
                $testValue
            ) : $constraint);

            // If the operator is OR and one constraint is TRUE return TRUE
            if ($isOr && $constraintResult) {
                return true;
            }

            // If the operator is AND and one constraint is FALSE return FALSE
            if (!$isOr && !$constraintResult) {
                return false;
            }
        }

        if ($isOr) { // If the operator is OR and none matched so far, return FALSE
            return false;
        }

        // If the operator is AND and nothing failed, return TRUE
        return true;
    }

    /**
     * Returns the type of the comparison from the comparison value against the given test data's property
     *
     * @return string one of the TYPE constants
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Returns the constraints
     *
     * @return array|\Iterator
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * If strict is true the perform method will throw an InvalidComparisonException if one of the constraints is not an
     * instance of ComparisonInterface
     *
     * @return boolean
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * If strict is true the perform method will throw an InvalidComparisonException if one of the constraints is not an
     * instance of ComparisonInterface
     *
     * @param boolean $strict
     * @return LogicalComparison
     */
    public function setStrict(bool $strict): LogicalComparison
    {
        $this->strict = $strict;

        return $this;
    }
}