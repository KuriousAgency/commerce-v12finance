<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\assetbundles\V12finance;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class V12financeAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
		
		$this->sourcePath = "@kuriousagency/commerce/v12finance/assetbundles/V12finance/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/CommerceV12finance.js',
        ];

        $this->css = [
            'css/CommerceV12finance.css',
        ];

        parent::init();
    }
}
