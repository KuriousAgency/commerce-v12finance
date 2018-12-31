<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\controllers;

use kuriousagency\commerce\v12finance\V12finance;

use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use yii\web\HttpException;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class ResponseController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index'];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
		$request = Craft::$app->getRequest();
		//Craft::dd($request);

		$ref = $request->getParam('REF'); //V12Numeric
		$orderNumber = $request->getParam('SSR');
		$flag = $request->getParam('Flag');
		$status = $request->getParam('Status'); // S = Success, R = Referred, D = Declined
		$auth = $request->getParam('Auth'); //V12 credit check token
		$hash = $request->getParam('SR');

		$transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($hash);

		if (!$transaction) {
            throw new HttpException(400, Craft::t('commerce', 'Can not complete payment for missing transaction.'));
        }

        $customError = '';
        $success = Commerce::getInstance()->getPayments()->completePayment($transaction, $customError);

        if ($success) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $response = ['url' => $transaction->order->returnUrl];

                return $this->asJson($response);
            }

            return $this->redirect($transaction->order->returnUrl);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Payment error: {message}', ['message' => $customError]));

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $response = ['url' => $transaction->order->cancelUrl];

            return $this->asJson($response);
        }
//Craft::dd($transaction->order->cancelUrl);
        return $this->redirect($transaction->order->cancelUrl);

    }
}
