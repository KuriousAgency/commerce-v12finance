<?php
/**
 * Commerce Braintree plugin for Craft CMS 3.x
 *
 * Braintree gateway for Commerce 2
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\gateways;

use Craft;
use craft\commerce\base\Plan as BasePlan;
use craft\commerce\base\PlanInterface;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\SubscriptionGateway as BaseGateway;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\errors\TransactionException;
use craft\commerce\models\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\subscriptions\CancelSubscriptionForm as BaseCancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use craft\db\Query;
use craft\db\Command;
use yii\base\Exception;
use yii\base\NotSupportedException;
use kuriousagency\commerce\v12finance\V12finance;
use kuriousagency\commerce\v12finance\models\V12financePaymentForm;
use kuriousagency\commerce\v12finance\responses\PaymentResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;


/**
 * Gateway represents v12finance gateway
 *
 * @author    Kurious Agency
 * @package   Braintree
 * @since     1.0.0
 *
 */
class Gateway extends BaseGateway
{
	// Properties
	// =========================================================================
	
	public $apiUrl = 'https://apply.v12finance.com/latest/retailerapi/';

	public $authKey;

	public $retailerGuid;

	public $retailerId;

	public $testMode;

	public $data;

	private $retailer;

    // Public Methods
    // =========================================================================

	public function init()
    {
		parent::init();
		
		$this->retailer = [
			'AuthenticationKey' => $this->authKey,
		    'RetailerGuid' => $this->retailerGuid,
		    'RetailerId' => $this->retailerId,
		];
	}
	
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'V12 Finance');
    }


    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new V12financePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('v12finance/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    // public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
    // {
	// 	parent::populateRequest($request, $paymentForm);

	// 	$request['ProductId'] = $paymentForm->ProductId;
	// 	$request['ProductGuid'] = $paymentForm->ProductGuid;
	// 	$request['Deposit'] = $paymentForm->Deposit;
	// 	//Craft::dd($request);
	// }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    // protected function createGateway(): AbstractGateway
    // {
	// 	if ($this->_gateway == null) {
	// 		$this->_gateway = Omnipay::create($this->getGatewayClassName());

	// 		$this->_gateway->setMerchantId($this->merchantId);
	// 		$this->_gateway->setPublicKey($this->publicKey);
	// 		$this->_gateway->setPrivateKey($this->privateKey);
	// 		$this->_gateway->setTestMode($this->testMode);
	// 	}

    //     return $this->_gateway;
	// }


    /**
     * @inheritdoc
     */
    // protected function extractPaymentSourceDescription(ResponseInterface $response): string
    // {
    //     $data = $response->getData();

    //     return Craft::t('commerce-eway', 'Payment card {masked}', ['masked' => $data['Customer']['CardDetails']['Number']]);
    // }




	

	

	public function getEndpoint($action)
	{
		return $this->apiUrl.$action;
	}



	public function getFinanceProducts()
	{
		if ($products = Craft::$app->cache->get('financeProducts')) {
			//return $products;
		}
		
		$response = $this->request('GetRetailerFinanceProducts', [
			'json' => [
				'FinanceProductListRequest' => [
					'Retailer' => $this->retailer,
				]
			]
		]);

		$products = $response->FinanceProducts;

		Craft::$app->cache->set('financeProducts', $products, 604800); //expire after 7 days

		return $products;
	}

	public function getFinanceProduct($id)
	{

	}

	public function getAvailableProducts($saleItems=false)
	{
		$products = [];

		foreach ($this->getAllProducts() as $product)
		{
			if (($saleItems && $product->saleItems) || $product->enabled) {
				$products[] = $product;
			}
		}

		return $products;
	}

	public function getExampleProduct($saleItems=false)
	{
		$amount = 1000;
		$value = 0;
		$selected = null;

		foreach ($this->getAvailableProducts($saleItems) as $product)
		{
			if($value == 0 || round($amount * $product->CalculationFactor, 2) < $value){
			    $value = round($amount * $product->CalculationFactor, 2);
			    $selected = $product;
		    }
		}

		return $selected;
	}

	public function getAllProducts()
	{
		$products = [];

		foreach ($this->getFinanceProducts() as $product)
		{
			$product->enabled = $this->data ? $this->data[$product->ProductId]['enabled'] : false;
			$product->saleItems = $this->data ? $this->data[$product->ProductId]['saleItems'] : false;
		    $products[] = $product;
		}

		uasort($products, [$this,'sortByMonths']);

		return $products;
	}


	public function getProductById($id)
	{

	}

	private function request($uri, $params=[], $method="POST")
	{
		$client = new Client(['base_uri'=>$this->apiUrl]);

		$params = array_merge_recursive([
			'headers' => [
				'Accept' => 'application/json',
			]
		], $params);

		try {
			$response = $client->request($method, $uri, $params);
			return json_decode($response->getBody());
		} catch (\Exception $e) {
			return $e->getMessage();
		}
	}

	private function sortByName($a, $b)
	{
		return strcmp($a->Name, $b->Name);
	}

	private function sortByMonths($a, $b)
	{
		if ($a->Months == $b->Months) {
			if($a->APR == $b->APR) return 0;
			return ($a->APR < $b->APR)?-1:1;
		};
		return ($a->Months < $b->Months)?-1:1;
	}

	private function findProduct($financeProduct, $products)
	{

	}

	private function findFinanceProduct($product, $financeProducts)
	{

	}








	public function checkApplicationStatus(Transaction $transaction)
	{
		// $response = $this->request('CheckApplicationStatus', [
		//     'Retailer' => $this->retailer,
		//     'ApplicationId' => $transaction->reference,
		//     'IncludeExtraDetails' => false,
		//     'IncludeFinancials' => false,
	    // ]);
	    
	    
	    
	    // if($response->AuthorisationCode){
		//     craft()->commerce_orders->completeOrder($order);
		    
		    
		//     $transaction->status = Commerce_TransactionRecord::STATUS_SUCCESS;
		//     $transaction->code = $response->AuthorisationCode;
		//     $transaction->response = json_encode($response);
		//     craft()->commerce_transactions->saveTransaction($transaction);
	    // }
	    
	    // return $response;
	}

         

	public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
		/** @var Payment $form */
		//Craft::dd('here');
		$requestData = $this->_buildRequestData($transaction, $form);
		//Craft::dd($requestData);
		
		/*$requestData['redirect'] = [
			'return_url' => UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash])
		];*/

		//Craft::dd($requestData);

		$applicationResponse = $this->request('SubmitApplication', ['json'=>$requestData]);
		//Craft::dd($applicationResponse);

		if (count($applicationResponse->Errors)) {
			$applicationResponse->message = $applicationResponse->Errors[0]->Description;
			$applicationResponse->code = $applicationResponse->Errors[0]->Code;
		} else {
			$applicationResponse->Status = 'Success';
		}

		$response = new PaymentResponse($applicationResponse);

		//Craft::dd($response->isSuccessful());

		if ($response->isSuccessful()) {
			$response->setProcessing(true);
			$response->setRedirectUrl($applicationResponse->ApplicationFormUrl);
		}

		//$response = $this->_createPaymentResponseFromApiResource($requestData);
		
		//Craft::dd($response);

		return $response;

	}

	public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
		//REF=V12Numeric&SR=YourSalesRef&Flag=30&Status=S&Auth=OurCreditCheckToken&SSR=YourSecondSalesReference
		///actions/v12Finance/response?REF=123456789&SR=cadef120bb7ae731cc73de24e887e3b9&Flag=30&Status=S&Auth=asdfqwer&SSR=0ea02be55cd49c2095721c99263f0a44
		
		$request = Craft::$app->getRequest();
		$data = [
			'ApplicationId' => $request->getParam('REF')
		];

		switch ($request->getParam('Status'))
		{
			case 'S':
				$data['Status'] = 'Success';
				break;
			case 'R':
				$data['Status'] = 'Referred';
				$data['message'] = 'Your application has been referred.';
				break;
			case 'D':
				$data['Status'] = 'Declined';
				$data['message'] = 'Your application has been declined.';
				break;
			case 'C':
				$data['Status'] = 'Cancelled';
				$data['message'] = 'Your application has been cancelled.';
				break;
		}

		$response = new PaymentResponse((object)$data);

		if ($request->getParam('Status') == 'R') {
			$response->setProcessing(true);
		}

		return $response;
		
		//$sourceId = Craft::$app->getRequest()->getParam('source');
        /** @var Source $paymentSource */
		//$paymentSource = Source::retrieve($sourceId);
		$request = Craft::$app->getRequest();
		Craft::dd($request->getRawBody());
        //$response = $this->_createPaymentResponseFromApiResource($paymentSource);
        //$response->setProcessing(true);
    	//return $response;
    }
	
	//apiUrl = 'https://apply.v12finance.com/latest/retailerapi/'
	//AuthenticationKey
	//RetailerGuid
	//RetailerId
	//Deposit
	//TransactionReference
	//ProductGuid
	//ProductId

	//items?
	// purchase
	// complete purchase

    /**
     * @inheritdoc
     */
    // public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    // {
    //     /** @var Payment $sourceData */
    //     $sourceData->token = $this->_normalizePaymentToken((string)$sourceData->token);
    //     try {
    //         $stripeCustomer = $this->_getStripeCustomer($userId);
    //         $stripeResponse = $stripeCustomer->sources->create(['source' => $sourceData->token]);
    //         $stripeCustomer->default_source = $stripeResponse->id;
    //         $stripeCustomer->save();
    //         switch ($stripeResponse->type) {
    //             case 'card':
    //                 $description = Craft::t('commerce-stripe', '{cardType} ending in ••••{last4}', ['cardType' => $stripeResponse->card->brand, 'last4' => $stripeResponse->card->last4]);
    //                 break;
    //             default:
    //                 $description = $stripeResponse->type;
    //         }
    //         $paymentSource = new PaymentSource([
    //             'userId' => $userId,
    //             'gatewayId' => $this->id,
    //             'token' => $stripeResponse->id,
    //             'response' => $stripeResponse->jsonSerialize(),
    //             'description' => $description
    //         ]);
    //         return $paymentSource;
    //     } catch (\Throwable $exception) {
    //         throw new CommercePaymentSourceException($exception->getMessage());
    //     }
    // }
    /**
     * @inheritdoc
     */
    // public function deletePaymentSource($token): bool
    // {
    //     try {
    //         /** @var Source $source */
    //         $source = Source::retrieve($token);
    //         $source->detach();
    //     } catch (\Throwable $throwable) {
    //         // Assume deleted.
    //     }
    //     return true;
	// }
	
	public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
	}
	public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
	}

	public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
	}

	public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
	}

	public function deletePaymentSource($token): bool
    {
	}

	public function cancelSubscription(Subscription $subscription, BaseCancelSubscriptionForm $parameters): SubscriptionResponseInterface
    {
	}
	public function getCancelSubscriptionFormHtml(Subscription $subscription): string
    {
	}
	public function getCancelSubscriptionFormModel(): BaseCancelSubscriptionForm
    {
	}

	public function getNextPaymentAmount(Subscription $subscription): string
    {
	}

	public function getPaymentFormHtml(array $params)
    {
	}

	public function getPlanModel(): BasePlan
    {
	}

	public function getPlanSettingsHtml(array $params = [])
    {
	}

	public function getSubscriptionFormModel(): SubscriptionForm
    {
	}
	public function getSubscriptionPayments(Subscription $subscription): array
    {
	}
	public function getSubscriptionPlanByReference(string $reference): string
    {
	}

	public function getSubscriptionPlans(): array
    {
	}
	public function getSwitchPlansFormHtml(PlanInterface $originalPlan, PlanInterface $targetPlan): string
    {
	}
	public function getSwitchPlansFormModel(): SwitchPlansForm
    {
	}
	public function switchSubscriptionPlan(Subscription $subscription, BasePlan $plan, SwitchPlansForm $parameters): SubscriptionResponseInterface
    {
	}

	public function reactivateSubscription(Subscription $subscription): SubscriptionResponseInterface
    {
	}

	public function refund(Transaction $transaction): RequestResponseInterface
    {
	}
	public function subscribe(User $user, BasePlan $plan, SubscriptionForm $parameters): SubscriptionResponseInterface
    {
	}
	


    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
		$rawData = Craft::$app->getRequest()->getRawBody();
		Craft::dd($rawData);
        $response = Craft::$app->getResponse();
        $secret = $this->signingSecret;
        $stripeSignature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if (!$secret || !$stripeSignature) {
            Craft::warning('Webhook not signed or signing secret not set.', 'stripe');
            $response->data = 'ok';
            return $response;
        }
        try {
            // Check the payload and signature
            Webhook::constructEvent($rawData, $stripeSignature, $secret);
        } catch (\Exception $exception) {
            Craft::warning('Webhook signature check failed: ' . $exception->getMessage(), 'stripe');
            $response->data = 'ok';
            return $response;
        }
        $data = Json::decodeIfJson($rawData);
        if ($data) {
            switch ($data['type']) {
                case 'plan.deleted':
                case 'plan.updated':
                    $this->_handlePlanEvent($data);
                    break;
                case 'invoice.payment_succeeded':
                    $this->_handleInvoiceSucceededEvent($data);
                    break;
                case 'invoice.created':
                    $this->_handleInvoiceCreated($data);
                    break;
                case 'customer.subscription.deleted':
                    $this->_handleSubscriptionExpired($data);
                    break;
                case 'customer.subscription.updated':
                    $this->_handleSubscriptionUpdated($data);
                    break;
                default:
                    if (!empty($data['data']['object']['metadata']['three_d_secure_flow'])) {
                        $this->_handle3DSecureFlowEvent($data);
                    }
            }
            if ($this->hasEventHandlers(self::EVENT_RECEIVE_WEBHOOK)) {
                $this->trigger(self::EVENT_RECEIVE_WEBHOOK, new ReceiveWebhookEvent([
                    'webhookData' => $data
                ]));
            }
        } else {
            Craft::warning('Could not decode JSON payload.', 'stripe');
        }
        $response->data = 'ok';
		return $response;
		
		/*

		The query string for a complete (S for signed agreement) order will look like this: ?REF=V12Numeric&SR=YourSalesRef&Flag=30&Status=S&Auth=OurCreditCheckToken&SSR=YourSecondSalesReference
		S = success
		R = referrer
		D = declined

$hash = craft()->request->getParam('SR');

        $transaction = craft()->commerce_transactions->getTransactionByHash($hash);


        if (!$transaction)
        {
            throw new HttpException(400, Craft::t("Can not complete payment for missing transaction."));
        }

        $customError = "";
        $success = craft()->commerce_payments->completePayment($transaction, $customError);

        if ($success)
        {
            $this->redirect($transaction->order->returnUrl);
        }
        else
        {
            craft()->userSession->setError(Craft::t('Payment error: {message}', ['message' => $customError]));
            $this->redirect($transaction->order->cancelUrl);
        }
		*/
    }

	/**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return false;
	}
	/**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }
	/**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return false;
    }
	/**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }
    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }
    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return true;
	}
	/**
     * @inheritdoc
     */
    public function supportsPlanSwitch(): bool
    {
        return false;
	}
	/**
     * @inheritdoc
     */
    public function supportsReactivation(): bool
    {
        return false;
	}
	/**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return false;
	}
	/**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }
    
    // Private methods
    // =========================================================================
    /**
     * Build the request data array.
     *
     * @param Transaction $transaction the transaction to be used as base
     *
     * @return array
     * @throws NotSupportedException
     */
    private function _buildRequestData(Transaction $transaction, BasePaymentForm $paymentForm): array
    {
		$currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso($transaction->paymentCurrency);
		//Craft::dd($currency);
        if (!$currency) {
            throw new NotSupportedException('The currency “' . $transaction->paymentCurrency . '” is not supported!');
		}
		
		$order = $transaction->getOrder();
		//Craft::dd($order);
		$request = [];

        /*$request = [
            'amount' => $transaction->paymentAmount * (10 ** $currency->minorUnit),
            'currency' => $transaction->paymentCurrency,
			'description' => Craft::t('v12finance', 'Order') . ' #' . $transaction->orderId,
		];*/
		//Craft::dd($request);
        $metadata = [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'transaction_id' => $transaction->id,
            'transaction_reference' => $transaction->hash,
            'client_ip' => Craft::$app->getRequest()->userIP,
        ];
		//$request['metadata'] = $metadata;
		//Craft::dd($request);
		
		$lineItems = [];
		foreach ($order->lineItems as $lineItem)
		{
			$lineItems[] = [
				'OrderLine' => [
					'Item' => $lineItem->description,
					'Price' => (string)$lineItem->price,
					'Qty' => (string)$lineItem->qty,
					'SKU' => $lineItem->sku,
				],
			];
		}
		//Craft::dd($lineItems);
//Craft::dd($order);
		$request['ApplicationRequest'] = [
			'Customer' => [
				'EmailAddress' => $order->email,
				'FirstName' => $order->billingAddress->firstName,
				'LastName' => $order->billingAddress->lastName,
			],
			'Order' => [
				'CashPrice' => (string)$transaction->paymentAmount,
				'Deposit' => (string)$paymentForm->deposit,
				'DuplicateSalesReferenceMethod' => 'ShowError',
				'Lines' => $lineItems,
				'ProductGuid' => $paymentForm->productGuid,
				'ProductId' => $paymentForm->productId,
				'SalesReference' => $transaction->hash,
				'SecondSalesReference' => $order->number,
			],
			'Retailer' => [
				'AuthenticationKey' => $this->authKey,
				'RetailerGuid' => $this->retailerGuid,
				'RetailerId' => $this->retailerId,
			],
			'WaitForDecision' => 'false',
		];
		//Craft::dd($request);

		return $request;
		
    }
    /**
     * Build a payment source for request.
     *
     * @param Transaction $transaction the transaction to be used as base
     * @param Payment $paymentForm the payment form
     * @param array $request the request data
     *
     * @return Source
     * @throws PaymentException if unexpected payment information encountered
     */
    private function _buildRequestPaymentSource(Transaction $transaction, Payment $paymentForm, array $request): Source
    {
        // For 3D secure, make sure to set the redirect URL and the metadata flag, so we can catch it later.
        if ($paymentForm->threeDSecure) {
            unset($request['description'], $request['receipt_email']);
            $request['type'] = 'three_d_secure';
            $request['three_d_secure'] = [
                'card' => $paymentForm->token
            ];
            $request['redirect'] = [
                'return_url' => UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash])
            ];
            $request['metadata']['three_d_secure_flow'] = true;
            return Source::create($request);
        }
        if ($paymentForm->token) {
            $paymentForm->token = $this->_normalizePaymentToken((string)$paymentForm->token);
            /** @var Source $source */
            $source = Source::retrieve($paymentForm->token);
            // If this required 3D secure, let's set the flag for it  and repeat
            if (!empty($source->card->three_d_secure) && $source->card->three_d_secure == 'required') {
                $paymentForm->threeDSecure = true;
                return $this->_buildRequestPaymentSource($transaction, $paymentForm, $request);
            }
            return $source;
        }
        throw new PaymentException(Craft::t('commerce-stripe', 'Cannot process the payment at this time'));
    }
    /**
     * Create a Response object from an ApiResponse object.
     *
     * @param ApiResource $resource
     *
     * @return PaymentResponse
     */
    private function _createPaymentResponseFromApiResource($resource): PaymentResponse
    {
		//$data = $resource->jsonSerialize();
		$data = json_encode($resource);
        return new PaymentResponse($data);
    }
    /**
     * Create a Response object from an Exception.
     *
     * @param \Exception $exception
     *
     * @return PaymentResponse
     * @throws \Exception if not a Stripe exception
     */
    private function _createPaymentResponseFromError(\Exception $exception): PaymentResponse
    {
        if ($exception instanceof CardError) {
            $body = $exception->getJsonBody();
            $data = $body;
            $data['code'] = $body['error']['code'];
            $data['message'] = $body['error']['message'];
            $data['id'] = $body['error']['charge'];
        } else if ($exception instanceof Base) {
            // So it's not a card being declined but something else. ¯\_(ツ)_/¯
            $body = $exception->getJsonBody();
            $data = $body;
            $data['id'] = null;
            $data['message'] = $body['error']['message'] ?? $exception->getMessage();
            $data['code'] = $body['error']['code'] ?? $body['error']['type'] ?? $exception->getStripeCode();
        } else {
            throw $exception;
        }
        return new PaymentResponse($data);
    }
    
    /**
     * Handle a created invoice.
     *
     * @param array $data
     */
    private function _handleInvoiceCreated(array $data)
    {
        $stripeInvoice = $data['data']['object'];
        if ($this->hasEventHandlers(self::EVENT_CREATE_INVOICE)) {
            $this->trigger(self::EVENT_CREATE_INVOICE, new CreateInvoiceEvent([
                'invoiceData' => $stripeInvoice
            ]));
        }
        $canBePaid = empty($stripeInvoice['paid']) && $stripeInvoice['billing'] === 'charge_automatically';
        if (StripePlugin::getInstance()->getSettings()->chargeInvoicesImmediately && $canBePaid) {
            /** @var StripeInvoice $invoice */
            $invoice = StripeInvoice::retrieve($stripeInvoice['id']);
            $invoice->pay();
        }
    }
    /**
     * Handle a successful invoice payment event.
     *
     * @param array $data
     * @throws \Throwable if something went wrong when processing the invoice
     */
    private function _handleInvoiceSucceededEvent(array $data)
    {
        $stripeInvoice = $data['data']['object'];
        // Sanity check
        if (!$stripeInvoice['paid']) {
            return;
        }
        $subscriptionReference = $stripeInvoice['subscription'];
        $counter = 0;
        $limit = 5;
        do {
            // Handle cases when Stripe sends us a webhook so soon that we haven't processed the subscription that triggered the webhook
            sleep(1);
            $subscription = Subscription::find()->reference($subscriptionReference)->one();
            $counter++;
        } while (!$subscription && $counter < $limit);
        if (!$subscription) {
            throw new SubscriptionException('Subscription with the reference “' . $subscriptionReference . '” not found when processing webhook ' . $data['id']);
        }
        $invoice = new Invoice();
        $invoice->subscriptionId = $subscription->id;
        $invoice->reference = $stripeInvoice['id'];
        $invoice->invoiceData = $stripeInvoice;
        StripePlugin::getInstance()->getInvoices()->saveInvoice($invoice);
        $lineItems = $stripeInvoice['lines']['data'];
        $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso(strtoupper($invoice->invoiceData['currency']));
        // Find the relevant line item and update subscription end date
        foreach ($lineItems as $lineItem) {
            if (!empty($lineItem['subscription']) && $lineItem['subscription'] === $subscriptionReference) {
                $payment = $this->_createSubscriptionPayment($invoice->invoiceData, $currency);
                Commerce::getInstance()->getSubscriptions()->receivePayment($subscription, $payment, DateTimeHelper::toDateTime($lineItem['period']['end']));
                return;
            }
        }
    }
    
    /**
     * Get the Stripe customer for a User.
     *
     * @param int $userId
     *
     * @return Customer
     * @throws CustomerException if wasn't able to create or retrieve Stripe Customer.
     */
    private function _getStripeCustomer(int $userId): Customer
    {
        try {
            $user = Craft::$app->getUsers()->getUserById($userId);
            $customers = StripePlugin::getInstance()->getCustomers();
            $customer = $customers->getCustomer($this->id, $user);
            return Customer::retrieve($customer->reference);
        } catch (\Exception $exception) {
            throw new CustomerException('Could not fetch Stripe customer: ' . $exception->getMessage());
        }
    }
    /**
     * Normalize one-time payment token to a source token, that may or may not be multi-use.
     *
     * @param string $token
     * @return string
     */
    private function _normalizePaymentToken(string $token = ''): string
    {
        if (StringHelper::substr($token, 0, 4) === 'tok_') {
            try {
                /** @var Source $tokenSource */
                $tokenSource = Source::create([
                    'type' => 'card',
                    'token' => $token
                ]);
                return $tokenSource->id;
            } catch (\Exception $exception) {
                Craft::error('Unable to normalize payment token: ' . $token . ', because ' . $exception->getMessage());
            }
        }
        return $token;
    }
    /**
     * Make an authorize or purchase request to Stripe
     *
     * @param Transaction $transaction the transaction on which this request is based
     * @param BasePaymentForm $form payment form parameters
     * @param bool $capture whether funds should be captured immediately, defaults to true.
     *
     * @return RequestResponseInterface
     * @throws NotSupportedException if unrecognized currency specified for transaction
     * @throws PaymentException if unexpected payment information provided.
     * @throws \Exception if reasons
     */
    private function _authorizeOrPurchase(Transaction $transaction, BasePaymentForm $form, bool $capture = true): RequestResponseInterface
    {
        /** @var Payment $form */
        $requestData = $this->_buildRequestData($transaction);
        $paymentSource = $this->_buildRequestPaymentSource($transaction, $form, $requestData);
        if ($paymentSource instanceof Source && $paymentSource->status === 'pending' && $paymentSource->flow === 'redirect') {
            // This should only happen for 3D secure payments.
            $response = $this->_createPaymentResponseFromApiResource($paymentSource);
            $response->setRedirectUrl($paymentSource->redirect->url);
            return $response;
        }
        $requestData['source'] = $paymentSource;
        if ($form->customer) {
            $requestData['customer'] = $form->customer;
        }
        $requestData['capture'] = $capture;
        try {
            $charge = Charge::create($requestData, ['idempotency_key' => $transaction->hash]);
            return $this->_createPaymentResponseFromApiResource($charge);
        } catch (\Exception $exception) {
            return $this->_createPaymentResponseFromError($exception);
        }
    }

}