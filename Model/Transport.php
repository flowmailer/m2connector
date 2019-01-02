<?php
namespace Flowmailer\M2Connector\Model;

use \Psr\Log\LoggerInterface;
use \Magento\Framework\Phrase;
use \Magento\Framework\Mail\MessageInterface;
use \Magento\Framework\Exception\MailException;
use \Magento\Framework\Mail\TransportInterface;
use \Magento\Framework\Module\Manager;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\Encryption\EncryptorInterface;
use Flowmailer\M2Connector\Helper\API\FlowmailerAPI;
use Flowmailer\M2Connector\Helper\API\SubmitMessage;
use Flowmailer\M2Connector\Helper\API\Attachment;

class Transport extends \Magento\Framework\Mail\Transport implements TransportInterface {

	/**
	* @var \Magento\Framework\Mail\MessageInterface
	*/
	protected $_message;

	/**
	* @var \Psr\Log\LoggerInterface
	*/
	protected $_logger;

	/**
	* @var \Magento\Framework\Module\Manager
	*/
	protected $_moduleManager;

	/**
	* @var \Magento\Framework\App\Config\ScopeConfigInterface
	*/
	protected $_scopeConfig;

	/**
	* @var \Magento\Framework\Encryption\EncryptorInterface
	*/
	protected $_encryptor;

	/**
	*/
	protected $_enabled;

	/**
	* @param   MessageInterface  $message
	* @param   LoggerInterface   $loggerInterface
	* @throws  \InvalidArgumentException
	*/
	public function __construct(
		MessageInterface     $message,
		LoggerInterface      $loggerInterface,
		Manager		     $moduleManager,
		ScopeConfigInterface $scopeConfig,
		EncryptorInterface   $encryptor,
		$parameters = null
	) {
		parent::__construct($message, $parameters);

		$this->_logger		= $loggerInterface;
		$this->_message		= $message;
		$this->_moduleManager   = $moduleManager;
		$this->_scopeConfig     = $scopeConfig;
		$this->_encryptor       = $encryptor;

		$this->_enabled = $this->_scopeConfig->isSetFlag('fmconnector/api_credentials/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$this->_enabled = $this->_enabled && $this->_moduleManager->isOutputEnabled('Flowmailer_M2Connector');
	}

	/**
	* Returns a string with the JSON request for the API from the current message
	*
	* @return string
	*/
	private function _getSubmitMessages() {

		$text	   = $this->_message->getBodyText(false);
		$html	   = $this->_message->getBodyHtml(false);

		if($text instanceof \Zend_Mime_Part) {
			$text = $text->getRawContent();
		}
		if($html instanceof \Zend_Mime_Part) {
			$html = $html->getRawContent();
		}

                $from = '';
		$from = $this->_message->getFrom();

		$messages = array();
		foreach($this->_message->getRecipients() as $recipient) {
			$message = new SubmitMessage();

			$message->messageType = 'EMAIL';
			$message->senderAddress = $from;
			$message->headerFromAddress = $from;
			if(!empty($from_name)) {
				$message->headerFromName = $from_name;
			}
			$message->recipientAddress = trim($recipient);
			$message->subject = trim($this->_message->getSubject());
			$message->html = $html;
			$message->text = $text;

			if($this->_message instanceof \Flowmailer\M2Connector\Model\Mail\Message) {
				$message->data = $this->_message->getTemplateVars();
			}

			$attachments = array();
			$parts = $this->_message->getParts();
			foreach($parts as $part) {
				$attachment = new Attachment();
				$attachment->content = base64_encode($part->getRawContent());
				$attachment->contentType = $part->type;
				$attachment->filename = $part->filename;

				$attachments[] = $attachment;
			}
			$message->attachments = $attachments;

			$messages[] = $message;
		}
		return $messages;
	}

	/**
	* Returns a string with the JSON request for the API from the current message
	*
	* @return string
	*/
	private function _getSubmitMessagesZend2() {

		$raw = $this->_message->getRawMessage();
		$rawb64 = base64_encode($raw);

		$zendmessage = \Zend\Mail\Message::fromString($raw);

		if($zendmessage->getFrom()->count() > 0) {
			$from = $zendmessage->getFrom()->current()->getEmail();
		} else {
			$from = '';
		}

		$recipients = array();
		foreach($zendmessage->getTo() as $recipient) {
			$recipients[] = $recipient->getEmail();
		}
		foreach($zendmessage->getCc() as $recipient) {
			$recipients[] = $recipient->getEmail();
		}
		foreach($zendmessage->getBcc() as $recipient) {
			$recipients[] = $recipient->getEmail();
		}

		$messages = array();
		foreach($recipients as $recipient) {
			$message = new SubmitMessage();

			$message->messageType = 'EMAIL';
			$message->senderAddress = $from;
			$message->recipientAddress = trim($recipient);
			$message->mimedata = $rawb64;

			if($this->_message instanceof \Flowmailer\M2Connector\Model\Mail\Message) {
				$message->data = $this->_message->getTemplateVars();
			}

			$messages[] = $message;
		}
		return $messages;
	}

	/**
	* Sets the message
	*
	* @param   MessageInterface  $message
	* @return  void
	* @throws  \Magento\Framework\Exception\MailException
	*/
	public function setMessage(MessageInterface $message) {
		$this->_message = $message;
	}

	/**
	* Send a mail using this transport
	*
	* @return void
	* @throws \Magento\Framework\Exception\MailException
	*/
	public function sendMessage() {
		if($this->_enabled) {
			try {
				$this->_logger->debug('[Flowmailer] Sending message');
				if($this->_message instanceof \Zend_Mail) {
					$messages = $this->_getSubmitMessages();
				} else {
					$messages = $this->_getSubmitMessagesZend2();
				}

				$accountId = $this->_scopeConfig->getValue('fmconnector/api_credentials/api_account_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
				$apiId = $this->_scopeConfig->getValue('fmconnector/api_credentials/api_client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
				$apiSecret = $this->_encryptor->decrypt($this->_scopeConfig->getValue('fmconnector/api_credentials/api_client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));

				$api = new FlowmailerAPI($accountId, $apiId, $apiSecret);

				foreach($messages as $message) {
					$result = $api->submitMessage($message);

					if($result['headers']['ResponseCode'] != 201) {
						throw new \Exception(json_encode($result));
					}

					$this->_logger->debug('[Flowmailer] Sending message done ' . var_export($result, true));
				}
			} catch (\Exception $e) {
				$this->_logger->warn('[Flowmailer] Error sending message : ' . $e->getMessage());
				throw new MailException(new Phrase($e->getMessage()), $e);
			}
		} else {
			$this->_logger->debug('[Flowmailer] Module not enabled');
			parent::send($this->_message);
		}
	}

	/**
	* Get message
	*
	* @return string
	*/
	public function getMessage() {
		return $this->_message;
	}
}

