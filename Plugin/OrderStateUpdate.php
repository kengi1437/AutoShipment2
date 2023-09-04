<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Plugin;

use Magento\Framework\App\ObjectManager;
use Eastz\AutoShipment\Helper\ShipUtil;

class OrderStateUpdate
{
    /**
     * @var \Eastz\AutoShipment\Helper\ShipUtil
     */
    protected $_shipUtil;

    /**
     * Constructor.
     * @param \Eastz\AutoShipment\Helper\ShipUtil $shipUtil
     */
    public function __construct(ShipUtil $shipUtil)
    {
        $this->_shipUtil = $shipUtil;
    }

    public function beforeSetState(\Magento\Sales\Model\Order $subject, $state)
    {
        $objectManager = ObjectManager::getInstance();
        $createOption = array();
        $createOption[] = 'processing';

        $orderId = $subject->getRealOrderId();
        $currentState = $subject->getState();

        //debug_time('order status update: ' . $orderId . ' ' . $currentState);

        switch ($state) {
            case 'new':
                break;

            case 'processing':
                if ($currentState != $state) {
                    // $body = $this->_shipUtil->prepareOrderDetail($subject);
                    // $this->_shipUtil->createShipanyOrder($body);

                    //debug_time('order status update: ' . $orderId);
                    // $this->processCreateOrder($orderId);
                }
                break;

            case 'closed':
                // $body = array(
                //     'ext_order_ref' => $orderId
                // );
                // $req = json_encode($body, true);
                // $this->_shipUtil->cancelShipanyOrder($req, $orderId);

                // $this->processCancelOrder($orderId);
                break;

            case 'canceled':
                // $body = array(
                //     'ext_order_ref' => $orderId
                // );
                // $req = json_encode($body, true);
                // $this->_shipUtil->cancelShipanyOrder($req, $orderId);

                // $this->processCancelOrder($orderId);
                break;

            case 'complete':
                break;
        }

        return [$state];
    }

    /**
     * Process create order
     */
    public function processCreateOrder($orderId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $service = $objectManager->get(\Eastz\AutoShipment\Model\Service\OrderService::class);
        $service->processOrderShipanyCreate($orderId);
    }

    /**
     * Process cancel order
     */
    public function processCancelOrder($orderId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $service = $objectManager->get(\Eastz\AutoShipment\Model\Service\OrderService::class);
        $service->processOrderShipanyCancel($orderId);
    }
}
