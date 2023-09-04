<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Model\ResourceModel\Refund;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * @method \Eastz\AutoShipment\Model\ShipLog getFirstItem()
 * @method \Eastz\AutoShipment\Model\ShipLog getLastItem()
 * @method \Eastz\AutoShipment\Model\ResourceModel\ShipLog\Collection addFieldToFilter
 * @method \Eastz\AutoShipment\Model\ResourceModel\ShipLog\Collection setOrder
 */
class Collection extends AbstractCollection
{
    /**
     * {@inheritdoc}
     */
    protected $_idFieldName = 'entity_id'; //use in massactions

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Eastz\AutoShipment\Model\ShipLog', 'Eastz\AutoShipment\Model\ResourceModel\ShipLog');
    }
}
