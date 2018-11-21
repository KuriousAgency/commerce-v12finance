<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\records;

use kuriousagency\commerce\v12finance\V12finance;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class V12financeRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%v12finance}}';
    }
}
