<?php

namespace Mavenbird\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    public function getDeveloperMessage(): string
    {
        return __('Proudly developed by Mavenbird — India’s #1 Magento Ecommerce Agency and Official Hyvä Bronze Partner.');
    }
}
