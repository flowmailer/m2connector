<?php

namespace Flowmailer\M2Connector\Model\Mail\Template;

use \Psr\Log\LoggerInterface;
use Magento\Framework\App\TemplateTypesInterface;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\ObjectManagerInterface;

class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
  /**
   * @var \Psr\Log\LoggerInterface
   */
    protected $_logger;

    /**
     * @param FactoryInterface $templateFactory
     * @param MessageInterface $message
     * @param SenderResolverInterface $senderResolver
     * @param ObjectManagerInterface $objectManager
     * @param TransportInterfaceFactory $mailTransportFactory
     */
    public function __construct(
        FactoryInterface $templateFactory,
        MessageInterface $message,
        SenderResolverInterface $senderResolver,
        ObjectManagerInterface $objectManager,
        TransportInterfaceFactory $mailTransportFactory,
	LoggerInterface $loggerInterface,
	\Magento\Catalog\Helper\Image $imageHelper
    ) {
        parent::__construct($templateFactory, $message, $senderResolver, $objectManager, $mailTransportFactory);
        $this->_logger = $loggerInterface;
        $this->_imageHelper = $imageHelper;
    }

    private function toData($data, $depth) {
	if($data instanceof \Magento\Framework\Model\AbstractExtensibleModel) {
            $orgdata = $data;
	    if($orgdata instanceof \Magento\Sales\Model\Order\Item) {
		// zodat de product array met image urls mee komt
                $orgdata->getProduct();
	    }
	    if($orgdata instanceof \Magento\Sales\Model\Order\Shipment) {
                $orgdata->getTracks();
                $orgdata->getComments();
	    }
            $this->_logger->warn('[Flowmailer] extensible object class ' . get_class($data));
	    $data = $data->getData();
//	    if($orgdata instanceof \Magento\Sales\Model\Order\Item) {
//                $data['small_image'] = $this->_imageHelper->init($orgdata->getProduct(), 'small_image', ['type'=>'small_image'])->keepAspectRatio(true)->resize('65','65')->getUrl();
//                $data['image_2'] = $orgdata->getProduct()->getSmallImage();
//                $data['small_image_2'] = $orgdata->getProduct()->getImage();
//	    }
	    if($orgdata instanceof \Magento\Store\Model\Store) {
                $data['base_url'] = $orgdata->getBaseUrl();
                $data['product_image_base_url'] = $orgdata->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
	    }
	    if($orgdata instanceof \Magento\Customer\Model\Customer) {
                $data['rp_token'] = $orgdata->getRpToken();
	    }

	} else if($data instanceof \Magento\Framework\DataObject) {
            $orgdata = $data;
            $this->_logger->warn('[Flowmailer] data object class ' . get_class($data));
	    $data = $data->getData();
	    unset($data['password_hash']);

//	} else if($data instanceof \Magento\Customer\Model\Data\CustomerSecure) {
//            $orgdata = $data;
//	    $data = array();
//            $data['rp_token'] = $orgdata->getRpToken();

	} else if(is_object($data)) {
            $this->_logger->warn('[Flowmailer] object class ' . get_class($data));
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

    /**
     * Prepare message
     *
     * @return $this
     */
    protected function prepareMessage()
    {
	parent::prepareMessage();
        $this->_logger->debug('[Flowmailer] prepare message');
	$data = $this->toData($this->templateVars, 0);
	$data['templateIdentifier'] = $this->templateIdentifier;
//	$data['templateModel'] = $this->templateModel;
	$data['templateOptions'] = $this->templateOptions;
//        $this->_logger->debug('[Flowmailer] prepare message ' . json_encode($data));
        $this->message->setTemplateVars($data);
        return $this;
    }
}

