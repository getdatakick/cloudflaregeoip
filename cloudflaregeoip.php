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
    const SET_COUNTRY_CODE_TO_CONTEXT = 'CFGIP_SET_COUNTRY_CODE_TO_CONTEXT';
    const ENABLE_GEOIP_SERVICE = 'CFGIP_ENABLE_GEOIP_SERVICE';
    const SUBMIT_NAME = 'SAVE_SETTINGS';

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'cloudflaregeoip';
        $this->tab = 'back_office_features';
        $this->author = 'datakick';
        $this->version = '1.1.0';
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
        return (
            parent::install() &&
            $this->initConfig()
        );
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        if (Tools::isSubmit(static::SUBMIT_NAME)) {
            $this->setSetCountrycodeToContext(Tools::getValue(static::SET_COUNTRY_CODE_TO_CONTEXT));
            $this->setEnableGeoIpService(Tools::getValue(static::ENABLE_GEOIP_SERVICE));
        }

        $countryCode = $this->getCountryCode();
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitNocaptcharecaptchaModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                static::SET_COUNTRY_CODE_TO_CONTEXT => $this->setCountryCodeToContext(),
                static::ENABLE_GEOIP_SERVICE => $this->enableGeoIpService(),
            ],
            'languages'    => $this->getHelperLanguages(),
            'id_language'  => $this->context->language->id,
        ];
        $geoUrl = Context::getContext()->link->getAdminLink('AdminGeolocation');

        $settingsForm = [
            'legend' => [
                'title' => $this->l('Settings'),
                'icon'  => 'icon-cogs',
            ],
            'success' => null,
            'warning' => null,
            'input'  => [
                [
                    'type'     => 'switch',
                    'label'    => $this->l('Set visitor country'),
                    'name'     => static::SET_COUNTRY_CODE_TO_CONTEXT,
                    'required' => false,
                    'class'    => 't',
                    'is_bool'  => true,
                    'values'   => [
                        [
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                    'desc'     => $this->l('When enabled, your visitors country will be assigned on their first visit'),
                ],
                [
                    'type'     => 'switch',
                    'label'    => $this->l('Enable Geo-IP service'),
                    'name'     => static::ENABLE_GEOIP_SERVICE,
                    'required' => false,
                    'class'    => 't',
                    'is_bool'  => true,
                    'values'   => [
                        [
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                    'desc'     => Translate::ppTags(
                        $this->l('When enabled, this module will provide GeoIP information to [1]Geolocation service[/1]'),
                        ['<a href="'.$geoUrl.'" target="_blank">']
                    )
                ],
            ],
            'submit'  => [
                'name'  => static::SUBMIT_NAME,
                'title' => $this->l('Save settings'),
            ],
        ];

        if ($countryCode) {
            $settingsForm['success'] = sprintf($this->l('Cloudflare header found in request. Your country: %s'), $countryCode);
        } else {
            $settingsForm['warning'] = $this->l('Cloudflare header not found in request. Please configure your cloudflare to provide geolocation information');
        }

        return $helper->generateForm([
            [ 'form' => $settingsForm ]
        ]);
    }

    /**
     * @throws PrestaShopException
     */
    public function hookModuleRoutes()
    {
        $this->setCountry();

        return [];
    }

    /**
     * Returns iso code for IP address
     *
     * @param array $params
     *
     * @return string | null
     */
    public function hookActionGeoLocation($params)
    {
        return $this->getCountryCode();
    }

    /**
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
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

    /**
     * @return mixed|null
     */
    private function getCountryCode()
    {
        if (isset($_SERVER["HTTP_CF_IPCOUNTRY"])) {
            return $_SERVER["HTTP_CF_IPCOUNTRY"];
        }
        return null;
    }

    /**
     * @return array
     * @throws PrestaShopException
     */
    protected function getHelperLanguages()
    {
        /** @var AdminController $controller */
        $controller = $this->context->controller;
        return $controller->getLanguages();
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    protected function setCountryCodeToContext()
    {
        return (bool)Configuration::getGlobalValue(static::SET_COUNTRY_CODE_TO_CONTEXT);
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    protected function enableGeoIpService()
    {
        return (bool)Configuration::getGlobalValue(static::ENABLE_GEOIP_SERVICE);
    }

    /**
     * @param int $value
     *
     * @throws PrestaShopException
     */
    protected function setSetCountrycodeToContext($value)
    {
        $value = (int)$value;
        Configuration::updateGlobalValue(static::SET_COUNTRY_CODE_TO_CONTEXT, $value);
        if ($value) {
            $this->registerHook('moduleRoutes');
        } else {
            $this->unregisterHook('moduleRoutes');
        }
    }

    /**
     * @param int $value
     *
     * @throws PrestaShopException
     */
    protected function setEnableGeoIpService($value)
    {
        $value = (int)$value;
        Configuration::updateGlobalValue(static::ENABLE_GEOIP_SERVICE, $value);
        if ($value) {
            $this->registerHook('actionGeoLocation');
        } else {
            $this->unregisterHook('actionGeoLocation');
        }
    }

    /**
     * @return true
     * @throws PrestaShopException
     */
    protected function initConfig()
    {
        $value = $this->getCountryCode() ? 1 : 0;
        $this->setSetCountrycodeToContext($value);
        $this->setEnableGeoIpService($value);
        return true;
    }

}
