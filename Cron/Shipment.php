<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Eastz\AutoShipment\Cron;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\StoresConfig;
use Psr\Log\LoggerInterface;

class Shipment
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoresConfig
     */
    protected $storesConfig;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @param LoggerInterface $logger
     * @param StoresConfig $storesConfig
     * @param Relation $relation
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        LoggerInterface $logger,
        StoresConfig $storesConfig,
        ResolverInterface $localeResolver
    )
    {
        $this->logger = $logger;
        $this->storesConfig = $storesConfig;
        $this->localeResolver = $localeResolver;
    }

    public function execute()
    {
        if (!$this->storesConfig->getStoresConfigByPath('autoshipment/general/cron_active')) {
            $this->logger->info('Auto Shipment: cron inactive');
            return $this;
        }

        $this->localeResolver->emulate(0);
        $this->logger->info('Auto Shipment started...');

        $this->logger->info('Auto Shipment was successful. End.');
        $this->localeResolver->revert();

        return $this;
    }
}
