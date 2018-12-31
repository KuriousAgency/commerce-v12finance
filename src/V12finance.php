<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance;

use kuriousagency\commerce\v12finance\services\ProductsService as ProductsService;
use kuriousagency\commerce\v12finance\variables\V12financeVariable;
use kuriousagency\commerce\v12finance\models\Settings;

use kuriousagency\commerce\v12finance\gateways\Gateway;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

/**
 * Class V12finance
 *
 * @author    Kurious Agency
 * @package   2.0.0
 *
 * @property  V12financeService $V12financeService
 */
class V12finance extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CommerceV12finance
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
	public $schemaVersion = '1.0.0';
	
	public $apiUrl = 'https://apply.v12finance.com/latest/retailerapi/';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
		self::$plugin = $this;

		$this->setComponents([
            'products' => ProductsService::class,
        ]);
		
		Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES,  function(RegisterComponentTypesEvent $event) {
            $event->types[] = Gateway::class;
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
				//$event->rules['v12finance/response'] = 'v12finance/products/index';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                //$event->rules['v12finance'] = 'v12finance/products/index';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('v12finance', V12financeVariable::class);
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        // Craft::info(
        //     Craft::t(
        //         'v12finance',
        //         '{name} plugin loaded',
        //         ['name' => $this->name]
        //     ),
        //     __METHOD__
        // );
	}
	
	// public function getCpNavItem()
    // {
    //     $parent = parent::getCpNavItem();
    //     // Allow user to override plugin name in sidebar
	// 	$parent['label'] = 'V12 Finance';
	// 	$parent['url'] = 'v12finance';
        
    //     return $parent;
    // }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    // protected function createSettingsModel()
    // {
    //     return new Settings();
    // }

    // /**
    //  * @inheritdoc
    //  */
    // protected function settingsHtml(): string
    // {
    //     return Craft::$app->view->renderTemplate(
    //         'v12finance/settings',
    //         [
    //             'settings' => $this->getSettings()
    //         ]
    //     );
    // }
}
