<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Console\Command;

use Magento\Framework\App\State;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

class Shipment extends Command
{
    /**
     * @var State
     **/
    private $state;

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
     * Constructor.
     * @param State $state
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        parent::__construct();

        $this->state = $state;
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('eastz:autoshipment:shipment')
            ->setDescription('Generate shipment');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

            $message = '<info>Start.<info>';
            $output->writeln($message);

            // Instance of object manager
            $this->processTest();
            // $this->processOrders();

            $message = '<info>Finished<info>';

        } catch (\Exception $e) {
            $message = '<error>' . $e->getMessage() . '<error>';
        }

        $output->writeln($message);
    }

    /**
     * Process test
     *
     * @return
     */
    public function processTest()
    {
        $orderId = 21693;
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);

        $order->setStatus('shipped');
        $order->save();

        // $service = $this->_objectManager->get(\Eastz\AutoShipment\Model\Service\OrderService::class);
        // $service->processOrder($order);
    }

    /**
     * Process orders
     * 
     * @return
     */
    public function processOrders()
    {
        $service = $this->_objectManager->get(\Eastz\AutoShipment\Model\Service\OrderService::class);
        $service->processOrders();
    }
}
