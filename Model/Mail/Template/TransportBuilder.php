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
            $this->_logger->warn('[Flowmailer] object class ' . get_class($data));
	    $data = $data->getData();
#	    if($orgdata instanceof \Magento\Sales\Model\Order\Item) {
#                $data['small_image'] = $this->_imageHelper->init($orgdata->getProduct(), 'small_image', ['type'=>'small_image'])->keepAspectRatio(true)->resize('65','65')->getUrl();
#                $data['image_2'] = $orgdata->getProduct()->getSmallImage();
#                $data['small_image_2'] = $orgdata->getProduct()->getImage();
#	    }
	    if($orgdata instanceof \Magento\Store\Model\Store) {
                $data['base_url'] = $orgdata->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                $data['base_product_image_url'] = $orgdata->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product';
	    }
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
        $this->_logger->debug('[Flowmailer] prepare message ' . json_encode($data));
//        $this->_logger->warn('[Flowmailer] prepare message ' . get_class($this->templateVars['store']));
//        $this->_logger->warn('[Flowmailer] prepare message ' . get_class($this->templateVars['order']));
//        $this->_logger->warn('[Flowmailer] prepare message ' . get_class($this->templateVars['billing']));
//        $this->_logger->warn('[Flowmailer] prepare message ' . json_encode($this->templateVars['billing']->getData()));
//        $this->_logger->warn('[Flowmailer] prepare message ' . json_encode($this->templateVars['order']->getData()));
//        $this->_logger->warn('[Flowmailer] prepare message ' . json_encode($this->templateVars['store']->getData()));
	// TODO: if juiste message class checken
        $this->message->setTemplateVars($data);
        return $this;
    }
}

