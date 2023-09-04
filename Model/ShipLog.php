<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Model;

/**
 * Shiporder model
 *
 * @method int getSubscriberId()
 * @method \Eastz\AutoShipment\Model\ShipLog setSubscriberId(int $value)
 * @method int getQueueId()
 * @method \Eastz\AutoShipment\Model\ShipLog setQueueId(int $value)
 * @method int getShipLogErrorCode()
 * @method \Eastz\AutoShipment\Model\ShipLog setShipLogErrorCode(int $value)
 * @method string getShipLogErrorText()
 * @method \Eastz\AutoShipment\Model\ShipLog setShipLogErrorText(string $value)
 *
 * @author     Magento Core Team <core@magentocommerce.com>
 *
 * @api
 * @since 100.0.2
 */
class ShipLog extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Construct
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Eastz\AutoShipment\Model\SubscriberFactory $subscriberFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Initialize Model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Eastz\AutoShipment\Model\ResourceModel\ShipLog::class);
    }
}
