<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Eastz\AutoShipment\Helper\ShipUtil;

class OrdersAddressEditObserver implements ObserverInterface
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
        $orderId = $observer->getData('order_id');
        $order = $this->_order->load($orderId);
        $realOrderId = $order->getRealOrderId();

        $orderData = $this->_shipUtil->prepareOrderDetail($order);
        $result = $this->_shipUtil->updateShipanyOrder($orderData);
    }
}
