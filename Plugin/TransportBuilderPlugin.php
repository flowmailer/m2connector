<?php

namespace Flowmailer\M2Connector\Plugin;

use Psr\Log\LoggerInterface;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Catalog\Helper\Image;

use Flowmailer\M2Connector\Registry\MessageData;

class TransportBuilderPlugin
{
	private $_messageData;

	/**
	 * @var Psr\Log\LoggerInterface
	 */
	protected $_logger;

	protected $_imageHelper;

	public function __construct(
		MessageData	 $messageData,
		LoggerInterface  $loggerInterface,
		Image		 $imageHelper
	) {
		$this->_messageData = $messageData;
		$this->_logger = $loggerInterface;
		$this->_imageHelper = $imageHelper;

		$this->_logger->debug('[Flowmailer] messageData1 ' . spl_object_id($messageData));
	}

	public function beforeSetTemplateOptions(TransportBuilder $transportBuilder, $templateOptions)
	{
		$this->_messageData->setTemplateOptions($templateOptions);
		return null;
	}

	public function beforeSetTemplateIdentifier(TransportBuilder $transportBuilder, $templateIdentifier)
	{
		$this->_messageData->setTemplateIdentifier($templateIdentifier);
		return null;
	}

	public function beforeSetTemplateVars(TransportBuilder $transportBuilder, $templateVars)
	{
		$this->_messageData->setTemplateVars($this->toData($templateVars));
		return null;
	}

	public function beforeReset(TransportBuilder $transportBuilder)
	{
		$this->_messageData->reset();
		return null;
	}

	private function toData($data, $depth=0) {
		if($data instanceof \Magento\Framework\Model\AbstractExtensibleModel) {
			$orgdata = $data;
			if($orgdata instanceof \Magento\Sales\Model\Order) {
				$orgdata->getItems();
			}
			if($orgdata instanceof \Magento\Sales\Model\Order\Item) {
				// zodat de product array met image urls mee komt
				$orgdata->getProduct();
			}
			if($orgdata instanceof \Magento\Sales\Model\Order\Shipment) {
				$orgdata->getTracks();
				$orgdata->getComments();
			}
			$this->_logger->debug('[Flowmailer] extensible object class ' . get_class($data));
			$data = $data->getData();
//			if($orgdata instanceof \Magento\Sales\Model\Order\Item) {
//				$data['small_image'] = $this->_imageHelper->init($orgdata->getProduct(), 'small_image', ['type'=>'small_image'])->keepAspectRatio(true)->resize('65','65')->getUrl();
//				$data['image_2'] = $orgdata->getProduct()->getSmallImage();
//				$data['small_image_2'] = $orgdata->getProduct()->getImage();
//			}
			if($orgdata instanceof \Magento\Store\Model\Store) {
				$data['base_url'] = $orgdata->getBaseUrl();
				$data['product_image_base_url'] = $orgdata->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
			}
			if($orgdata instanceof \Magento\Customer\Model\Customer) {
				$data['rp_token'] = $orgdata->getRpToken();
			}

		} else if($data instanceof \Magento\Framework\DataObject) {
			$orgdata = $data;
			$this->_logger->debug('[Flowmailer] data object class ' . get_class($data));
			$data = $data->getData();
			unset($data['password_hash']);

//		} else if($data instanceof \Magento\Customer\Model\Data\CustomerSecure) {
//			$orgdata = $data;
//			$data = array();
//			$data['rp_token'] = $orgdata->getRpToken();

		} else if(is_object($data)) {
			$this->_logger->debug('[Flowmailer] object class ' . get_class($data));
		}
		
		if(is_array($data)) {
			$newdata = array();
			if($depth > 8) {
				return $newdata;
			}
			foreach($data as $key => $value) {
				$newdata[$key] = $this->toData($value, $depth + 1);
			}
			return $newdata;
		}
		return $data;
	}
}

