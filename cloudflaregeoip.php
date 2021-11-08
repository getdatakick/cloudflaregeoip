<?php
/**
 * Copyright (C) 2017-2019 Petr Hucik <petr@getdatakick.com>
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@getdatakick.com so we can send you a copy immediately.
 *
 * @author    Petr Hucik <petr@getdatakick.com>
 * @copyright 2017-2019 Petr Hucik
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class CloudflareGeoIp extends Module
{
    public function __construct()
    {
        $this->name = 'cloudflaregeoip';
        $this->tab = 'back_office_features';
        $this->author = 'datakick';
        $this->version = '1.0.0';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Cloudflare GeoIp');
        $this->description = $this->l('use cloudflare geoip header to choose country and currency');
        $this->controllers = ['login'];
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        return (parent::install() && $this->registerHook('moduleRoutes'));
    }

    /**
     * @throws PrestaShopException
     */
    public function hookModuleRoutes()
    {
        $this->setCountry();

        return [];
    }

    private function setCountry()
    {
        // get cloudflare header
        $countryCode = $this->getCountryCode();
        if (!$countryCode) {
            return;
        }

        // check that we not in back office
        $cookie = $this->context->cookie;
        if (defined('_PS_ADMIN_DIR_') || isset($cookie->id_employee)) {
            return;
        }

        // get currently set country code from cookie
        $currentCountryCode = isset($this->context->cookie->iso_code_country) ? $this->context->cookie->iso_code_country : null;
        if ($currentCountryCode && $currentCountryCode != $countryCode) {
            // if country is already set to different country, don't interfere
            return;
        }

        $countryId = Country::getByIso($countryCode, true);
        if (! $countryId) {
            return;
        }

        $country = new Country($countryId, (int)$cookie->id_lang);
        if (!Validate::isLoadedObject($country)) {
            return;
        }

        // ok, let's set up country
        $this->context->country = $country;
        $cookie->iso_code_country = $countryCode;
        unset($cookie->detect_language);

        // set currency, if not set up yet
        $currentCurrencyId = isset($this->context->cookie->id_currency) ? (int)$this->context->cookie->id_currency : 0;
        if (!$currentCurrencyId) {
            $cookie->id_currency = (int)Currency::getCurrencyInstance($country->id_currency ? (int)$country->id_currency : (int)Configuration::get('PS_CURRENCY_DEFAULT'))->id;
        }
    }

    private function getCountryCode()
    {
        if (isset($_SERVER["HTTP_CF_IPCOUNTRY"])) {
            return $_SERVER["HTTP_CF_IPCOUNTRY"];
        }
        return null;
    }
}
