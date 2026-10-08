<?php

namespace EscolaLms\PencilSpaces\Common;

use EscolaLms\PencilSpaces\EscolaLmsPencilSpacesServiceProvider;
use EscolaLms\PencilSpaces\Exceptions\InvalidPencilSpaceConfigurationException;
use Exception;
use Illuminate\Support\Facades\Config;

class PencilSpacesRestClient extends RestClient
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->validConfiguration();
        parent::__construct($this->getApiUrl(), $this->getApiKey());
    }

    private function getApiUrl(): ?string
    {
        return Config::get(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_url');
    }

    private function getApiKey(): ?string
    {
        return Config::get(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_key');
    }

    /**
     * @throws Exception
     */
    private function validConfiguration(): void
    {
        if (!$this->getApiUrl() || !$this->getApiKey()) {
            throw new InvalidPencilSpaceConfigurationException();
        }
    }
}
