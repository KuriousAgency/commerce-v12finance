<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\services;

use kuriousagency\commerce\v12finance\V12finance;

use Craft;
use craft\base\Component;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class V12financeService extends Component
{
    // Public Methods
    // =========================================================================

    /*
     * @return mixed
     */
    public function exampleService()
    {
        $result = 'something';
        // Check our Plugin's settings for `someAttribute`
        if (V12finance::$plugin->getSettings()->someAttribute) {
        }

        return $result;
    }
}
