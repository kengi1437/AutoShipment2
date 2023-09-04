<?php
namespace Eastz\AutoShipment\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    protected $fileName = '/var/log/autoshipment.log';  // 日誌文件路徑
    protected $loggerType = Logger::DEBUG;  // 日誌級別
}
