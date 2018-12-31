<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\variables;

use kuriousagency\commerce\v12finance\V12finance;

use Craft;
use yii\base\Behavior;
use yii\di\ServiceLocator;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class V12financeVariable extends ServiceLocator
{
    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
		$components = V12finance::$plugin->components;
		unset($components['migrator']);
        $config['components'] = $components;
        parent::__construct($config);
    }
}
