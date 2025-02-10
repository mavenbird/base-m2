<?php
namespace Mavenbird\Core\Block\Adminhtml\System\Config;

use Magento\Framework\Module\ModuleListInterface;

class ModuleVersion extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $moduleList;
    
    private $allowedModules = [
        'Mavenbird_AdminCategoryProductLink' => 'Admin Category Product Link',
        'Mavenbird_AdminCategoryProductThumbnail' => 'Admin Category Product Thumbnail',
        'Mavenbird_AdminLoginAsVendor' => 'Admin Login As Vendor',
        'Mavenbird_AdvancedReview' => 'Advanced Review',
        'Mavenbird_AlsoViewed' => 'Also Viewed',
        'Mavenbird_AutoLogin' => 'Auto Login',
        'Mavenbird_BookingSystem' => 'Booking System',
        'Mavenbird_CmsPageByCustomerGroup' => 'CMS Page By Customer Group',
        'Mavenbird_ContactFormPro' => 'Contact Form Pro',
        'Mavenbird_Core' => 'Core',
        'Mavenbird_CustomOrderNumber' => 'Custom Order Number',
        'Mavenbird_Customform' => 'Custom Form',
        'Mavenbird_DeleteOrder' => 'Delete Order',
        'Mavenbird_EstimatedProfit' => 'Estimated Profit',
        'Mavenbird_Faqs' => 'FAQs',
        'Mavenbird_FraudDetector' => 'Fraud Detector',
        'Mavenbird_FreeShippingAdmin' => 'Free Shipping Admin',
        'Mavenbird_GdprPro' => 'GDPR Pro',
        'Mavenbird_GoogleTagManager' => 'Google Tag Manager',
        'Mavenbird_ImageUploader' => 'Image Uploader',
        'Mavenbird_ImportExportCMSBlocks' => 'Import Export CMS Blocks',
        'Mavenbird_ImportExportCMSPages' => 'Import Export CMS Pages',
        'Mavenbird_ImportExportCategories' => 'Import Export Categories',
        'Mavenbird_ImportExportNewsletterSubscribers' => 'Import Export Newsletter Subscribers',
        'Mavenbird_ImportExportOrder' => 'Import Export Order',
        'Mavenbird_ImportExportProduct' => 'Import Export Product',
        'Mavenbird_ImportExportProductAttributes' => 'Import Export Product Attributes',
        'Mavenbird_ImportExportProductReviews' => 'Import Export Product Reviews',
        'Mavenbird_ImportExportURLRewrites' => 'Import Export URL Rewrites',
        'Mavenbird_ImportExportWishlistProducts' => 'Import Export Wishlist Products',
        'Mavenbird_MiniCartCoupon' => 'Mini Cart Coupon',
        'Mavenbird_MostViewed' => 'Most Viewed',
        'Mavenbird_Multivendor' => 'Multivendor',
        'Mavenbird_MultivendorBaseShipping' => 'Multivendor Base Shipping',
        'Mavenbird_Newproduct' => 'New Product',
        'Mavenbird_OrderInformation' => 'Order Information',
        'Mavenbird_Orderarchive' => 'Order Archive',
        'Mavenbird_Orderattr' => 'Order Attributes',
        'Mavenbird_PaymentRestriction' => 'Payment Restriction',
        'Mavenbird_PredefinedAdminOrderComments' => 'Predefined Admin Order Comments',
        'Mavenbird_ProductAttachment' => 'Product Attachment',
        'Mavenbird_Promotions' => 'Promotions',
        'Mavenbird_PromotionsPro' => 'Promotions Pro',
        'Mavenbird_Quickview' => 'Quickview',
        'Mavenbird_Reindex' => 'Reindex',
        'Mavenbird_Rma' => 'RMA',
        'Mavenbird_Shiprestriction' => 'Ship Restriction',
        'Mavenbird_Shopbybrand' => 'Shop by Brand',
        'Mavenbird_ShoppingFeed' => 'Shopping Feed',
        'Mavenbird_StockImport' => 'Stock Import',
        'Mavenbird_Storelocator' => 'Store Locator',
        'Mavenbird_StorePickupWithLocator' => 'Store Pickup With Locator',
        'Mavenbird_WhatsApp' => 'WhatsApp',
        'Mavenbird_XmlSitemap' => 'XML Sitemap'
    ];

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        $this->moduleList = $moduleList;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<div class="mavenbird-module-versions">';
        $html .= '<table class="admin__table-secondary">
            <thead>
                <tr>
                    <th>Products</th>
                    <th>Version</th>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($this->moduleList->getAll() as $module) {
            if (array_key_exists($module['name'], $this->allowedModules)) {
                $html .= '<tr>
                    <td>' . $this->allowedModules[$module['name']] . '</td>
                    <td>' . ($module['setup_version'] ?? 'N/A') . '</td>
                </tr>';
            }
        }
        
        $html .= '</tbody></table></div>';
        
        return $html;
    }
}
