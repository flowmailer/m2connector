<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Helper\API;

class SubmitMessage
{
    /**
     * @var array<int, Attachment>
     */
    public $attachments;

    /**
     * @var array
     */
    public $data;

    /**
     * @var string
     */
    public $deliveryNotificationType;

    /**
     * @var string
     */
    public $headerFromAddress;

    /**
     * @var string
     */
    public $headerFromName;

    /**
     * @var string
     */
    public $headerToAddress;

    /**
     * @var string
     */
    public $headerToName;

    /**
     * @var string
     */
    public $headers;

    /**
     * @var string
     */
    public $html;

    /**
     * @var string
     */
    public $messageType;

    /**
     * @var string
     */
    public $mimedata;

    /**
     * @var string
     */
    public $recipientAddress;

    /**
     * @var string
     */
    public $senderAddress;

    /**
     * @var string
     */
    public $subject;

    /**
     * @var string
     */
    public $text;
}
