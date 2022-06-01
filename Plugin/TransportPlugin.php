<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Plugin;

use Flowmailer\API\Flowmailer;
use Flowmailer\API\Model\SubmitMessage;
use Flowmailer\M2Connector\Registry\MessageData;
use Laminas\Mail\Message;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Phrase;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

final class TransportPlugin
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var MessageData
     */
    private $messageData;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Manager $moduleManager,
        MessageData $messageData,
        LoggerInterface $logger,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->messageData = $messageData;
        $this->logger      = $logger;
        $this->encryptor   = $encryptor;

        $this->logger->debug(sprintf('[Flowmailer] messageData2 %s', spl_object_id($messageData)));

        $this->enabled = $this->scopeConfig->isSetFlag('fmconnector/api_credentials/enable', ScopeInterface::SCOPE_STORE) && $moduleManager->isOutputEnabled('Flowmailer_M2Connector');
    }

    private function getSubmitMessages(TransportInterface $transport): \Generator
    {
        $raw             = $transport->getMessage()->getRawMessage();
        $rawb64          = base64_encode($raw);
        $originalMessage = Message::fromString($raw);

        $from     = '';
        $fromName = '';
        if ($originalMessage->getFrom()->count() > 0) {
            $from     = $originalMessage->getFrom()->current()->getEmail();
            $fromName = $originalMessage->getFrom()->current()->getName();
        }

        $recipients = $this->getRecipients($originalMessage);

        foreach ($recipients as $recipient) {
            yield (new SubmitMessage())
                ->setMessageType('EMAIL')
                ->setSenderAddress($from)
                ->setHeaderFromAddress($from)
                ->setHeaderFromName($fromName)
                ->setRecipientAddress(trim($recipient))
                ->setMimedata($rawb64)
                ->setData(
                    json_decode(json_encode($this->messageData->getTemplateVars()))
                )
            ;
        }
    }

    private function clearSensitiveData(SubmitMessage $message): void
    {
        $data = $message->getData();

        if (is_object($data) && property_exists($data, 'user')) {
            if (property_exists($data->user, 'password')) {
                $data->user->password = null;
            }
            if (property_exists($data->user, 'current_password')) {
                $data->user->current_password = null;
            }
            if (property_exists($data->user, 'password_confirmation')) {
                $data->user->password_confirmation = null;
            }
        }

        $message->setData($data);
    }

    private function createApiClient(): Flowmailer
    {
        return Flowmailer::init(
            $this->scopeConfig->getValue('fmconnector/api_credentials/api_account_id', ScopeInterface::SCOPE_STORE),
            $this->scopeConfig->getValue('fmconnector/api_credentials/api_client_id', ScopeInterface::SCOPE_STORE),
            $this->encryptor->decrypt($this->scopeConfig->getValue('fmconnector/api_credentials/api_client_secret', ScopeInterface::SCOPE_STORE))
        )->setLogger($this->logger);
    }

    private function submitMessages(\Generator $messages): void
    {
        $api = $this->createApiClient();

        foreach ($messages as $message) {
            $this->clearSensitiveData($message);

            $result = $api->submitMessage($message);

            $this->logger->debug(sprintf('[Flowmailer] Sending message done %s', $result));
        }
    }

    private function getRecipients(Message $originalMessage): array
    {
        $recipients = [];
        foreach ($originalMessage->getTo() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }
        foreach ($originalMessage->getCc() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }
        foreach ($originalMessage->getBcc() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }

        return $recipients;
    }

    public function aroundSendMessage(TransportInterface $subject, \Closure $proceed)
    {
        if ($this->enabled === false) {
            $this->logger->debug('[Flowmailer] Module not enabled');

            return $proceed();
        }

        try {
            $this->logger->debug('[Flowmailer] Sending message');

            $this->submitMessages(
                $this->getSubmitMessages($subject)
            );
        } catch (\Exception $exception) {
            $this->logger->warning('[Flowmailer] Error sending message : '.$exception->getMessage());

            throw new MailException(new Phrase($exception->getMessage()), $exception);
        }
    }
}
