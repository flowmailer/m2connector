<?php

declare(strict_types=1);

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Plugin;

use Closure;
use Exception;
use Flowmailer\API\Flowmailer;
use Flowmailer\API\FlowmailerInterface;
use Flowmailer\API\Model\SubmitMessage;
use Flowmailer\M2Connector\Registry\MessageData;
use Generator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Phrase;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

final class TransportPlugin
{
    private LoggerInterface $logger;

    private ScopeConfigInterface $scopeConfig;

    private bool $enabled;

    private MessageData $messageData;

    private EncryptorInterface $encryptor;

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

        $this->enabled = $this->scopeConfig->isSetFlag(
            'fmconnector/api_credentials/enable',
            ScopeInterface::SCOPE_STORE
        ) && $moduleManager->isOutputEnabled('Flowmailer_M2Connector');
    }

    private function getSubmitMessages(TransportInterface $transport): Generator
    {
        $originalMessage = $transport->getMessage();

        // Encode the raw MIME message once; it is identical for every recipient.
        $rawb64 = base64_encode($originalMessage->getRawMessage());

        $from     = '';
        $fromName = '';

        $fromAddresses = $originalMessage->getFrom();
        if ($fromAddresses !== null && count($fromAddresses) > 0) {
            $firstFrom = current($fromAddresses);
            $from      = $firstFrom->getEmail();
            $fromName  = $firstFrom->getName();
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
                    json_decode(
                        json_encode($this->messageData->getTemplateVars(), JSON_THROW_ON_ERROR),
                        false,
                        512,
                        JSON_THROW_ON_ERROR
                    )
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

    private function createApiClient(): FlowmailerInterface
    {
        $accountId = (string) ($this->scopeConfig->getValue(
            'fmconnector/api_credentials/api_account_id',
            ScopeInterface::SCOPE_STORE
        ) ?? '');

        $clientId = (string) ($this->scopeConfig->getValue(
            'fmconnector/api_credentials/api_client_id',
            ScopeInterface::SCOPE_STORE
        ) ?? '');

        $encryptedSecret = (string) ($this->scopeConfig->getValue(
            'fmconnector/api_credentials/api_client_secret',
            ScopeInterface::SCOPE_STORE
        ) ?? '');

        $clientSecret = $this->encryptor->decrypt($encryptedSecret);

        return Flowmailer::init($accountId, $clientId, $clientSecret)
            ->setLogger($this->logger);
    }

    private function submitMessages(Generator $messages): void
    {
        $api = $this->createApiClient();

        foreach ($messages as $message) {
            $this->clearSensitiveData($message);

            $result = $api->submitMessage($message);

            $this->logger->debug(sprintf('[Flowmailer] Sending message done %s', $result));
        }
    }

    /**
     * @return array<int, string>
     */
    private function getRecipients(MessageInterface $originalMessage): array
    {
        $recipients = [];

        foreach ($originalMessage->getTo() as $recipient) {
            $recipients[] = $recipient->getEmail();
        }

        $cc = $originalMessage->getCc();
        if ($cc !== null) {
            foreach ($cc as $recipient) {
                $recipients[] = $recipient->getEmail();
            }
        }

        $bcc = $originalMessage->getBcc();
        if ($bcc !== null) {
            foreach ($bcc as $recipient) {
                $recipients[] = $recipient->getEmail();
            }
        }

        return $recipients;
    }

    /**
     * @throws MailException
     */
    public function aroundSendMessage(TransportInterface $subject, Closure $proceed): mixed
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
        } catch (Exception $exception) {
            $this->logger->warning('[Flowmailer] Error sending message : '.$exception->getMessage());

            throw new MailException(new Phrase($exception->getMessage()), $exception);
        }

        return null;
    }
}
