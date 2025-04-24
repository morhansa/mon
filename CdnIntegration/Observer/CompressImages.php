<?php
/**
 * MagoArab CdnIntegration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category   MagoArab
 * @package    MagoArab_CdnIntegration
 * @copyright  Copyright (c) 2025 MagoArab (https://www.mago-ar.com/)
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
 namespace MagoArab\CdnIntegration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MagoArab\CdnIntegration\Helper\Data;

class CompressImages implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Automatically add WebP conversion and image optimization
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        $response = $observer->getEvent()->getResponse();
        if (!$response) {
            return;
        }

        $html = $response->getBody();
        if (empty($html)) {
            return;
        }

        // استبدال الصور بصيغة WebP إذا كان المتصفح يدعمها
        $html = preg_replace_callback(
            '/<img([^>]*)src=[\'"]((?:https?:)?\/\/cdn\.jsdelivr\.net\/gh\/[^\/]+\/[^\/]+@[^\/]+\/[^"\']+\.(jpg|jpeg|png))([\'"][^>]*)>/i',
            function($matches) {
                $before = $matches[1];
                $src = $matches[2];
                $ext = $matches[3];
                $after = $matches[4];
                
                // استبدال الامتداد بـ WebP
                $webpSrc = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $src);
                
                // إضافة صورة WebP مع fallback
                return '<picture>' .
                       '<source srcset="' . $webpSrc . '" type="image/webp">' .
                       '<img' . $before . 'src="' . $src . '"' . $after . '>' .
                       '</picture>';
            },
            $html
        );
        
        // إضافة lazy loading للصور غير المرئية في الشاشة الأولى
        $html = preg_replace(
            '/(<img(?![^>]*loading=)((?!class=|class=["|\'][^"]*above-the-fold).)*?>)/i',
            '<img loading="lazy" $2',
            $html
        );

        $response->setBody($html);
    }
}