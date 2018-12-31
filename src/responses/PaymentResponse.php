<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */
namespace kuriousagency\commerce\v12finance\responses;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;

class PaymentResponse implements RequestResponseInterface
{
    /**
     * @var
     */
    protected $data = [];
    /**
     * @var string
     */
    private $_redirect = '';
    /**
     * @var bool
     */
    private $_processing = false;
    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
    public function setRedirectUrl(string $url)
    {
        $this->_redirect = $url;
    }
    public function setProcessing(bool $status)
    {
        $this->_processing = $status;
    }
    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
		//return count($this->data['Errors']) == 0;
		return $this->data->Status === 'Success';
		//return is_bool($this->data->Status) ? $this->data->Status : $this->data->Status === 'Success';
		//return array_key_exists('status', $this->data) && $this->data['status'] === 'Success';
    }
    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return $this->_processing;
    }
    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return !empty($this->_redirect);
    }
    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }
    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }
    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return $this->_redirect;
    }
    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        if (empty($this->data)) {
            return '';
        }
        return (string)$this->data->ApplicationId;
    }
    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        if (empty($this->data->code)) {
            return '';
        }
        return $this->data->code;
    }
    /**
     * @inheritdoc
     */
    public function getData()
    {
        return $this->data;
    }
    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        if (empty($this->data->message)) {
            return '';
        }
        return $this->data->message;
    }
    /**
     * @inheritdoc
     */
    public function redirect()
    {
        throw new NotImplementedException('Redirecting directly is not implemented for this gateway.');
    }
}