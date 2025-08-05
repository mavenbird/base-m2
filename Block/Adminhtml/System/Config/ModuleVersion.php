<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Encryption\EncryptorInterface;

class ModuleVersion extends Field
{
    protected $moduleList;
    protected $scopeConfig;
    protected $encryptor;
    const STYLE_BLOCKED = '
        display: inline-block;
        color: #fff;
        background-color: #d32f2f; /* deep red */
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        box-shadow: 0 2px 6px rgba(211, 47, 47, 0.5);
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    const STYLE_WARNING = '
        display: inline-block;
        color: #444;
        background-color: #fff8e1; /* light yellow */
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        border: 1px solid #fbc02d; /* yellow border */
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    const STYLE_VALID = '
        display: inline-block;
        color: #2e7d32; /* dark green */
        background-color: #e8f5e9; /* light green */
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        border: 1px solid #2e7d32;
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';

    const STYLE_UNKNOWN = '
        display: inline-block;
        color: #757575;
        background-color: #f5f5f5;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 4px;
        font-family: Arial, sans-serif;
        font-size: 13px;
        text-transform: uppercase;
    ';


    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        array $data = []
    ) {
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '<div class="mavenbird-module-versions">';
        $html .= '<table class="admin__table-secondary">
            <thead>
                <tr>
                    <th>' . __('Product') . '</th>
                    <th>' . __('Version') . '</th>
                    <th>' . __('License Status') . '</th>
                </tr>
            </thead>
            <tbody>';

        // Load entire license status JSON once
        $licenseJson = $this->scopeConfig->getValue('mavenbird_license_status/mavenbird');
        $licenseData = is_string($licenseJson) ? json_decode($licenseJson, true) : [];

        foreach ($this->moduleList->getAll() as $module) {
            if (strpos($module['name'], 'Mavenbird_') !== 0) {
                continue;
            }

            $moduleName = str_replace('Mavenbird_', '', $module['name']);
            $displayName = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $moduleName));
            $displayName = $this->escapeHtml($displayName);

            $version = isset($module['setup_version']) ? $this->escapeHtml($module['setup_version']) : 'N/A';

            // Lookup license info for this module (key is lowercase module name)
            $moduleKey = strtolower($module['name']);
            $statusHtml = $this->getLicenseStatusHtml($moduleKey, $licenseData);

            $html .= '<tr>
                        <td>' . $displayName . '</td>
                        <td>' . $version . '</td>
                        <td>' . $statusHtml . '</td>
                      </tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Get formatted license status HTML for a single module key using license data array
     */
    private function getLicenseStatusHtml(string $moduleKey, array $licenseData): string
    {
        if (!isset($licenseData[$moduleKey])) {
            return '<span style="' . self::STYLE_UNKNOWN . '">' . __('Unknown') . '</span>';
        }

        $data = $licenseData[$moduleKey];
        $statusEnc = $data['status'] ?? null;
        $attemptEnc = $data['attempt_count'] ?? null;

        try {
            $status = $statusEnc ? $this->encryptor->decrypt($statusEnc) : 'unknown';
            $attempts = $attemptEnc ? (int)$this->encryptor->decrypt($attemptEnc) : null;
        } catch (\Exception $e) {
            $status = 'unknown';
            $attempts = null;
        }

        $attemptText = $attempts !== null ? ' (' . $attempts . ' attempts)' : '';

        switch ($status) {
            case 'blocked':
                $label = __('Blocked') . $attemptText;
                $style = self::STYLE_BLOCKED;
                break;
            case 'warning':
                $label = __('Warning') . $attemptText;
                $style = self::STYLE_WARNING;
                break;
            case 'valid':
                $label = __('Valid');
                $style = self::STYLE_VALID;
                break;
            default:
                $label = __('Unknown');
                $style = self::STYLE_UNKNOWN;
        }

        return '<span style="' . $style . '">' . $this->escapeHtml($label) . '</span>';
    }
}
