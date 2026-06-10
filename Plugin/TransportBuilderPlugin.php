<?php

declare(strict_types=1);

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Plugin;

use Flowmailer\M2Connector\Registry\MessageData;
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
    private MessageData $messageData;

    private LoggerInterface $logger;

    public function __construct(
        MessageData $messageData,
        LoggerInterface $logger
    ) {
        $this->messageData = $messageData;
        $this->logger      = $logger;

        $this->logger->debug('[Flowmailer] messageData1 '.spl_object_id($messageData));
    }

    /**
     * @param array<string, mixed> $templateOptions
     */
    public function beforeSetTemplateOptions(TransportBuilder $transportBuilder, array $templateOptions): ?array
    {
        $this->messageData->setTemplateOptions($templateOptions);

        return null;
    }

    public function beforeSetTemplateIdentifier(TransportBuilder $transportBuilder, string $templateIdentifier): ?array
    {
        $this->messageData->setTemplateIdentifier($templateIdentifier);

        return null;
    }

    /**
     * @param array<string, mixed> $templateVars
     */
    public function beforeSetTemplateVars(TransportBuilder $transportBuilder, array $templateVars): ?array
    {
        $this->messageData->setTemplateVars($this->toData($templateVars));

        return null;
    }

    public function beforeReset(TransportBuilder $transportBuilder): ?array
    {
        $this->messageData->reset();

        return null;
    }

    private function toData(mixed $data, int $depth = 0): mixed
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

            $this->logger->debug(sprintf('[Flowmailer] extensible object class %s', $data::class));

            $data = $data->getData();
            if ($orgdata instanceof Store) {
                $data['base_url']               = $orgdata->getBaseUrl();
                $data['product_image_base_url'] = $orgdata->getBaseUrl(UrlInterface::URL_TYPE_MEDIA).'catalog/product';
            }
        } elseif ($data instanceof DataObject) {
            $this->logger->debug(sprintf('[Flowmailer] data object class %s', $data::class));
            $data = $data->getData();
            unset($data['password_hash']);
        } elseif (is_object($data)) {
            $this->logger->debug(sprintf('[Flowmailer] object class %s', $data::class));
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
