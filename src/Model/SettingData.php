<?php

namespace Cdek\Model;

class SettingData
{
    public $mode;
    public $grantType;
    public $clientId;
    public $clientSecret;
    public $tariffCode;
    public $sellerName;
    public $sellerPhone;
    public $fromCity;
    public $fromAddress;
    public $pvzCode;
    public $pvzAddress;
    public $developerKey;


    /**
     * @return mixed
     */
    public function getGrantType()
    {
        return $this->grantType;
    }

    /**
     * @param mixed $grantType
     */
    public function setGrantType($grantType): void
    {
        $this->grantType = $grantType;
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param mixed $clientId
     */
    public function setClientId($clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return mixed
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * @param mixed $clientSecret
     */
    public function setClientSecret($clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return mixed
     */
    public function getTariffCode()
    {
        return $this->tariffCode;
    }

    /**
     * @param mixed $tariffCode
     */
    public function setTariffCode($tariffCode): void
    {
        $this->tariffCode = $tariffCode;
    }

    /**
     * @return mixed
     */
    public function getFromCity()
    {
        return $this->fromCity;
    }

    /**
     * @param mixed $fromCity
     */
    public function setFromCity($fromCity): void
    {
        $this->fromCity = $fromCity;
    }

    /**
     * @param mixed $fromAddress
     */
    public function setFromAddress($fromAddress): void
    {
        $this->fromAddress = $fromAddress;
    }

    /**
     * @return mixed
     */
    public function getPvzCode()
    {
        return $this->pvzCode;
    }

    /**
     * @param mixed $pvzCode
     */
    public function setPvzCode($pvzCode): void
    {
        $this->pvzCode = $pvzCode;
    }

    /**
     * @return mixed
     */
    public function getPvzAddress()
    {
        return $this->pvzAddress;
    }

    /**
     * @param mixed $pvzAddress
     */
    public function setPvzAddress($pvzAddress): void
    {
        $this->pvzAddress = $pvzAddress;
    }

    /**
     * @return mixed
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param mixed $mode
     */
    public function setMode($mode): void
    {
        $this->mode = $mode;
    }

    /**
     * @return mixed
     */
    public function getSellerName()
    {
        return $this->sellerName;
    }

    /**
     * @param mixed $sellerName
     */
    public function setSellerName($sellerName): void
    {
        $this->sellerName = $sellerName;
    }

    /**
     * @return mixed
     */
    public function getSellerPhone()
    {
        return $this->sellerPhone;
    }

    /**
     * @param mixed $sellerPhone
     */
    public function setSellerPhone($sellerPhone): void
    {
        $this->sellerPhone = $sellerPhone;
    }

    /**
     * @param mixed $developerKey
     */
    public function setDeveloperKey($developerKey): void
    {
        $this->developerKey = $developerKey;
    }
}
