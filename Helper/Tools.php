<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Helper;

use Psr\Log\LoggerInterface;

class Tools
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Constructor for the General Settings Block.
     */
    public function __construct(LoggerInterface $loggerInterface)
    {
        $this->_logger = $loggerInterface;
    }

    /**
     * Checks if the specified email is valid.
     *
     * @param string $email
     *
     * @return bool
     */
    public function isEmailValid($email)
    {
        $validator = new \Zend_Validate_EmailAddress();
        if (!$validator->isValid($email)) {
            return false;
        }

        return true;
    }
}
