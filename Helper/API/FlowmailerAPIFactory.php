<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Helper\API;

class FlowmailerAPIFactory
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Psr\Log\LoggerInterface $loggerInterface
    ) {
        $this->_objectManager = $objectManager;
        $this->_logger        = $loggerInterface;
    }

    public function create($accountId, $apiId, $apiSecret)
    {
        $api = $this->_objectManager->create(FlowmailerAPI::class, ['accountId' => $accountId, 'clientId' => $apiId, 'clientSecret' => $apiSecret]);
        $api->setLogger($this->_logger);

        return $api;
    }
}
