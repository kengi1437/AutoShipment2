<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Eastz\AutoShipment\Helper\ShipUtil;

class OrdersCancelObserver implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Eastz\AutoShipment\Helper\ShipUtil
     */
    protected $_shipUtil;

    /**
     * Constructor.
     * @param \Magento\Sales\Model\Order $order
     * @param \Eastz\AutoShipment\Helper\ShipUtil $shipUtil
     */
    public function __construct(Order $order, ShipUtil $shipUtil)
    {
        $this->_order = $order;
        $this->_shipUtil = $shipUtil;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderId = $observer->getData('order')->getRealOrderId();
        $body = [
            'ext_order_ref' => $orderId
        ];

        $req = json_encode($body, true);
        $order_json = $this->_shipUtil->cancelShipanyOrder(json_encode($body, true), $orderId);
    }
}
