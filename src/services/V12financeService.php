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
use kuriousagency\commerce\v12finance\models\v12FinanceModel;

use Craft;
use craft\base\Component;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class V12financeService extends Component
{
   

	private $client;
    private $retailer;
    
    public function init()
	{
		parent::init();
		
		$this->client = new Client([
		    'base_uri' => craft()->config->get('apiUrl', 'v12finance'),
	    ]);
	    
	    $settings = craft()->plugins->getPlugin('v12Finance')->getSettings();
	    $this->retailer = [
		    'AuthenticationKey' => $settings->authKey,
		    'RetailerGuid' => $settings->guid,
		    'RetailerId' => $settings->id,
	    ];
	}
    
    /**
	*	submit the application to v12
    */
    public function submitApplication($product, $deposit, $order)
    {
	    $data = [];
	    $lineItems = [];
	    
	    craft()->commerce_cart->setPaymentMethod($order, 4);
	    
	    $transaction = craft()->commerce_transactions->createTransaction($order);
	    $transaction->type = Commerce_TransactionRecord::TYPE_PURCHASE;
	    craft()->commerce_transactions->saveTransaction($transaction);
	    
	    foreach($order->lineItems as $lineItem){
		    $lineItems[] = [
			    'OrderLine' => [
				    'Item' => $lineItem->purchasable->title,
				    'Price' => (string) $lineItem->salePrice,
				    'Qty' => $lineItem->qty,
				    'SKU' => $lineItem->purchasable->sku,
			    ],
		    ];
	    }
	    
	    $data = [
		   'ApplicationRequest' => [
			   'Customer' => [
				   'EmailAddress' => $order->customer->email,
				   'FirstName' => $order->billingAddress->firstName,
				   'LastName' => $order->billingAddress->lastName,
			   ],
			   'Order' => [
				   'CashPrice' => (string) $order->totalPrice,
				   'Deposit' => (string) $deposit,
				   'DuplicateSalesReferenceMethod' => 'ShowError',
				   'Lines' => $lineItems,
				   'ProductGuid' => $product->ProductGuid,
				   'ProductId' => $product->ProductId,
				   'SalesReference' => $order->number,
				   'SecondSalesReference' => $transaction->hash,
			   ],
			   'Retailer' => $this->retailer,
			   'WaitForDecision' => 'false',
		   ]
		];
		
		//print_r(json_encode($data));
	    
	    $response = $this->request('SubmitApplication', $data);
	    //print_r($response);
	    
	    //$transaction->status = Commerce_TransactionRecord::STATUS_SUCCESS;
		//$transaction->code = $response->AuthorisationCode;
		$transaction->response = json_encode($response);
		$transaction->reference = $response->ApplicationId;
		craft()->commerce_transactions->saveTransaction($transaction);
	    
		return $response;
    }
    
    public function checkApplicationStatus($order)
    {
	    $transactions = $order->getTransactions();
		$transaction = $transactions[count($transactions)-1];
		
		//print_r($transaction->reference);
	    
	    $response = $this->request('CheckApplicationStatus', [
		    'Retailer' => $this->retailer,
		    'ApplicationId' => $transaction->reference,
		    'IncludeExtraDetails' => false,
		    'IncludeFinancials' => false,
	    ]);
	    
	    
	    
	    if($response->AuthorisationCode){
		    craft()->commerce_orders->completeOrder($order);
		    
		    
		    $transaction->status = Commerce_TransactionRecord::STATUS_SUCCESS;
		    $transaction->code = $response->AuthorisationCode;
		    $transaction->response = json_encode($response);
		    craft()->commerce_transactions->saveTransaction($transaction);
	    }
	    
	    return $response;
    }
    
    
    /**
	    get the configured finance products
	 */
    public function getFinanceProducts()
    {
	    $response = $this->request('GetRetailerFinanceProducts', [
		    'FinanceProductListRequest' => [
			    'Retailer' => $this->retailer,
		    ]
	    ]);
	    return $response->FinanceProducts;

    }
    
    
    private function request($endpoint, $data)
    {
	    $response = $this->client->request('POST', $endpoint, [
		    'json' => $data,
	    ]);
	    
	    return json_decode((string) $response->getBody());
    }
    
    
    
    public function getFinanceProduct($id)
    {
	    $financeProducts = $this->getFinanceProducts();
	    
	    foreach($financeProducts as $product){
		    if($id == $product->ProductId){
			    return $product;
		    }
	    }
	    
	    return null;
    }
    
    
    
    public function getAvailableProducts($saleItems = false)
    {
	    if($saleItems){
	    	$availableProducts = $this->getProductsByAttributes(['enabledForSaleItems'=>1]);
	    }else{
		    $availableProducts = $this->getProductsByAttributes(['enabled'=>1]);
	    }
	    $financeProducts = $this->getFinanceProducts();
	    $products = [];
	    
	    foreach($availableProducts as $product){
		    
		    $products[] = $this->findFinanceProduct($product, $financeProducts);
		    
		}
		
		uasort($products, [$this,'sortByMonths']);
		
		return $products;
    }
    
    public function getExampleProduct($saleItems = false)
    {
	    $availableProducts = $this->getAvailableProducts($saleItems);
	    
	    $amount = 1000;
	    $value = 0;
	    $selected = null;
	    
	    foreach($availableProducts as $product){
		    if($value == 0 || round($amount * $product->CalculationFactor, 2) < $value){
			    $value = round($amount * $product->CalculationFactor, 2);
			    $selected = $product;
		    }
	    }
	    
	    return $product;
    }
    
    
    
    public function getProductsList()
    {
	    $storedProducts = $this->getAllProducts();
	    $financeProducts = $this->getFinanceProducts();
	    $products = [];
	    
	    foreach($financeProducts as $product){
		    
		    $p = $this->findProduct($product, $storedProducts);
		    $product->id = $p->id;
		    $product->enabled = $p->enabled;
		    $product->enabledForSaleItems = $p->enabledForSaleItems;
		    $products[] = $product;
		}
		//print_r($products);
		uasort($products, [$this,'sortByMonths']);
		
		return $products;
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
    
    private function findProduct($financeProduct, $products){
	    foreach($products as $product){
		    if($financeProduct->ProductId == $product->v12ProductId){
			    return $product;
		    }
	    }
	    
	    $model = new v12FinanceModel();
	    $model->v12ProductId = $financeProduct->ProductId;
	    
	    if($this->saveProduct($model)){
		    return $model;
	    }
	    
	    return null;
    }
    
    private function findFinanceProduct($product, $financeProducts)
    {
	    foreach($financeProducts as $p){
		    if($product->v12ProductId == $p->ProductId){
			    return $p;
		    }
	    }
	    
	    return null;
    }
    
    
    public function getAllProducts($criteria = [])
    {
        $results = V12FinanceRecord::model()->findAll($criteria);

        return V12FinanceModel::populateModels($results);
    }
    
    public function getProductsByAttributes($attributes)
    {
        $results = V12FinanceRecord::model()->findAllByAttributes($attributes);

        return V12FinanceModel::populateModels($results);
    }
    
    public function getProductById($id)
    {
        $result = V12FinanceRecord::model()->findById($id);

        if ($result) {
            return V12FinanceModel::populateModel($result);
        }

        return null;
    }
    
    public function saveProduct(V12FinanceModel $model)
    {
        if ($model->id) {
            $record = V12FinanceRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No v12 product exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new v12FinanceRecord();
        }

        $fields = [
            'v12ProductId',
            'enabled',
            'enabledForSaleItems',
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }
        //print_r($model);

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }
    
    public function deleteProductById($id)
    {
        V12FinanceRecord::model()->deleteByPk($id);
    }

}
