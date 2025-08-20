<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface; 

class NewModules extends Field
{
    protected $scopeConfig;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        //  Now this constant will work
       $modulesJson = $this->scopeConfig->getValue('mavenbird_license/mavenbird/modules');
        $modules = [];

        if ($modulesJson) {
            $modulesArray = json_decode($modulesJson, true);

            if (is_array($modulesArray)) {
                foreach ($modulesArray as $item) {
                    $decoded = json_decode($item, true);
                    if ($decoded) {
                        $modules[] = $decoded;
                    }
                }
            }
        }

        if (empty($modules)) {
            return '<div style="color:red; font-weight:bold;">⚠ No Mavenbird modules found in config!</div>';
        }

        // --- same rendering HTML as before ---
        $html = '<div style="margin-top:40px; padding:20px; background:#fff; border:1px solid #e0e0e0; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.05); font-family:Arial,sans-serif;">';
        $html .= '<h4 style="margin-top:0; margin-bottom:15px; color:#1976d2; font-weight:700; font-size:16px;">' . __('New Mavenbird Modules') . '</h4>';
        $html .= '<ul style="margin:0; padding:0; list-style:none;">';

        foreach ($modules as $module) {
            $html .= sprintf(
                '<li style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #eee; transition: background 0.2s ease;" onmouseover="this.style.background=\'#f9f9f9\'" onmouseout="this.style.background=\'#fff\'">
                    <div style="flex:1; font-weight:600; color:#000;">%s</div>
                    <div style="flex:2; color:#555; padding-left:20px;">%s</div>
                    <div style="flex:none; display:flex; gap:10px; margin-left:20px;">
                        <span style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:#43a047; color:#fff; font-size:12px; font-weight:700; border-radius:12px;">
                            ✔ ' . __('New') . '
                        </span>
                        <a href="%s" target="_blank" style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:#e05807; color:#fff; font-size:12px; font-weight:700; border-radius:12px; text-decoration:none;">' . __('Install Now') . '</a>
                    </div>
                </li>',
                $module['code'],
                $module['name'],
                $module['link']
            );
        }

        $html .= '</ul>';
        $html .= '<p style="margin-top:20px;"><a href="https://www.mavenbird.com/extensions/magentor-2-extensions" target="_blank" style="color:#1976d2; font-weight:600; text-decoration:none;">' . __('View All Mavenbird Extensions') . '</a></p>';
        $html .= '</div>';

        return $html;
    }
}
