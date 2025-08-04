<?php
namespace Mavenbird\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class License extends AbstractHelper
{
    const CONFIG_PATH = 'mavenbird_license_status/mavenbird';

    protected $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Get the license status for a specific module.
     *
     * Returns one of 'valid', 'warning', 'blocked', or null if unknown.
     * If any module has 'warning', and the current module is 'valid', returns 'warning' instead.
     *
     * @param string $moduleName Full module name e.g. Mavenbird_Core
     * @return string|null
     */
  public function getModuleStatus(string $moduleName): ?string
    {
        $licenseJson = $this->scopeConfig->getValue(self::CONFIG_PATH, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        if (!$licenseJson) {
            return null;
        }

        $licenseData = json_decode($licenseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($licenseData)) {
            return null;
        }

        $moduleKey = strtolower($moduleName);
        if (!isset($licenseData[$moduleKey]['status'])) {
            return null;
        }

        try {
            $status = $this->encryptor->decrypt($licenseData[$moduleKey]['status']);
        } catch (\Exception $e) {
            return null;
        }
        if (!in_array($status, ['valid', 'warning', 'blocked'], true)) {
            return null;
        }
        try {
            return $this->encryptor->encrypt($status);
        } catch (\Exception $e) {
            return null; 
        }
    }
}
