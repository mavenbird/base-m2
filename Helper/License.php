<?php
namespace Mavenbird\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class License extends AbstractHelper
{
    const CONFIG_PREFIX = 'mavenbird_license_status/';

    public function getModuleStatus(string $moduleName): ?string
    {
        $configPath = self::CONFIG_PREFIX . strtolower($moduleName);
        $statusJson = $this->scopeConfig->getValue($configPath, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        if (!$statusJson || !is_string($statusJson)) {
            return null;
        }

        $decoded = json_decode($statusJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['status'])) {
            return null;
        }

        return in_array($decoded['status'], ['valid', 'warning', 'blocked'], true)
            ? $decoded['status']
            : null;
    }
}
