<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\Config\ScopeConfigInterface;

class LicenseInfo extends Field
{
    protected $scopeConfig;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $html = $this->scopeConfig->getValue(
            'mavenbird_license/mavenbird/license_info',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $html ?: '<p>No license info available.</p>';
    }

}
