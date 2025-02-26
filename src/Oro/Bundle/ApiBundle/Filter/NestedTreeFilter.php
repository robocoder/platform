<?php

namespace Oro\Bundle\ApiBundle\Filter;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\Value;
use Oro\Bundle\ApiBundle\Exception\InvalidFilterException;
use Oro\Bundle\ApiBundle\Exception\InvalidFilterOperatorException;

/**
 * A filter that can be used to filter child nodes of a tree by a specific parent node
 * and independs on the nesting level of child nodes.
 * Supported modes:
 * * greater than (gt) - returns all child nodes for a given node
 * * greater than or equal to (gte) - returns a given node and all child nodes for this node
 * Note: this filter can be used only for entities based on the nested tree from Gedmo extensions for Doctrine.
 * @link http://atlantic18.github.io/DoctrineExtensions/doc/tree.html
 */
class NestedTreeFilter extends StandaloneFilter
{
    /**
     * {@inheritdoc}
     */
    public function apply(Criteria $criteria, FilterValue $value = null)
    {
        $expr = $this->createExpression($value);
        if (null !== $expr) {
            $criteria->andWhere($expr);
        }
    }

    /**
     * @param FilterValue|null $value
     *
     * @return Expression|null
     */
    private function createExpression(?FilterValue $value): ?Expression
    {
        if (null === $value) {
            return null;
        }

        $path = $value->getPath();
        if (false !== strpos($path, '.')) {
            throw new InvalidFilterException('This filter is not supported for associations.');
        }

        return new Comparison(
            $path,
            $this->getComparisonExpressionOperator($value->getOperator()),
            new Value($value->getValue())
        );
    }

    /**
     * @param string|null $operator
     *
     * @return string
     */
    private function getComparisonExpressionOperator(?string $operator): string
    {
        if ($operator && \in_array($operator, $this->operators, true)) {
            if (ComparisonFilter::GT === $operator) {
                return 'NESTED_TREE';
            }
            if (ComparisonFilter::GTE === $operator) {
                return 'NESTED_TREE_WITH_ROOT';
            }
        }

        throw new InvalidFilterOperatorException($operator ?? self::EQ);
    }
}
