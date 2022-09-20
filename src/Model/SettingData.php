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
    public $shipperName;
    public $shipperAddress;
    public $sellerAddress;

    public function getGrantType()
    {
        return $this->grantType;
    }

    public function setGrantType($grantType)
    {
        $this->grantType = $grantType;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    public function getTariffCode()
    {
        return $this->tariffCode;
    }

    public function setTariffCode($tariffCode)
    {
        $this->tariffCode = $tariffCode;
    }

    public function getFromCity()
    {
        return $this->fromCity;
    }

    public function setFromCity($fromCity)
    {
        $this->fromCity = $fromCity;
    }

    public function setFromAddress($fromAddress)
    {
        $this->fromAddress = $fromAddress;
    }

    public function getPvzCode()
    {
        return $this->pvzCode;
    }

    public function setPvzCode($pvzCode)
    {
        $this->pvzCode = $pvzCode;
    }

    public function getPvzAddress()
    {
        return $this->pvzAddress;
    }

    public function setPvzAddress($pvzAddress)
    {
        $this->pvzAddress = $pvzAddress;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function getSellerName()
    {
        return $this->sellerName;
    }

    public function setSellerName($sellerName)
    {
        $this->sellerName = $sellerName;
    }

    public function getSellerPhone()
    {
        return $this->sellerPhone;
    }

    public function setSellerPhone($sellerPhone)
    {
        $this->sellerPhone = $sellerPhone;
    }

    public function setDeveloperKey($developerKey)
    {
        $this->developerKey = $developerKey;
    }

    public function getShipperName()
    {
        return $this->shipperName;
    }

    public function setShipperName($shipperName)
    {
        $this->shipperName = $shipperName;
    }

    public function getShipperAddress()
    {
        return $this->shipperAddress;
    }

    public function setShipperAddress($shipperAddress)
    {
        $this->shipperAddress = $shipperAddress;
    }

    public function getSellerAddress()
    {
        return $this->sellerAddress;
    }

    public function setSellerAddress($sellerAddress)
    {
        $this->sellerAddress = $sellerAddress;
    }
}
