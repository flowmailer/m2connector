<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

declare(strict_types=1);

namespace Vendic\FlowmailerM2Connector\Plugin;

use Exception;
use Flowmailer\API\Flowmailer;
use Flowmailer\API\Model\SubmitMessage;
use Vendic\FlowmailerM2Connector\Registry\MessageData;
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
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Manager $moduleManager,
        private readonly MessageData $messageData,
        private readonly LoggerInterface $logger,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    private function getSubmitMessages(TransportInterface $transport): \Generator
    {
        $raw = $transport->getMessage()->getRawMessage();
        $rawb64 = base64_encode($raw);
        $originalMessage = Message::fromString($raw);

        $from = '';
        $fromName = '';

        if ($originalMessage->getFrom()->count() > 0) {
            $from = $originalMessage->getFrom()->current()->getEmail();
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
                );
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
        $accountId = $this->scopeConfig->getValue(
            'fmconnector/api_credentials/api_account_id',
            ScopeInterface::SCOPE_STORE
        );
        $clientId = $this->scopeConfig->getValue(
            'fmconnector/api_credentials/api_client_id',
            ScopeInterface::SCOPE_STORE
        );
        $clientSecret = $this->scopeConfig->getValue(
            'fmconnector/api_credentials/api_client_secret',
            ScopeInterface::SCOPE_STORE
        );
        
        return Flowmailer::init(
            $accountId,
            $clientId,
            $this->encryptor->decrypt($clientSecret)
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
        $this->logger->debug(sprintf('[Flowmailer] messageData2 %s', spl_object_id($this->messageData)));

        if ($this->isExtensionEnabled() === false) {
            $this->logger->debug('[Flowmailer] Module not enabled');

            return $proceed();
        }

        try {
            $this->logger->debug('[Flowmailer] Sending message');

            $this->submitMessages(
                $this->getSubmitMessages($subject)
            );
        } catch (Exception $exception) {
            $this->logger->warning('[Flowmailer] Error sending message : ' . $exception->getMessage());

            throw new MailException(__($exception->getMessage()));
        }
    }

    private function isExtensionEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'fmconnector/api_credentials/enable',
            ScopeInterface::SCOPE_STORE
        ) && $this->moduleManager->isOutputEnabled('Flowmailer_M2Connector');
    }
}
