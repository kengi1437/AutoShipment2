<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ShipOrder extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('ez_shiporder', 'entity_id');
    }
}
