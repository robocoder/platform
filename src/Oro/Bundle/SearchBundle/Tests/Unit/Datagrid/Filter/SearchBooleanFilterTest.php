<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Datagrid\Filter;

use Oro\Bundle\EntityConfigBundle\Attribute\Type\BooleanAttributeType;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\FilterBundle\Form\Type\Filter\BooleanFilterType;
use Oro\Bundle\SearchBundle\Datagrid\Filter\Adapter\SearchFilterDatasourceAdapter;
use Oro\Bundle\SearchBundle\Datagrid\Filter\SearchBooleanFilter;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Symfony\Component\Form\FormFactoryInterface;

class SearchBooleanFilterTest extends \PHPUnit_Framework_TestCase
{
    private const FIELD_NAME = 'testField';

    /**
     * @var FormFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $formFactory;

    /**
     * @var FilterUtility|\PHPUnit_Framework_MockObject_MockObject
     */
    private $filterUtility;

    /**
     * @var SearchBooleanFilter
     */
    private $filter;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->filterUtility = $this->createMock(FilterUtility::class);

        $this->filter = new SearchBooleanFilter($this->formFactory, $this->filterUtility);
    }

    public function testApplyWhenWrongDatasource()
    {
        /** @var FilterDatasourceAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $dataSource */
        $dataSource = $this->createMock(FilterDatasourceAdapterInterface::class);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid filter datasource adapter provided: '.get_class($dataSource));
        $this->filter->apply($dataSource, []);
    }

    public function testApplyWhenNoValue()
    {
        /** @var FilterDatasourceAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $dataSource */
        $dataSource = $this->createMock(SearchFilterDatasourceAdapter::class);
        $dataSource->expects($this->never())
            ->method('addRestriction');

        $this->filter->init('test', [FilterUtility::DATA_NAME_KEY => self::FIELD_NAME]);
        $this->filter->apply($dataSource, []);
    }

    public function testApplyWhenYes()
    {
        /** @var FilterDatasourceAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $dataSource */
        $dataSource = $this->createMock(SearchFilterDatasourceAdapter::class);
        $dataSource->expects($this->once())
            ->method('addRestriction')
            ->with(Criteria::expr()->eq(self::FIELD_NAME, BooleanAttributeType::TRUE_VALUE));

        $this->filter->init('test', [FilterUtility::DATA_NAME_KEY => self::FIELD_NAME]);
        $this->filter->apply($dataSource, ['value' => BooleanFilterType::TYPE_YES]);
    }

    public function testApplyWhenNo()
    {
        /** @var FilterDatasourceAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $dataSource */
        $dataSource = $this->createMock(SearchFilterDatasourceAdapter::class);
        $dataSource->expects($this->once())
            ->method('addRestriction')
            ->with(Criteria::expr()->eq(self::FIELD_NAME, BooleanAttributeType::FALSE_VALUE));

        $this->filter->init('test', [FilterUtility::DATA_NAME_KEY => self::FIELD_NAME]);
        $this->filter->apply($dataSource, ['value' => BooleanFilterType::TYPE_NO]);
    }

    public function testApplyWhenSomeOther()
    {
        /** @var FilterDatasourceAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $dataSource */
        $dataSource = $this->createMock(SearchFilterDatasourceAdapter::class);
        $dataSource->expects($this->never())
            ->method('addRestriction');

        $this->filter->init('test', [FilterUtility::DATA_NAME_KEY => self::FIELD_NAME]);
        $this->filter->apply($dataSource, ['value' => 'all']);
    }
}
