<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Model\Service;

use Magento\Framework\App\State;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class OrderService
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentRepository
     */
    protected $shipmentRepository;

    /**
     * @var \Magento\Shipping\Model\ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * @var \Magento\Sales\Model\Order\Shipment\TrackFactory
     */
    protected $trackFactory;

    /**
     * Constructor.
     * @param State $state
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository, 
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier, 
        \Magento\Framework\DB\Transaction $transaction
    )
    {
        $this->state = $state;
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;

        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
        $this->transaction = $transaction;
    }

    /**
     * Get order by id
     * 
     * @return
     */
    public function getOrderById($orderId)
    {
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);
        return $order;
    }

    /**
     * Get order by system increment identifier
     * 
     * @return
     */
    public function getOrderByIncrementId($incrementId)
    {
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($incrementId);
        return $order;
    }

    /**
     * Process orders
     */
    public function processOrders()
    {
        $date = $this->_objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->date('Y-m-d_H-i-s');

        $orderCollectionFactory = $this->_objectManager->create(\Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class);
        $orderCollection = $orderCollectionFactory->create()
                // ->addAttributeToFilter('created_at', ['gt' => '2022-07-01'])
                ->addAttributeToFilter('shipany_status', 0)
                ->addAttributeToFilter('status', ['in'=> ['processing', 'complete']]);

        foreach($orderCollection as $order) {
            echo '[ORDER]' . ' ' . $order->getId() . ' ' . $order->getIncrementId() . PHP_EOL;
            echo '[STATUS] ' . $order->getStatus() . ' ' . $order->getState() . PHP_EOL;

            $this->processOrder($order);

            $shipments = $order->getShipmentsCollection();
            foreach($shipments as $shipment) {
                // echo '[SHIPMENT]' . ' ' . $shipment->getId() . ' ' . $shipment->getIncrementId() . PHP_EOL;

                // $this->processShipment($shipment);
            }
        }
    }

    /**
     * Process shipments
     */
    public function processShipments()
    {
        $date = $this->_objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->date('Y-m-d_H-i-s');

        $shipmentCollectionFactory = $this->_objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory::class);
        $shipmentCollection = $shipmentCollectionFactory->create()
                ->addAttributeToFilter('created_at', ['gt' => '2022-07-01']);

        foreach($shipmentCollection as $shipment) {
            // echo '[SHIPMENT]' . ' ' . $shipment->getId() . ' ' . $shipment->getIncrementId() . PHP_EOL;

            $this->processShipment($shipment);
        }
    }

    /**
     * Process order
     */
    public function processOrder($order)
    {
        // Process order invoice
        $this->processOrderInvoice($order);

        // Process order shipment
        $this->processOrderShipment($order);

        // Process order shipany create
        $this->processOrderShipanyCreate($order->getIncrementId(), $order);
    }

    /**
     * Process order invoice
     */
    public function processOrderInvoice($order)
    {
        if ($order->getStatus() != 'processing') {
            return;
        }
        if ($order->hasInvoices()) {
            return;
        }
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->save();

            $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
            $this->invoiceSender->send($invoice);

            // Send Invoice mail to customer
            $order->addStatusHistoryComment(__('Notified customer about invoice creation #%1.', $invoice->getId()))
                ->setIsCustomerNotified(false)
                ->save();
        }
    }

    /**
     * Process order shipment
     */
    public function processOrderShipment($order)
    {
        // echo ($order->canShip() ? 'can ship' : 'can not ship') . PHP_EOL;

        // Check if order can be shipped or has already shipped
        if (!$order->canShip()) {
            // throw new \Magento\Framework\Exception\LocalizedException(__('You can\'t create an shipment.'));
            return;
        }

        // Initialize the order shipment object
        $convertOrder = $this->_objectManager->create('Magento\Sales\Model\Convert\Order');
        $shipment = $convertOrder->toShipment($order);

        // Loop through order items
        foreach ($order->getAllItems() AS $orderItem) {
            // Check if order item has qty to ship or is virtual
            if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qtyShipped = $orderItem->getQtyToShip();

            // Create shipment item with qty
            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

            // Add shipment item to shipment
            $shipment->addItem($shipmentItem);
        }

        // Register shipment
        $shipment->register();

        $shipment->getOrder()->setIsInProcess(true);

        try {
            // Save created shipment and order
            $shipment->save();
            $shipment->getOrder()->save();

            // Send email
            $this->_objectManager->create('Magento\Shipping\Model\ShipmentNotifier')->notify($shipment);

            $shipment->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));

            // foreach ($order->getAllItems() AS $orderItem) {
            //     echo $orderItem->getSku() . ', ';
            // }
            // foreach ($order->getAllItems() AS $orderItem) {
            //     echo $orderItem->getSku() . ', ' . $orderItem->getQtyOrdered() . PHP_EOL;
            // }
        }
    }

    /**
     * Process shipment
     */
    public function processShipment($shipment)
    {
        if ($shipment) {
            $path = BP . '/pub/media/shipment/packingslip/';
            // $file = 'packingslip' . '-' . $shipment->getOrder()->getIncrementId() . '-' . $shipment->getIncrementId() . '.pdf';
            $file = 'packingslip' . '-' . $shipment->getOrder()->getIncrementId() . '.pdf';
            if (file_exists($path . $file)) {
                return;
            }

            $pdf = $this->_objectManager->create(
                \Magento\Sales\Model\Order\Pdf\Shipment::class
            )->getPdf(
                [$shipment]
            );

            $pdf->save($path . $file);

            //
            // $path = BP . '/pub/media/shipment/packingslip/';
            // $file = 'packingslip' . '-' . $shipment->getIncrementId() . '.pdf';
            // $pdf->save($path . $file);
        }
    }

    /**
     * Process shipment
     */
    public function processShipmentById($shipmentId)
    {
        $shipment = $this->_objectManager->create(\Magento\Sales\Model\Order\Shipment::class)->load($shipmentId);
        $this->processShipment($shipment);
    }

    /**
     * @param int $shipmentId
     */
    public function addTrackToShipment($shipment, $carrierCode, $title, $trackingNumber)
    {
        try {
            /** Creating Tracking */
            $track = $this->trackFactory->create();
            $track->setTrackNumber($trackingNumber);
            $track->setTitle($title);
            $track->setCarrierCode($carrierCode);

            $shipment->addTrack($track);
            $this->shipmentRepository->save($shipment);

            /* Notify the customer*/
            $this->shipmentNotifier->notify($shipment);

        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * Process order to shipany create
     * 
     * @return
     */
    public function processOrderShipanyCreate($orderId, $order = null)
    {
        // $shipUtil = $this->_objectManager->get(\Eastz\AutoShipment\Helper\ShipUtil::class);
        // $body = $shipUtil->prepareOrderDetail($order);
        // $result = $shipUtil->createShipanyOrder($body);
        // debug_time('order status update: ' . print_r($body, true));

        $shipAny = $this->_objectManager->get(\Eastz\AutoShipment\Helper\ShipAny::class);
        // $result = $shipAny->getMerchantInformation();
        // $result = $shipAny->getCouriers();
        // $result = $shipAny->getShippingRate($orderId);
        $result = $shipAny->createShipOrder($orderId);

//        debug_time('processOrderShipanyCreate ' . $orderId);
//        debug_time($result);

    // Initialize the logger
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    
    // Log the required details
    $logger->info('processOrderShipanyCreate ' . $orderId);
    $logger->info(print_r($result, true));

        $this->processShipanyResult($result);
    }

    /**
     * Process order to shipany cancel
     * 
     * @return
     */
    public function processOrderShipanyCancel($orderId, $order = null)
    {
        $shipAny = $this->_objectManager->get(\Eastz\AutoShipment\Helper\ShipAny::class);
        $result = $shipAny->cancelShipOrder($orderId);
    }

    /**
     * Process shipany result
     * 
     * @return
     */
public function processShipanyResult($result)
{
    $shipAny = $this->_objectManager->get(\Eastz\AutoShipment\Helper\ShipAny::class);
    $trackList = $shipAny->processShipOrderResult($result);

    // Initialize the logger
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/debug.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    
    // Log the required details
    $logger->info('processShipanyResult');
    $logger->info(print_r($trackList, true));

    foreach ($trackList as $trackItem) {
        if ($trackItem['increment_id'] && $trackItem['track_no']) {
            $order = $this->getOrderByIncrementId($trackItem['increment_id']);
            if (!$order || !$order->getId()) {
                continue;
            }

            $logger->info($order->getId() . ' ' . $order->getState() . ' ' . $order->getStatus() . ' ' . $order->getShipanyStatus());
            $logger->info($order->getStatus() == 'processing' ? 'processing' : '');
            $logger->info($trackItem['status'] == 'shipped' ? 'shipped' : '');

            if ($order->getStatus() == 'complete' && empty($trackItem['status'])) {
                // $order->setStatus('processing');
                // $order->setShipanyStatus(1);
                // $order->save();

            } elseif ($order->getStatus() == 'processing' && $trackItem['status'] == 'shipped') {
                $logger->info('processing shipped');
                // $this->processOrder($order);
                $this->processOrderShipment($order);
                $logger->info($order->getId() . ' ' . $order->getState() . ' ' . $order->getStatus() . ' ' . $order->getShipanyStatus());

                $shipments = $order->getShipmentsCollection();
                foreach($shipments as $shipment) {

                    $tracks = $shipment->getTracks();
                    if (!$tracks) {
                        $this->addTrackToShipment($shipment, 'custom', $trackItem['cour_name'], $trackItem['track_no']);
                    }
                }

                $order->setStatus('shipped');
                $order->setShipanyStatus(1);
                $order->save();

            } elseif ($order->getStatus() == 'processing' && $trackItem['status'] == 'complete') {
                $logger->info('processing complete');
                // $this->processOrder($order);
                $this->processOrderShipment($order);
                $logger->info($order->getId() . ' ' . $order->getState() . ' ' . $order->getStatus() . ' ' . $order->getShipanyStatus());

                $shipments = $order->getShipmentsCollection();
                foreach($shipments as $shipment) {

                    $tracks = $shipment->getTracks();
                    if (!$tracks) {
                        $this->addTrackToShipment($shipment, 'custom', $trackItem['cour_name'], $trackItem['track_no']);
                    }
                }

                $order->setStatus('complete');
                $order->setShipanyStatus(1);
                $order->save();

                try {
                    $this->_objectManager->create('Magento\Sales\Model\Order\Email\Sender\OrderSender')->send($order);
                    $logger->info('order complete email success');
                } catch (\Exception $e) {
                    $logger->info('order complete email fail');
                }

            } elseif ($order->getStatus() == 'shipped' && $trackItem['status'] == 'complete') {
                $order->setStatus('complete');
                $order->setShipanyStatus(1);
                $order->save();

                try {
                    $this->_objectManager->create('Magento\Sales\Model\Order\Email\Sender\OrderSender')->send($order);
                    $logger->info('order complete email success');
                } catch (\Exception $e) {
                    $logger->info('order complete email fail');
                }
            }
        }
    }
}
}
