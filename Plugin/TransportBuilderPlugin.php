<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

declare(strict_types=1);

namespace Vendic\FlowmailerM2Connector\Plugin;

use Vendic\FlowmailerM2Connector\Registry\MessageData;
use Magento\Catalog\Helper\Image;
use Magento\Framework\DataObject;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

final class TransportBuilderPlugin
{
    public function __construct(
        private readonly MessageData $messageData,
        private readonly LoggerInterface $logger
    ) {
    }

    public function beforeSetTemplateOptions(TransportBuilder $transportBuilder, $templateOptions)
    {
        $this->logger->debug(
            sprintf('[Flowmailer] set template options messageData1 %s', spl_object_id($this->messageData))
        );
        $this->messageData->setTemplateOptions($templateOptions);

        return null;
    }

    public function beforeSetTemplateIdentifier(TransportBuilder $transportBuilder, $templateIdentifier)
    {
        $this->logger->debug(
            sprintf(
                '[Flowmailer] set template identifier %s messageData1 %s',
                $templateIdentifier,
                spl_object_id($this->messageData)
            )
        );
        $this->messageData->setTemplateIdentifier($templateIdentifier);

        return null;
    }

    public function beforeSetTemplateVars(TransportBuilder $transportBuilder, $templateVars)
    {
        $this->logger->debug(
            sprintf(
                '[Flowmailer] set template variables %s for messageData1 %s',
                json_encode($templateVars),
                spl_object_id($this->messageData)
            )
        );
        $this->messageData->setTemplateVars($this->toData($templateVars));

        return null;
    }

    public function beforeReset(TransportBuilder $transportBuilder)
    {
        $this->logger->debug(
            sprintf(
                '[Flowmailer] Reset messageData1 %s',
                spl_object_id($this->messageData)
            )
        );
        $this->messageData->reset();

        return null;
    }

    private function toData($data, $depth = 0)
    {
        if ($data instanceof AbstractExtensibleModel) {
            $orgdata = $data;

            if ($orgdata instanceof Order) {
                $orgdata->getItems();
            }

            if ($orgdata instanceof Item) {
                // Be sure to load the product array with image urls.
                $orgdata->getProduct();
            }

            if ($orgdata instanceof Shipment) {
                $orgdata->getTracks();
                $orgdata->getComments();
            }

            $this->logger->debug(sprintf('[Flowmailer] extensible object class %s', get_class($data)));

            $data = $data->getData();
            if ($orgdata instanceof Store) {
                $data['base_url'] = $orgdata->getBaseUrl();
                $data['product_image_base_url'] = $orgdata->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
                    . 'catalog/product';
            }
        } elseif ($data instanceof DataObject) {
            $this->logger->debug(sprintf('[Flowmailer] data object class %s', get_class($data)));
            $data = $data->getData();
            unset($data['password_hash']);
        } elseif (is_object($data)) {
            $this->logger->debug(sprintf('[Flowmailer] object class %s', get_class($data)));
        }

        if (is_array($data)) {
            $newData = [];

            if ($depth > 8) {
                return $newData;
            }

            foreach ($data as $key => $value) {
                $newData[$key] = $this->toData($value, $depth + 1);
            }

            return $newData;
        }

        return $data;
    }
}
