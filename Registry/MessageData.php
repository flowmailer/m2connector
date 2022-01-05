<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Registry;

class MessageData
{
    /**
     * Template Identifier.
     *
     * @var string
     */
    protected $templateIdentifier;

    /**
     * Template Variables.
     *
     * @var array
     */
    protected $templateVars;

    /**
     * Template Options.
     *
     * @var array
     */
    protected $templateOptions;

    public function __construct()
    {
    }

    /**
     * Set template identifier.
     *
     * @param string $templateIdentifier
     *
     * @return $this
     */
    public function setTemplateIdentifier($templateIdentifier)
    {
        $this->templateIdentifier = $templateIdentifier;

        return $this;
    }

    /**
     * Set template vars.
     *
     * @param array $templateVars
     *
     * @return $this
     */
    public function setTemplateVars($templateVars)
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    /**
     * Set template options.
     *
     * @param array $templateOptions
     *
     * @return $this
     */
    public function setTemplateOptions($templateOptions)
    {
        $this->templateOptions = $templateOptions;

        return $this;
    }

    public function getTemplateVars()
    {
        $data                       = $this->templateVars;
        $data['templateIdentifier'] = $this->templateIdentifier;
        $data['templateOptions']    = $this->templateOptions;

        return $data;
    }

    /**
     * Reset object state.
     *
     * @return $this
     */
    public function reset()
    {
        $this->templateIdentifier = null;
        $this->templateVars       = null;
        $this->templateOptions    = null;

        return $this;
    }
}
