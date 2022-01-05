<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Plugin;

use Flowmailer\M2Connector\Helper\API\Attachment;
use Flowmailer\M2Connector\Helper\API\FlowmailerAPIFactory;
use Flowmailer\M2Connector\Helper\API\SubmitMessage;
use Flowmailer\M2Connector\Registry\MessageData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Phrase;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Zend\Mail\Message;

class TransportPlugin
{
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var bool
     */
    protected $_enabled;

    /**
     * @var MessageData
     */
    protected $_messageData;

    /**
     * @var EncryptorInterface
     */
    private $_encryptor;

    /**
     * @var FlowmailerAPIFactory
     */
    private $_flowmailerApiFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Manager $moduleManager,
        MessageData $messageData,
        LoggerInterface $loggerInterface,
        EncryptorInterface $encryptor,
        FlowmailerApiFactory $flowmailerApiFactory
    ) {
        $this->_scopeConfig          = $scopeConfig;
        $this->_messageData          = $messageData;
        $this->_logger               = $loggerInterface;
        $this->_encryptor            = $encryptor;
        $this->_flowmailerApiFactory = $flowmailerApiFactory;

        $this->_logger->debug('[Flowmailer] messageData2 '.spl_object_id($messageData));

        $this->_enabled = $this->_scopeConfig->isSetFlag('fmconnector/api_credentials/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->_enabled = $this->_enabled && $moduleManager->isOutputEnabled('Flowmailer_M2Connector');
    }

    /**
     * Returns an array with SubmitMessage objects for the API from the current message.
     *
     * @return array<int, SubmitMessage>
     */
    private function _getSubmitMessages(TransportInterface $transport): array
    {
        $text = $transport->getMessage()->getBodyText(false);
        $html = $transport->getMessage()->getBodyHtml(false);

        if ($text instanceof \Zend_Mime_Part) {
            $text = $text->getRawContent();
        }
        if ($html instanceof \Zend_Mime_Part) {
            $html = $html->getRawContent();
        }

        $from = $transport->getMessage()->getFrom();
        if (null === $from) {
            $from = '';
        }

        $messages = [];
        foreach ($transport->getMessage()->getRecipients() as $recipient) {
            $message = new SubmitMessage();

            $message->messageType       = 'EMAIL';
            $message->senderAddress     = $from;
            $message->headerFromAddress = $from;
            if (!empty($from_name)) {
                $message->headerFromName = $from_name;
            }
            $message->recipientAddress = trim($recipient);
            $message->subject          = trim($transport->getMessage()->getSubject());
            $message->html             = $html;
            $message->text             = $text;
            $message->data             = $this->_messageData->getTemplateVars();

            $attachments = [];
            $parts       = $transport->getMessage()->getParts();
            foreach ($parts as $part) {
                $attachment              = new Attachment();
                $attachment->content     = base64_encode($part->getRawContent());
                $attachment->contentType = $part->type;
                $attachment->filename    = $part->filename;

                $attachments[] = $attachment;
            }
            $message->attachments = $attachments;

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Returns an array with SubmitMessage objects for the API from the current message.
     *
     * @return array<int, SubmitMessage>
     */
    private function _getSubmitMessagesZend2(TransportInterface $transport): array
    {
        $raw         = $transport->getMessage()->getRawMessage();
        $rawb64      = base64_encode($raw);
        $zendmessage = Message::fromString($raw);

        if ($zendmessage->getFrom()->count() > 0) {
            $from = $zendmessage->getFrom()->current()->getEmail();
        } else {
            $from = '';
        }

        $recipients = [];
        foreach ($zendmessage->getTo() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }
        foreach ($zendmessage->getCc() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }
        foreach ($zendmessage->getBcc() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }

        $messages = [];
        foreach ($recipients as $recipient) {
            $message = new SubmitMessage();

            $message->messageType      = 'EMAIL';
            $message->senderAddress    = $from;
            $message->recipientAddress = trim($recipient);
            $message->mimedata         = $rawb64;
            $message->data             = $this->_messageData->getTemplateVars();

            $messages[] = $message;
        }

        return $messages;
    }

    public function aroundSendMessage(TransportInterface $subject, \Closure $proceed)
    {
        if ($this->_enabled) {
            try {
                $this->_logger->debug('[Flowmailer] Sending message');
                if ($subject->getMessage() instanceof \Zend_Mail) {
                    $messages = $this->_getSubmitMessages($subject);
                } else {
                    $messages = $this->_getSubmitMessagesZend2($subject);
                }

                $accountId = $this->_scopeConfig->getValue('fmconnector/api_credentials/api_account_id', ScopeInterface::SCOPE_STORE);
                $apiId     = $this->_scopeConfig->getValue('fmconnector/api_credentials/api_client_id', ScopeInterface::SCOPE_STORE);
                $apiSecret = $this->_encryptor->decrypt($this->_scopeConfig->getValue('fmconnector/api_credentials/api_client_secret', ScopeInterface::SCOPE_STORE));

                $api = $this->_flowmailerApiFactory->create($accountId, $apiId, $apiSecret);

                foreach ($messages as $message) {
                    if (isset($message->data['user']['password'])) {
                        $message->data['user']['password'] = null;
                    }
                    if (isset($message->data['user']['current_password'])) {
                        $message->data['user']['current_password'] = null;
                    }
                    if (isset($message->data['user']['password_confirmation'])) {
                        $message->data['user']['password_confirmation'] = null;
                    }

                    $result = $api->submitMessage($message);

                    if ($result['headers']['ResponseCode'] != 201) {
                        throw new \Exception(json_encode($result));
                    }

                    $this->_logger->debug('[Flowmailer] Sending message done '.var_export($result, true));
                }
            } catch (\Exception $exception) {
                $this->_logger->warning('[Flowmailer] Error sending message : '.$exception->getMessage());

                throw new MailException(new Phrase($exception->getMessage()), $exception);
            }
        } else {
            $this->_logger->debug('[Flowmailer] Module not enabled');

            return $proceed();
        }
    }
}
