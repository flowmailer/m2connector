<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

declare(strict_types=1);

namespace Vendic\FlowmailerM2Connector\Registry;

final class MessageData
{
    private string|null $templateIdentifier = null;
    
    private array|null $templateVars = null;

    private array|null $templateOptions = null;

    public function setTemplateIdentifier($templateIdentifier): self
    {
        $this->templateIdentifier = $templateIdentifier;

        return $this;
    }

    public function setTemplateVars(?array $templateVars): self
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    public function setTemplateOptions(?array $templateOptions): self
    {
        $this->templateOptions = $templateOptions;

        return $this;
    }

    public function getTemplateVars(): array
    {
        $data = $this->templateVars;
        $data['templateIdentifier'] = $this->templateIdentifier;
        $data['templateOptions'] = $this->templateOptions;

        return $data;
    }

    /**
     * Reset object state.
     */
    public function reset(): self
    {
        $this->templateIdentifier = null;
        $this->templateVars = null;
        $this->templateOptions = null;

        return $this;
    }
}
