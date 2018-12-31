<?php
/**
 * Commerce v12finance plugin for Craft CMS 3.x
 *
 * v12finance gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\models;

use kuriousagency\commerce\v12finance\V12finance;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;

use Craft;
use craft\base\Model;

/**
 * @author    Kurious Agency
 * @package   Commerce v12finance
 * @since     1.0.0
 */
class V12financePaymentForm extends BasePaymentForm
{
    /**
     * @var string credit card reference
     */
	public $productId;
	public $productGuid;
	public $deposit;

	
}
