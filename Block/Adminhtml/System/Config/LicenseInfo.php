<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class LicenseInfo extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '
        <div style="width:100%;padding:28px 36px;margin:30px 0;
                    border-radius:5px;font-size:14px;line-height:1.6;
                    background-color:#e9ffef;
                    color:#333;box-sizing:border-box;">

            <strong style="font-size:18px;color:#000;display:block;margin-bottom:10px;">
                ⚠ License & Domain Registration
            </strong>

            <p>Please register your domain to avoid unlicensed product usage. 
            If the license fails to validate <strong>3 times in a row</strong>, 
            this module will be automatically disabled.</p>

            <div style="margin-top:15px;">
                <strong>How to register your domain:</strong>
                <ol style="margin:10px 0 0 20px;">
                    <li>Login to your Mavenbird customer account.</li>
                    <li>Go to <em>My Downloadable Products</em> section.</li>
                    <li>Add your Magento store domain (e.g. https://yourstore.com).</li>
                    <li>Save changes And your domain will be registered.</li>
                </ol>
            </div>

            <div style="margin-top:15px;">
                Need help? Please 
                <a href="https://www.mavenbird.com/contact" target="_blank" 
                style="color:#1976d2;font-weight:600;text-decoration:none;">
                contact Mavenbird support
                </a>.
            </div>
        </div>';
        return $html;
    }
}
