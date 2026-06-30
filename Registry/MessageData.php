<?php

declare(strict_types=1);

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Registry;

final class MessageData
{
    /**
     * Template Identifier.
     */
    private ?string $templateIdentifier = null;

    /**
     * Template Variables.
     *
     * @var array<string, mixed>|null
     */
    private ?array $templateVars = null;

    /**
     * Template Options.
     *
     * @var array<string, mixed>|null
     */
    private ?array $templateOptions = null;

    public function setTemplateIdentifier(?string $templateIdentifier): self
    {
        $this->templateIdentifier = $templateIdentifier;

        return $this;
    }

    /**
     * @param array<string, mixed>|null $templateVars
     */
    public function setTemplateVars(?array $templateVars): self
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    /**
     * @param array<string, mixed>|null $templateOptions
     */
    public function setTemplateOptions(?array $templateOptions): self
    {
        $this->templateOptions = $templateOptions;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateVars(): array
    {
        $data                       = $this->templateVars ?? [];
        $data['templateIdentifier'] = $this->templateIdentifier;
        $data['templateOptions']    = $this->templateOptions;

        return $data;
    }

    /**
     * Reset object state.
     */
    public function reset(): self
    {
        $this->templateIdentifier = null;
        $this->templateVars       = null;
        $this->templateOptions    = null;

        return $this;
    }
}
