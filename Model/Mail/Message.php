<?php

namespace Flowmailer\M2Connector\Model\Mail;

use \Magento\Framework\Mail\MessageInterface;

class Message extends \Magento\Framework\Mail\Message implements MessageInterface
{
    /**
     * @param string $charset
     */
    public function __construct($charset = 'utf-8')
    {
        parent::__construct($charset);
    }

    /**
     * Template Variables
     *
     * @var array
     */
    protected $templateVars;

    /**
     * Set template vars
     *
     * @param array $templateVars
     * @return $this
     */
    public function setTemplateVars($templateVars)
    {
        $this->templateVars = $templateVars;
        return $this;
    }

    /**
     * Get template vars
     *
     * @return array
     */
    public function getTemplateVars()
    {
        return $this->templateVars;
    }
}

