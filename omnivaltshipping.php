<?php
if (!defined('_PS_VERSION_'))
  exit;

class OmnivaltShipping extends CarrierModule
{
  private $_html = '';
  private $_postErrors = array();

  protected $_hooks = array(
    'actionCarrierUpdate', //For control change of the carrier's ID (id_carrier), the module must use the updateCarrier hook.
    'displayAdminOrderContentShip',
    //'extraCarrier',
    'displayBeforeCarrier',
    'header',
    'actionCarrierProcess',
    'orderDetailDisplayed',
    'displayAdminOrder',
    'displayBackOfficeHeader',
  );

  private static $_classMap = array(
    'OmnivaPatcher' => 'omnivapatcher.php',
    'OrderInfo' => 'classes/OrderInfo.php',
  );

  private static $_carriers = array(
    //"Public carrier name" => "technical name",
    'Parcel terminal' => 'omnivalt_pt',
    'Courier' => 'omnivalt_c',
  );

  private $texts = array();

  public function __construct()
  {
    $this->name = 'omnivaltshipping';
    $this->tab = 'shipping_logistics';
    $this->version = '1.1.8';
    $this->author = 'Omniva.lt';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '1.8');
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Omniva Shipping');
    $this->description = $this->l('Shipping module for Omniva carrier');

    $this->texts = array(
      $this->l('Sender address'),
      $this->l('No.'),
      $this->l('Shipment number'),
      $this->l('Date'),
      $this->l('Amount'),
      $this->l('Weight (kg)'),
      $this->l('Recipient address'),
      $this->l('Courier name, surname, signature'),
      $this->l('Sender name, surname, signature'),
    );

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    if (!Configuration::get('omnivalt_api_url'))
      $this->warning = $this->l('Please set up module');
    if (!Configuration::get('omnivalt_locations_update') || (Configuration::get('omnivalt_locations_update') + 24 * 3600) < time() || !file_exists(dirname(__file__) . "/locations.json")) {
      $url = 'https://www.omniva.ee/locations.json';
      $fp = fopen(dirname(__file__) . "/locations.json", "w");
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_HEADER, false);
      curl_setopt($curl, CURLOPT_FILE, $fp);
      curl_setopt($curl, CURLOPT_TIMEOUT, 60);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      $data = curl_exec($curl);
      curl_close($curl);
      fclose($fp);
      if ($data !== false) {
        Configuration::updateValue('omnivalt_locations_update', time());
      }
    }
  }

  public function getCustomOrderState()
  {
    $omnivalt_order_state = (int) Configuration::get('omnivalt_order_state');
    $order_status = new OrderState((int) $omnivalt_order_state, (int) $this->context->language->id);
    if (!$order_status->id || !$omnivalt_order_state) {
      $orderState = new OrderState();
      $orderState->name = array();
      foreach (Language::getLanguages() as $language) {
        if (strtolower($language['iso_code']) == 'lt')
          $orderState->name[$language['id_lang']] = 'Paruošta siųsti su Omniva';
        else
          $orderState->name[$language['id_lang']] = 'Shipment ready for Omniva';
      }
      $orderState->send_email = false;
      $orderState->color = '#DDEEFF';
      $orderState->hidden = false;
      $orderState->delivery = false;
      $orderState->logable = true;
      $orderState->invoice = false;
      $orderState->unremovable = false;
      if ($orderState->add()) {
        Configuration::updateValue('omnivalt_order_state', $orderState->id);
        return $orderState->id;
      }
    }
    return $omnivalt_order_state;
  }

  public function getErrorOrderState()
  {
    $omnivalt_order_state = (int) Configuration::get('omnivalt_error_state');
    $order_status = new OrderState((int) $omnivalt_order_state, (int) $this->context->language->id);
    if (!$order_status->id || !$omnivalt_order_state) {
      $orderState = new OrderState();
      $orderState->name = array();
      foreach (Language::getLanguages() as $language) {
        if (strtolower($language['iso_code']) == 'lt')
          $orderState->name[$language['id_lang']] = 'Omnivos siuntos klaida';
        else
          $orderState->name[$language['id_lang']] = 'Error with Omniva parcel';
      }
      $orderState->send_email = false;
      $orderState->color = '#F22323';
      $orderState->hidden = false;
      $orderState->delivery = false;
      $orderState->logable = true;
      $orderState->invoice = true;
      $orderState->unremovable = false;
      if ($orderState->add()) {
        Configuration::updateValue('omnivalt_error_state', $orderState->id);
        return $orderState->id;
      }
    }
    return $omnivalt_order_state;
  }

  public function install()
  {
    if (parent::install()) {
      foreach ($this->_hooks as $hook) {
        if (!$this->registerHook($hook)) {
          return FALSE;
        }
      }
      $name = $this->l('Omniva orders');
      $controllerName = 'AdminOmnivaOrders';
      $tab_admin_order_id = Tab::getIdFromClassName('AdminParentShipping') ? Tab::getIdFromClassName('AdminParentShipping') : Tab::getIdFromClassName('Shipping');
      $tab = new Tab();
      $tab->class_name = $controllerName;
      $tab->id_parent = $tab_admin_order_id;
      $tab->module = $this->name;
      $languages = Language::getLanguages(false);
      foreach ($languages as $lang) {
        $tab->name[$lang['id_lang']] = $name;
      }
      $tab->save();
      Configuration::updateValue('omnivalt_manifest', 1);
      //add new fields
      $new_fields = 'ALTER TABLE `' . _DB_PREFIX_ . 'cart` ADD omnivalt_terminal VARCHAR(10) default NULL';
      DB::getInstance()->Execute($new_fields);
      $new_fields2 = 'ALTER TABLE `' . _DB_PREFIX_ . 'cart` ADD omnivalt_manifest VARCHAR(10) default NULL';
      DB::getInstance()->Execute($new_fields2);
      $new_table = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'omnivalt_order_info` (
          `order_id` int(11) NOT NULL,
          `packs` int(10) unsigned NOT NULL,
          `weight` double(10,2) unsigned NOT NULL,
          `is_cod` tinyint(1) NOT NULL,
          `cod_amount` decimal(10,2) NOT NULL,
          `error` VARCHAR(200) default NULL,
          PRIMARY KEY (`order_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
      DB::getInstance()->Execute($new_table);
      //$new_fields = 'ALTER TABLE `'._DB_PREFIX_ .'omnivalt_order_info` ADD error VARCHAR(200) default NULL';
      //DB::getInstance()->Execute($new_fields );
      if (!$this->createCarriers()) { //function for creating new currier
        return FALSE;
      }
      //install of custom state
      $this->getCustomOrderState();
      $this->getErrorOrderState();
      return TRUE;
    }

    return FALSE;
  }

  protected function createCarriers()
  {
    foreach (self::$_carriers as $key => $value) {
      //Create new carrier
      $carrier = new Carrier();
      $carrier->name = $key;
      $carrier->active = TRUE;
      $carrier->deleted = 0;
      $carrier->shipping_handling = TRUE;
      $carrier->range_behavior = 0;
      $carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = '1-2 business days';
      $carrier->shipping_external = TRUE;
      $carrier->is_module = TRUE;
      $carrier->external_module_name = $this->name;
      $carrier->need_range = TRUE;
      $carrier->limited_countries = array('lt');
      $carrier->url = "https://www.omniva.lt/verslo/siuntos_sekimas?barcode=@";

      if ($carrier->add()) {
        $groups = Group::getGroups(true);
        foreach ($groups as $group) {
          Db::getInstance()->insert('carrier_group', array(
            'id_carrier' => (int) $carrier->id,
            'id_group' => (int) $group['id_group']
          ));
        }

        $rangePrice = new RangePrice();
        $rangePrice->id_carrier = $carrier->id;
        $rangePrice->delimiter1 = '0';
        $rangePrice->delimiter2 = '1000';
        $rangePrice->add();

        $rangeWeight = new RangeWeight();
        $rangeWeight->id_carrier = $carrier->id;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '1000';
        $rangeWeight->add();

        $zones = Zone::getZones(true);
        foreach ($zones as $z) {
          Db::getInstance()->insert(
            'carrier_zone',
            array('id_carrier' => (int) $carrier->id, 'id_zone' => (int) $z['id_zone'])
          );
          Db::getInstance()->insert(
            'delivery',
            array('id_carrier' => $carrier->id, 'id_range_price' => (int) $rangePrice->id, 'id_range_weight' => NULL, 'id_zone' => (int) $z['id_zone'], 'price' => '0'),
            true
          );
          Db::getInstance()->insert(
            'delivery',
            array('id_carrier' => $carrier->id, 'id_range_price' => NULL, 'id_range_weight' => (int) $rangeWeight->id, 'id_zone' => (int) $z['id_zone'], 'price' => '0'),
            true
          );
        }

        copy(dirname(__FILE__) . '/views/img/omnivalt-logo.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg'); //assign carrier logo

        Configuration::updateValue($value, $carrier->id);
        Configuration::updateValue($value . '_reference', $carrier->id);
      }
    }

    return TRUE;
  }

  protected function deleteCarriers()
  {
    foreach (self::$_carriers as $value) {
      $tmp_carrier_id = Configuration::get($value);
      $carrier = new Carrier($tmp_carrier_id);
      $carrier->delete();
    }

    return TRUE;
  }

  public function uninstall()
  {
    if (parent::uninstall()) {
      /*
      $omnivalt_order_state = (int)Configuration::get('omnivalt_order_state');
      $order_status = new OrderState((int)$omnivalt_order_state, (int)$this->context->language->id);
      if ($order_status){
        $order_status->deleted = true;
        $order_status->save();
      }
      */
      $tab_controller_main_id = TabCore::getIdFromClassName('AdminOmnivaOrders');
      $tab_controller_main = new Tab($tab_controller_main_id);
      $tab_controller_main->delete();
      foreach ($this->_hooks as $hook) {
        if (!$this->unregisterHook($hook)) {
          return FALSE;
        }
      }
      //delete new fields
      $new_fields = 'ALTER TABLE `' . _DB_PREFIX_ . 'cart` DROP omnivalt_terminal';
      DB::getInstance()->Execute($new_fields);
      $new_fields2 = 'ALTER TABLE `' . _DB_PREFIX_ . 'cart` DROP omnivalt_manifest';
      DB::getInstance()->Execute($new_fields2);
      if (!$this->deleteCarriers()) {
        return FALSE;
      }

      return TRUE;
    }

    return FALSE;
  }

  public function getOrderShippingCost($params, $shipping_cost)
  {
    //if ($params->id_carrier == (int)(Configuration::get('omnivalt_pt')) || $params->id_carrier == (int)(Configuration::get('omnivalt_c')))
    return $shipping_cost;
    return false; // carrier is not known
  }

  public function getOrderShippingCostExternal($params)
  {
    return $this->getOrderShippingCost($params, 0);
  }

  public function hookUpdateCarrier($params)
  {
    $id_carrier_old = (int) ($params['id_carrier']);
    $id_carrier_new = (int) ($params['carrier']->id);
    
    foreach (self::$_carriers as $value) {
      if ($id_carrier_old == (int) (Configuration::get($value)))
        Configuration::updateValue($value, $id_carrier_new);
    }
  }

  /*
  ** Form Config Methods
  **
  */
  public function getContent()
  {
    
    if (Tools::isSubmit('patch' . $this->name)) {
      self::checkForClass('OmnivaPatcher');

      $patcher = new OmnivaPatcher();
      $this->runPatcher($patcher);
    }

    $output = null;

    if (Tools::isSubmit('submit' . $this->name)) {
      $fields = array('omnivalt_map', 'omnivalt_api_url', 'omnivalt_api_user', 'omnivalt_api_pass', 'omnivalt_send_off', 'omnivalt_bank_account', 'omnivalt_company', 'omnivalt_address', 'omnivalt_city', 'omnivalt_postcode', 'omnivalt_countrycode', 'omnivalt_phone', 'omnivalt_pick_up_time_start', 'omnivalt_pick_up_time_finish', 'omnivalt_print_type', 'omnivalt_manifest_lang');
      $not_required = array('omnivalt_bank_account');
      $values = array();
      $all_filled = true;
      foreach ($fields as $field) {
        $values[$field] = strval(Tools::getValue($field));
        if ($values[$field] == '' && !in_array($field, $not_required)) {
          $all_filled = false;
        }
      }

      if (!$all_filled)
        $output .= $this->displayError($this->l('All fields required'));
      else {
        foreach ($values as $key => $val)
          Configuration::updateValue($key, $val);
        $output .= $this->displayConfirmation($this->l('Settings updated'));
      }
    }
    return $output . $this->displayForm();
  }

  public function cod_options()
  {
    return array(
      array('id_option' => '0', 'name' => $this->l('No')),
      array('id_option' => '1', 'name' => $this->l('Yes')),
    );
  }

  public function displayForm()
  {
    $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    $lang_options = array(
      array(
        'id_option' => 'en',
        'name' => $this->l('English')
      ),
      array(
        'id_option' => 'ee',
        'name' => $this->l('Estonian') . ' (' . $this->l('English') . ')'
      ),
      array(
        'id_option' => 'lv',
        'name' => $this->l('Latvian')
      ),
      array(
        'id_option' => 'lt',
        'name' => $this->l('Lithuanian')
      ),
    );
    $options = array(
      array(
        'id_option' => 'pt',
        'name' => $this->l('Parcel terminal')
      ),
      array(
        'id_option' => 'c',
        'name' => $this->l('Courier')
      ),
    );
    $print_options = array(
      array(
        'id_option' => 'single',
        'name' => $this->l('Original (single label)')
      ),
      array(
        'id_option' => 'four',
        'name' => $this->l('A4 (4 labels)')
      ),
    );

    // Init Fields form array
    $fields_form[0]['form'] = array(
      'legend' => array(
        'title' => $this->l('Settings'),
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Api URL'),
          'name' => 'omnivalt_api_url',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Api login user'),
          'name' => 'omnivalt_api_user',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Api login password'),
          'name' => 'omnivalt_api_pass',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Company name'),
          'name' => 'omnivalt_company',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Bank account'),
          'name' => 'omnivalt_bank_account',
          'size' => 20,
          'required' => false
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Company address'),
          'name' => 'omnivalt_address',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Company city'),
          'name' => 'omnivalt_city',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Company postcode'),
          'name' => 'omnivalt_postcode',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Company country code'),
          'name' => 'omnivalt_countrycode',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Company phone number'),
          'name' => 'omnivalt_phone',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Pick up time start'),
          'name' => 'omnivalt_pick_up_time_start',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Pick up time finish'),
          'name' => 'omnivalt_pick_up_time_finish',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'select',
          'lang' => true,
          'label' => $this->l('Send off type'),
          'name' => 'omnivalt_send_off',
          'desc' => $this->l('Please select send off from store type'),
          'required' => true,
          'options' => array(
            'query' => $options,
            'id' => 'id_option',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'switch',
          'label' => $this->l('Display map'),
          'name' => 'omnivalt_map',
          'is_bool' => true,
          'values' => array(
            array(
              'id' => 'label2_on',
              'value' => 1,
              'label' => $this->l('Enabled')
            ),
            array(
              'id' => 'label2_off',
              'value' => 0,
              'label' => $this->l('Disabled')
            )
          )
        ),
        array(
          'type' => 'select',
          'lang' => true,
          'label' => $this->l('Labels print type'),
          'name' => 'omnivalt_print_type',
          'required' => false,
          'options' => array(
            'query' => $print_options,
            'id' => 'id_option',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'select',
          'lang' => true,
          'label' => $this->l('Manifest language'),
          'name' => 'omnivalt_manifest_lang',
          'required' => false,
          'options' => array(
            'query' => $lang_options,
            'id' => 'id_option',
            'name' => 'name'
          )
        ),
      ),
      'submit' => array(
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      )
    );

    self::checkForClass('OmnivaPatcher');

    $patcher = new OmnivaPatcher();

    $installed_patches = $patcher->getInstalledPatches();
    $latest_patch = 'OmnivaPatcher Installed';
    if ($installed_patches) {
      $latest_patch = $installed_patches[count($installed_patches) - 1];
    }

    $patch_link = AdminController::$currentIndex . '&configure=' . $this->name . '&patch' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');

    $fields_form[0]['form']['input'][] = array(
      'type' => 'html',
      'label' => 'Patch:',
      'name' => 'patcher_info',
      'html_content' => '<label class="control-label"><b>' . $latest_patch . '</b></label><br><a class="btn btn-default" href="' . $patch_link . '">Check & Install Patches</a>',
    );

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

    // Language
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit' . $this->name;
    $helper->toolbar_btn = array(
      'save' =>
      array(
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
          '&token=' . Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Back to list')
      )
    );

    // Load current value
    $helper->fields_value['omnivalt_api_url'] = Configuration::get('omnivalt_api_url');
    if ($helper->fields_value['omnivalt_api_url'] == "") {
      $helper->fields_value['omnivalt_api_url'] = "https://edixml.post.ee";
    }
    $helper->fields_value['omnivalt_api_user'] = Configuration::get('omnivalt_api_user');
    $helper->fields_value['omnivalt_api_pass'] = Configuration::get('omnivalt_api_pass');
    $helper->fields_value['omnivalt_send_off'] = Configuration::get('omnivalt_send_off');
    $helper->fields_value['omnivalt_company'] = Configuration::get('omnivalt_company');
    $helper->fields_value['omnivalt_address'] = Configuration::get('omnivalt_address');
    $helper->fields_value['omnivalt_city'] = Configuration::get('omnivalt_city');
    $helper->fields_value['omnivalt_postcode'] = Configuration::get('omnivalt_postcode');
    $helper->fields_value['omnivalt_countrycode'] = Configuration::get('omnivalt_countrycode');
    $helper->fields_value['omnivalt_phone'] = Configuration::get('omnivalt_phone');
    $helper->fields_value['omnivalt_bank_account'] = Configuration::get('omnivalt_bank_account');
    $helper->fields_value['omnivalt_pick_up_time_start'] = Configuration::get('omnivalt_pick_up_time_start') ? Configuration::get('omnivalt_pick_up_time_start') : '8:00';
    $helper->fields_value['omnivalt_pick_up_time_finish'] = Configuration::get('omnivalt_pick_up_time_finish') ? Configuration::get('omnivalt_pick_up_time_finish') : '17:00';
    $helper->fields_value['omnivalt_map'] = Configuration::get('omnivalt_map');
    $helper->fields_value['omnivalt_print_type'] = Configuration::get('omnivalt_print_type') ? Configuration::get('omnivalt_print_type') : 'four';
    $helper->fields_value['omnivalt_manifest_lang'] = Configuration::get('omnivalt_manifest_lang') ? Configuration::get('omnivalt_manifest_lang') : 'en';
    return $helper->generateForm($fields_form);
  }
  
  private function runPatcher(OmnivaPatcher $patcherInstance)
  {
    $last_check = Configuration::get('omnivalt_patcher_update');

    $patcherInstance->startUpdate(Configuration::get('omnivalt_api_user'), Configuration::get('PS_SHOP_EMAIL'));

    Configuration::updateValue('omnivalt_patcher_update', time());
  }

  private function getTerminalsOptions($selected = '', $country = "")
  {
    if (!$country) {
      $shop_country = new Country();
      $country = $shop_country->getIsoById(Configuration::get('PS_SHOP_COUNTRY_ID'));
    }

    $terminals_json_file_dir = dirname(__file__) . "/locations.json";
    $terminals_file = fopen($terminals_json_file_dir, "r");
    $terminals = fread($terminals_file, filesize($terminals_json_file_dir) + 10);
    fclose($terminals_file);
    $terminals = json_decode($terminals, true);
    $parcel_terminals = '';
    if (is_array($terminals)) {
      $grouped_options = array();
      foreach ($terminals as $terminal) {
        # closed ? exists on EE only
        if (intval($terminal['TYPE'])) {
          continue;
        }
        if ($terminal['A0_NAME'] != $country && in_array($country, array("LT", "EE", "LV")))
          continue;
        if (!isset($grouped_options[$terminal['A1_NAME']]))
          $grouped_options[(string) $terminal['A1_NAME']] = array();
        //$grouped_options[(string)$terminal['A1_NAME']][(string)$terminal['ZIP']] = $terminal['NAME'];
        $address = trim($terminal['A2_NAME'] . ' ' . ($terminal['A5_NAME'] != 'NULL' ? $terminal['A5_NAME'] : '') . ' ' . ($terminal['A7_NAME'] != 'NULL' ? $terminal['A7_NAME'] : ''));
        $grouped_options[(string) $terminal['A1_NAME']][(string) $terminal['ZIP']] = $terminal['NAME'] . ' (' . $address . ')';
      }
      ksort($grouped_options);
      foreach ($grouped_options as $city => $locs) {
        $parcel_terminals .= '<optgroup label = "' . $city . '">';
        foreach ($locs as $key => $loc) {
          $parcel_terminals .= '<option value = "' . $key . '" ' . ($key == $selected ? 'selected' : '') . '  class="omnivaOption">' . $loc . '</option>';
        }
        $parcel_terminals .= '</optgroup>';
      }
    }
    $parcel_terminals = '<option value = "">' . $this->l('Select parcel terminal') . '</option>' . $parcel_terminals;
    return $parcel_terminals;
  }

  /**
   * Generate terminal list with coordinates info
   */
  private function getTerminalForMap($selected = '', $country = "LT")
  {
    if (!$country) {
      $shop_country = new Country();
      $country = $shop_country->getIsoById(Configuration::get('PS_SHOP_COUNTRY_ID'));
    }

    $terminals_json_file_dir = dirname(__file__) . "/locations.json";
    $terminals_file = fopen($terminals_json_file_dir, "r");
    $terminals = fread($terminals_file, filesize($terminals_json_file_dir) + 10);
    fclose($terminals_file);
    $terminals = json_decode($terminals, true);
    $parcel_terminals = '';
    if (is_array($terminals)) {
      $terminalsList = array();
      foreach ($terminals as $terminal) {
        if ($terminal['A0_NAME'] != $country && in_array($country, array("LT", "EE", "LV")) || intval($terminal['TYPE']) == 1)
          continue;
        if (!isset($grouped_options[$terminal['A1_NAME']]))
          $grouped_options[(string) $terminal['A1_NAME']] = array();
        $grouped_options[(string) $terminal['A1_NAME']][(string) $terminal['ZIP']] = $terminal['NAME'];

        $terminalsList[] = [$terminal['NAME'], $terminal['Y_COORDINATE'], $terminal['X_COORDINATE'], $terminal['ZIP'], $terminal['A1_NAME'], $terminal['A2_NAME'], $terminal['comment_lit']];
      }
    }
    return $terminalsList;
  }

  public static function getTranslate($string,$iso_lang='lt',$source='',$js=false)
  {
    $mainClass = new OmnivaltShipping();
    if (empty($source)) $source = $mainClass->name;
    $file = dirname(__FILE__).'/translations/'.$iso_lang.'.php';
    if(!file_exists($file)) return $string;
    include($file);
    $key = md5(str_replace('\'', '\\\'', $string));
    $current_key = strtolower('<{'.$mainClass->name.'}'._THEME_NAME_.'>'.$source).'_'.$key;
    $default_key = strtolower('<{'.$mainClass->name.'}prestashop>'.$source).'_'.$key;
    $ret = $string;
    if (isset($_MODULE[$current_key]))
      $ret = stripslashes($_MODULE[$current_key]);
    elseif (isset($_MODULE[$default_key]))
      $ret = stripslashes($_MODULE[$default_key]);
    if ($js)
      $ret = addslashes($ret);
    return $ret;
  }

  public static function getTerminalAddress($code)
  {
    $terminals_json_file_dir = dirname(__file__) . "/locations.json";
    $terminals_file = fopen($terminals_json_file_dir, "r");
    $terminals = fread($terminals_file, filesize($terminals_json_file_dir) + 10);
    fclose($terminals_file);
    $terminals = json_decode($terminals, true);
    $parcel_terminals = '';
    if (is_array($terminals)) {
      $grouped_options = array();
      foreach ($terminals as $terminal) {
        if ($terminal['ZIP'] == $code) {
          return $terminal['NAME'] . ', ' . $terminal['A2_NAME'] . ', ' . $terminal['A0_NAME'];
        }
      }
    }
    return '';
  }

  private function getCarriersOptions($selected = '')
  {
    $carriers = '';
    //$carriers .= '<option value = "">'.$this->l('Select carrier').'</option>';
    foreach (self::$_carriers as $key => $value) {
      $tmp_carrier_id = Configuration::get($value);
      $carrier = new Carrier($tmp_carrier_id);
      if ($carrier->active || 1 == 1) {
        $carriers .= '<option value = "' . Configuration::get($value) . '" ' . (Configuration::get($value) == $selected ? 'selected' : '') . '>' . $this->l($key) . '</option>';
      }
    }
    return $carriers;
  }

  public function hookDisplayBeforeCarrier($params)
  {
    $selected = '';
    if (isset($params['cookie']->id_cart) && $params['cookie']->id_cart) {
      $cart_sql = "SELECT omnivalt_terminal FROM " . _DB_PREFIX_ . "cart WHERE id_cart = " . $params['cookie']->id_cart . " AND omnivalt_terminal <> '' AND omnivalt_terminal IS NOT NULL";
      $selected = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($cart_sql);
    }
    $sql = 'SELECT a.*, c.iso_code FROM ' . _DB_PREFIX_ . 'address AS a LEFT JOIN ' . _DB_PREFIX_ . 'country AS c ON c.id_country = a.id_country WHERE id_address="' . $params['cart']->id_address_delivery . '"';
    $address = Db::getInstance()->getRow($sql);

    $language = new Language(Configuration::get('PS_LANG_DEFAULT'));
    $address['iso_code'] = (!empty($address['iso_code'])) ? $address['iso_code'] : strtoupper($language->iso_code);
    $address['postcode'] = (isset($address['postcode'])) ? $address['postcode'] : '';

    $showMap = Configuration::get('omnivalt_map');
    $this->context->smarty->assign(array(

      'omnivalt_parcel_terminal_carrier_id' => Configuration::get('omnivalt_pt'),
      'parcel_terminals' => $this->getTerminalsOptions($selected, $address['iso_code']),
      'terminals_list' => $this->getTerminalForMap($selected, $address['iso_code']),
      'omniva_current_country' => $address['iso_code'],
      'omniva_postcode' => $address['postcode'],
      'omniva_map' => $showMap,
      'module_url' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
    ));
    return $this->display(__file__, 'displayBeforeCarrier.tpl');
  }

  public function hookDisplayBackOfficeHeader($params)
  {
    return '
      <script type="text/javascript">
        var omnivalt_bulk_labels = "' . $this->l("Print Omnivalt labels") . '";
        var omnivalt_bulk_manifests = "' . $this->l("Print Omnivalt manifests") . '";
        var omnivalt_admin_action_labels = "' . $this->addHttps($this->context->link->getModuleLink("omnivaltshipping", "omnivaltadminajax", array("action" => "bulklabels"))) . '";
        var omnivalt_admin_action_manifests = "' . $this->addHttps($this->context->link->getModuleLink("omnivaltshipping", "omnivaltadminajax", array("action" => "bulkmanifests"))) . '";
      </script>
      <script type="text/javascript" src="' . (__PS_BASE_URI__) . 'modules/' . $this->name . '/views/js/adminOmnivalt.js"></script>
    ';
  }

  public function returnx()
  {
    return '
      <script type="text/javascript">
        modules/' . $this->name . '/views/js/adminOmnivalt.js
      </script>
    ';
  }

  public function hookHeader($params)
  {
    //var_dump($this->context->language);
    //$this->context->language->iso_code
    if (in_array(Context::getContext()->controller->php_self, array('order-opc', 'order'))) {
      $this->context->controller->registerJavascript(
        'leaflet',
        'modules/' . $this->name . '/views/js/leaflet.js',
        ['priority' => 190]
      );

      $this->context->controller->registerStylesheet(
        'leaflet-style',
        'modules/' . $this->name . '/views/css/leaflet.css',
        [
          'media' => 'all',
          'priority' => 200,
        ]
      );
      $this->context->controller->registerStylesheet(
        'omniva-modulename-style',
        'modules/' . $this->name . '/views/css/omniva.css',
        [
          'media' => 'all',
          'priority' => 200,
        ]
      );

      $this->context->controller->registerJavascript(
        'omnivalt',
        'modules/' . $this->name . '/views/js/omniva.js',
        [
          'priority' => 200,
        ]
      );

      $this->smarty->assign(array(
        'omnivalt_parcel_terminal_carrier_id' => Configuration::get('omnivalt_pt'),
        'module_url' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
      ));

      return $this->display(__FILE__, 'header.tpl');
    }
  }

  public function hookActionCarrierProcess($params)
  {
    if (isset($_POST['omnivalt_parcel_terminal']) && $params['cart']->id_carrier == Configuration::get('omnivalt_pt')) {
      $terminal_id = $_POST['omnivalt_parcel_terminal'];
      $params['cart']->omnivalt_terminal = $terminal_id;
    }
  }

  public static function getCarrierIds($carriers = [])
  {
    // use only supplied or all
    $carriers = count($carriers) > 0 ? $carriers : self::$_carriers;
    $ref = [];
    foreach ($carriers as $value) {
      $ref[] = Configuration::get($value . '_reference');
    }
    $data = [];
    if ($ref) {
      $sql = 'SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier WHERE id_reference IN(' . implode(',', $ref) . ')';
      $result = Db::getInstance()->executeS($sql);
      foreach ($result as $value) {
        $data[] = (int) $value['id_carrier'];
      }
      sort($data);
    }
    return $data;
  }

  protected static function cod($order, $cod = 0, $amount = 0)
  {
    $company = Configuration::get('omnivalt_company');
    $bank_account = Configuration::get('omnivalt_bank_account');
    if ($cod && empty($bank_account)) die('Empty bank account in Omniva module settings');
    
    //if($order->module == 'cashondelivery' && $cod) {
    if ($cod) {
      return '<monetary_values>
        <cod_receiver>' . $company . '</cod_receiver>
        <values code="item_value" amount="' . $amount . '"/>
      </monetary_values>
      <account>' . $bank_account . '</account>
      <reference_number>' . self::getReferenceNumber($order->id) . '</reference_number>';
    } else {
      return '';
    }
  }

  protected static function getReferenceNumber($order_number)
  {
    $order_number = (string) $order_number;
    $kaal = array(7, 3, 1);
    $sl = $st = strlen($order_number);
    $total = 0;
    while ($sl > 0 and substr($order_number, --$sl, 1) >= '0') {
      $total += substr($order_number, ($st - 1) - $sl, 1) * $kaal[($sl % 3)];
    }
    $kontrollnr = ((ceil(($total / 10)) * 10) - $total);
    return $order_number . $kontrollnr;
  }

  public function changeOrderStatus($id_order, $status)
  {
    $order = new Order((int) $id_order);
    if ($order->current_state != $status) // && $order->current_state != Configuration::get('PS_OS_SHIPPING'))
    {
      $history = new OrderHistory();
      $history->id_order = (int) $id_order;
      $history->id_employee = (int) $this->context->employee->id;
      $history->changeIdOrderState((int) $status, $order);
      $history->add();
      //$history->addWithemail(true); // broken in 1.7.6
    }
  }

  public function hookDisplayAdminOrder($id_order)
  {
    $order = new Order((int) $id_order['id_order']);
    $cart = new Cart((int) $order->id_cart);
    $return = '';
    if ($order->id_carrier == Configuration::get('omnivalt_pt') || $order->id_carrier == Configuration::get('omnivalt_c')) {
      $terminal_id = $cart->omnivalt_terminal;
      $label_url = '';

      $sql = 'SELECT a.*, c.iso_code FROM ' . _DB_PREFIX_ . 'address AS a LEFT JOIN ' . _DB_PREFIX_ . 'country AS c ON c.id_country = a.id_country WHERE id_address="' . $cart->id_address_delivery . '"';
      $address = Db::getInstance()->getRow($sql);
      $countryCode = $address['iso_code'];

      self::checkForClass('OrderInfo');
      $OrderInfo = new OrderInfo();
      $OrderInfo = $OrderInfo->getOrderInfo($order->id);
      $label_url = $this->context->link->getModuleLink("omnivaltshipping", "omnivaltadminajax", array("action"=>"bulklabels", "order_ids"=>$order->id));
      
      $error_msg = !empty($OrderInfo['error']) ? $OrderInfo['error'] : false;
      $omniva_tpl = 'blockinorder.tpl';

      if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
        $omniva_tpl = 'blockinorder_1_7_7.tpl';
        $error_msg = $error_msg ? $this->displayError($error_msg) : false;
      }

      $this->smarty->assign(array(
        'total_weight' => isset($OrderInfo['weight']) ? $OrderInfo['weight'] : $order->getTotalWeight(),
        'packs' => isset($OrderInfo['packs']) ? $OrderInfo['packs'] : 1,
        'total_paid_tax_incl' => isset($OrderInfo['cod_amount']) ? $OrderInfo['cod_amount'] : $order->total_paid_tax_incl,
        'is_cod' => isset($OrderInfo['is_cod']) ? $OrderInfo['is_cod'] : (strpos($order->module, 'cashondelivery') !== false), //($order->module == 'cashondeliveryplus' OR $order->module == 'cashondelivery'),
        'parcel_terminals' => $this->getTerminalsOptions($terminal_id, $countryCode),
        'carriers' => $this->getCarriersOptions($cart->id_carrier),
        'order_id' => (int) $id_order['id_order'],
        'moduleurl' => $this->addHttps($this->context->link->getModuleLink('omnivaltshipping', 'omnivaltadminajax', array('action' => 'saveorderinfo'))),
        'printlabelsurl' => $this->addHttps($this->context->link->getModuleLink('omnivaltshipping', 'omnivaltadminajax', array('action' => 'printlabels'))),
        'omnivalt_parcel_terminal_carrier_id' => Configuration::get('omnivalt_pt'),
        'label_url' => $label_url,
        'error' => $error_msg,
      ));

      $form = $this->display(__FILE__, $omniva_tpl);

      return $form;
    }
  }

  private function addHttps($url)
  {
    if (empty($_SERVER['HTTPS'])) {
      return $url;
    } elseif ($_SERVER['HTTPS'] == "on") {
      return str_replace('http://', 'https://', $url);
    } else {
      return $url;
    }
  }

  private static function getMethod($order_carrier_id = false)
  {
    if (!$order_carrier_id)
      return '';
    $terminals = self::getCarrierIds(['omnivalt_pt']);
    $couriers = self::getCarrierIds(['omnivalt_c']);
    if (in_array((int) $order_carrier_id, $terminals, true))
      return 'pt';
    if (in_array((int) $order_carrier_id, $couriers, true))
      return 'c';
    return '';
  }

  public static function get_tracking_number($id_order, $onload = false)
  {
    self::checkForClass('OrderInfo');
    $orderInfo = new OrderInfo();
    $orderInfo = $orderInfo->getOrderInfo($id_order);
    $order = new Order($id_order);
    $cart = new Cart((int) $order->id_cart);
    $terminal_id = $cart->omnivalt_terminal;
    $sql = 'SELECT a.*, c.iso_code FROM ' . _DB_PREFIX_ . 'address AS a LEFT JOIN ' . _DB_PREFIX_ . 'country AS c ON c.id_country = a.id_country WHERE id_address="' . $order->id_address_delivery . '"';
    $address = Db::getInstance()->getRow($sql);
    //return $sql;
    //return $params['cart']->id_address_delivery;
    $send_method = self::getMethod($order->id_carrier);
    $pickup_method = Configuration::get('omnivalt_send_off');
    $service = "";
    switch ($pickup_method . ' ' . $send_method) {
      case 'c pt':
        $service = "PU";
        break;
      case 'c c':
        $service = "QH";
        break;
      case 'pt c':
        $service = "PK";
        break;
      case 'pt pt':
        $service = "PA";
        break;
      default:
        $service = "";
        break;
    }
    $parcel_terminal = "";
    if ($send_method == "pt")
      $parcel_terminal = 'offloadPostcode="' . $terminal_id . '" ';
    $additionalService = '';
    if ($service == "PA" || $service == "PU")
      $additionalService .= '<option code="ST" />';
    if ($orderInfo['is_cod'])
      $additionalService .= '<option code="BP" />';

    if ($additionalService) {
      $additionalService  = '<add_service>' . $additionalService . '</add_service>';
    }
    $phones = '';
    if ($address['phone'])
      $phones .= '<phone>' . $address['phone'] . '</phone>';
    if ($address['phone_mobile'])
      $phones .= '<mobile>' . $address['phone_mobile'] . '</mobile>';
    else
      $phones .= '<mobile>' . $address['phone'] . '</mobile>';
    $pickStart = Configuration::get('omnivalt_pick_up_time_start') ? Configuration::get('omnivalt_pick_up_time_start') : '8:00';
    $pickFinish = Configuration::get('omnivalt_pick_up_time_finish') ? Configuration::get('omnivalt_pick_up_time_finish') : '17:00';
    $pickDay = date('Y-m-d');
    if (time() > strtotime($pickDay . ' ' . $pickFinish))
      $pickDay = date('Y-m-d', strtotime($pickDay . "+1 days"));

    $shop_country = new Country();
    $shop_country_iso = $shop_country->getIsoById(Configuration::get('PS_SHOP_COUNTRY_ID'));
    $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:businessToClientMsgRequest>
                 <partner>' . Configuration::get('omnivalt_api_user') . '</partner>
                 <interchange msg_type="info11">
                    <header file_id="' . \Date('YmdHms') . '" sender_cd="' . Configuration::get('omnivalt_api_user') . '" >                
                    </header>
                    <item_list>
                      ';
    for ($i = 0; $i < $orderInfo['packs']; $i++) :
      $xmlRequest .= '
                       <item service="' . $service . '" >
                          ' . $additionalService . '
                          <measures weight="' . $orderInfo['weight'] . '" />
                          ' . self::cod($order, $orderInfo['is_cod'], $orderInfo['cod_amount']) . '
                          <receiverAddressee >
                             <person_name>' . $address['firstname'] . ' ' . $address['lastname'] . '</person_name>
                            ' . $phones;
      // if ( $send_method != 'pt'):
      $xmlRequest .= '
                             <address postcode="' . $address['postcode'] . '" ' . $parcel_terminal . ' deliverypoint="' . $address['city'] . '" country="' . $address['iso_code'] . '" street="' . str_replace('"', "'", $address['address1']) . '" />';
      /* else:
                        $xmlRequest .= '
                             <address '.$parcel_terminal.' />';
                      
                      endif; */
      $xmlRequest .= ' 
                         </receiverAddressee>
                          <!--Optional:-->
                          <returnAddressee>
                             <person_name>' . Configuration::get('omnivalt_company') . '</person_name>
                             <!--Optional:-->
                             <phone>' . Configuration::get('omnivalt_phone') . '</phone>
                             <address postcode="' . Configuration::get('omnivalt_postcode') . '" deliverypoint="' . Configuration::get('omnivalt_city') . '" country="' . Configuration::get('omnivalt_countrycode') . '" street="' . Configuration::get('omnivalt_address') . '" />
                          
                          </returnAddressee>';
      $xmlRequest .= '</item>';
    endfor;
    $xmlRequest .= '
                    </item_list>
                 </interchange>
              </xsd:businessToClientMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';
    return self::api_request($xmlRequest);
  }

  public static function call_omniva()
  {
    $service = "QH";
    $additionalService = '';

    if ($additionalService) {
      $additionalService  = '<add_service>' . $additionalService . '</add_service>';
    }
    $phones = '';
    $phones .= '<mobile>' . Configuration::get('omnivalt_phone') . '</mobile>';
    $pickStart = Configuration::get('omnivalt_pick_up_time_start') ? Configuration::get('omnivalt_pick_up_time_start') : '8:00';
    $pickFinish = Configuration::get('omnivalt_pick_up_time_finish') ? Configuration::get('omnivalt_pick_up_time_finish') : '17:00';
    $pickDay = date('Y-m-d');
    if (time() > strtotime($pickDay . ' ' . $pickFinish))
      $pickDay = date('Y-m-d', strtotime($pickDay . "+1 days"));

    $shop_country = new Country();
    $shop_country_iso = $shop_country->getIsoById(Configuration::get('PS_SHOP_COUNTRY_ID'));
    $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:businessToClientMsgRequest>
                 <partner>' . Configuration::get('omnivalt_api_user') . '</partner>
                 <interchange msg_type="info11">
                    <header file_id="' . \Date('YmdHms') . '" sender_cd="' . Configuration::get('omnivalt_api_user') . '" >                
                    </header>
                    <item_list>
                      ';
    for ($i = 0; $i < 1; $i++) :
      $xmlRequest .= '
                       <item service="' . $service . '" >
                          ' . $additionalService . '
                          <measures weight="1" />
                          <receiverAddressee >
                             <person_name>' . Configuration::get('omnivalt_company') . '</person_name>
                            ' . $phones . '
                             <address postcode="' . Configuration::get('omnivalt_postcode') . '" deliverypoint="' . Configuration::get('omnivalt_city') . '" country="' . Configuration::get('omnivalt_countrycode') . '" street="' . Configuration::get('omnivalt_address') . '" />
                          </receiverAddressee>
                          <!--Optional:-->
                          <returnAddressee>
                             <person_name>' . Configuration::get('omnivalt_company') . '</person_name>
                             <!--Optional:-->
                             <phone>' . Configuration::get('omnivalt_phone') . '</phone>
                             <address postcode="' . Configuration::get('omnivalt_postcode') . '" deliverypoint="' . Configuration::get('omnivalt_city') . '" country="' . Configuration::get('omnivalt_countrycode') . '" street="' . Configuration::get('omnivalt_address') . '" />
                          
                          </returnAddressee>';
      $xmlRequest .= '
                          <onloadAddressee>
                             <person_name>' . Configuration::get('omnivalt_company') . '</person_name>
                             <!--Optional:-->
                             <phone>' . Configuration::get('omnivalt_phone') . '</phone>
                             <address postcode="' . Configuration::get('omnivalt_postcode') . '" deliverypoint="' . Configuration::get('omnivalt_city') . '" country="' . Configuration::get('omnivalt_countrycode') . '" street="' . Configuration::get('omnivalt_address') . '" />
                            <pick_up_time start="' . date("c", strtotime($pickDay . ' ' . $pickStart)) . '" finish="' . date("c", strtotime($pickDay . ' ' . $pickFinish)) . '"/>
                          </onloadAddressee>';
      $xmlRequest .= '</item>';
    endfor;
    $xmlRequest .= '
                    </item_list>
                 </interchange>
              </xsd:businessToClientMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';
    return self::api_request($xmlRequest);
  }

  public static function api_request($request)
  {
    $barcodes = array();;
    $errors = array();
    $url = Configuration::get('omnivalt_api_url') . "/epmx/services/messagesService.wsdl";

    $headers = array(
      "Content-type: text/xml;charset=\"utf-8\"",
      "Accept: text/xml",
      "Cache-Control: no-cache",
      "Pragma: no-cache",
      "Content-length: " . strlen($request),
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERPWD, Configuration::get('omnivalt_api_user') . ":" . Configuration::get('omnivalt_api_pass'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $xmlResponse = curl_exec($ch);
    if ($xmlResponse === false) {
      $errors[] = curl_error($ch);
    } else {
      $errorTitle = '';
      if (strlen(trim($xmlResponse)) > 0) {
        //echo $xmlResponse; exit;
        $xmlResponse = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $xmlResponse);
        $xml = simplexml_load_string($xmlResponse);
        if (!is_object($xml)) {
          $errors[] = $this->l('Response is in the wrong format');
        }
        if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo)) {
          foreach ($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo as $data) {
            $errors[] = $data->clientItemId . ' - ' . $data->barcode . ' - ' . $data->message;
          }
        }
        if (empty($errors)) {
          if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo)) {
            foreach ($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo as $data) {
              $barcodes[] = (string) $data->barcode;
            }
          }
        }
      }
    }
    // }
    if (!empty($errors)) {
      return array('status' => false, 'msg' => implode('. ', $errors));
    } else {
      if (!empty($barcodes))
        return array('status' => true, 'barcodes' => $barcodes);
      $errors[] = 'No saved barcodes received';
      return array('status' => false, 'msg' => implode('. ', $errors));
    }
  }

  public static function getShipmentLabels($barcodes, $order_id = 0)
  {
    $errors = array();
    $barcodeXML = '';
    foreach ($barcodes as $barcode) {
      $barcodeXML .= '<barcode>' . $barcode . '</barcode>';
    }
    $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:addrcardMsgRequest>
                 <partner>' . Configuration::get('omnivalt_api_user') . '</partner>
                 <sendAddressCardTo>response</sendAddressCardTo>
                 <barcodes>
                    ' . $barcodeXML . '
                 </barcodes>
              </xsd:addrcardMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';
    //echo $xmlRequest;
    try {
      $url = Configuration::get('omnivalt_api_url') . "/epmx/services/messagesService.wsdl";
      $headers = array(
        "Content-type: text/xml;charset=\"utf-8\"",
        "Accept: text/xml",
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        "Content-length: " . strlen($xmlRequest),
      );
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_USERPWD, Configuration::get('omnivalt_api_user')  . ":" . Configuration::get('omnivalt_api_pass'));
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $xmlResponse = curl_exec($ch);
      $debugData['result'] = $xmlResponse;
    } catch (\Exception $e) {
      $errors[] = $e->getMessage() . ' ' . $e->getCode();
      $xmlResponse = '';
    }
    $xmlResponse = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $xmlResponse);
    $xml = simplexml_load_string($xmlResponse);
    if (!is_object($xml)) {
      $errors[] = self::l('Response is in the wrong format');
    }
    if (is_object($xml) && is_object($xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->barcode)) {
      $shippingLabelContent = (string) $xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->fileData;
      file_put_contents(_PS_MODULE_DIR_ . "/omnivaltshipping/pdf/" . $order_id . '.pdf', base64_decode($shippingLabelContent));
    } else {
      $errors[] = 'No label received from webservice';
    }

    if (!empty($errors)) {
      return array('status' => false, 'msg' => implode('. ', $errors));
    } else {
      if (!empty($barcodes))
        return array('status' => true);
      $errors[] = self::l('No saved barcodes received');
      return array('status' => false, 'msg' => implode('. ', $errors));
    }
  }

  public function hookOrderDetailDisplayed($params)
  {
    $carrier_ids = self::getCarrierIds();
    if ($params['order']->getWsShippingNumber() && (in_array($params['order']->id_carrier, $carrier_ids))) {
      $sql = 'SELECT c.iso_code FROM ' . _DB_PREFIX_ . 'address AS a LEFT JOIN ' . _DB_PREFIX_ . 'country AS c ON c.id_country = a.id_country WHERE id_address="' . $params['order']->id_address_delivery . '"';
      $address = Db::getInstance()->getRow($sql);
      $tracking_info = $this->getTracking(array($params['order']->getWsShippingNumber()));
      $this->context->smarty->assign(array(
        'tracking_info' => $tracking_info,
        'tracking_number' => $params['order']->getWsShippingNumber(),
        'country_code' => $address['iso_code'],
      ));
      $this->context->controller->registerJavascript(
        'omnivalt',
        'modules/' . $this->name . '/views/js/trackingURL.js',
        [
          'media' => 'all',
          'priority' => 200,
        ]
      );

      return $this->display(__file__, 'trackingInfo.tpl');
    }
    return '';
  }

  public function getTracking($tracking)
  {
    $url = str_ireplace('epmx/services/messagesService.wsdl', '', Configuration::get('omnivalt_api_url')) . 'epteavitus/events/from/' . date("c", strtotime("-1 week +1 day")) . '/for-client-code/' . Configuration::get('omnivalt_api_user');
    $process = curl_init();
    $additionalHeaders = '';
    curl_setopt($process, CURLOPT_URL, $url);
    curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/xml', $additionalHeaders));
    curl_setopt($process, CURLOPT_HEADER, FALSE);
    curl_setopt($process, CURLOPT_USERPWD, Configuration::get('omnivalt_api_user') . ":" . Configuration::get('omnivalt_api_pass'));
    curl_setopt($process, CURLOPT_TIMEOUT, 30);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    $return = curl_exec($process);
    curl_close($process);
    if ($process === false) {
      return false;
    }
    return $this->parseXmlTrackingResponse($tracking, $return);
  }

  public function parseXmlTrackingResponse($trackings, $response)
  {
    $errors = array();
    $resultArr = array();

    if (strlen(trim($response)) > 0) {
      $xml = simplexml_load_string($response);
      if (!is_object($xml)) {
        $errors[] = $this->l('Response is in the wrong format');
      }
      //$this->_debug($xml);
      if (is_object($xml) && is_object($xml->event)) {
        foreach ($xml->event as $awbinfo) {
          $awbinfoData = [];

          $trackNum = isset($awbinfo->packetCode) ? (string) $awbinfo->packetCode : '';

          if (!in_array($trackNum, $trackings))
            continue;
          //$this->_debug($awbinfo);
          $packageProgress = [];
          if (isset($resultArr[$trackNum]['progressdetail']))
            $packageProgress = $resultArr[$trackNum]['progressdetail'];

          $shipmentEventArray = [];
          $shipmentEventArray['activity'] = $this->getEventCode((string) $awbinfo->eventCode);

          $shipmentEventArray['deliverydate'] = DateTime::createFromFormat('U', strtotime($awbinfo->eventDate));
          $shipmentEventArray['deliverylocation'] = $awbinfo->eventSource;
          $packageProgress[] = $shipmentEventArray;

          $awbinfoData['progressdetail'] = $packageProgress;

          $resultArr[$trackNum] = $awbinfoData;
        }
      }
    }
    /*
        if (!empty($resultArr)) {
            foreach ($resultArr as $trackNum => $data) {
                $tracking = $this->_trackStatusFactory->create();
                $tracking->setCarrier($this->_code);
                $tracking->setCarrierTitle($this->getConfigData('title'));
                $tracking->setTracking($trackNum);
                $tracking->addData($data);
                $result->append($tracking);
            }
        }
        */
    if (!empty($errors)) {
      return false;
    }
    return $resultArr;
  }

  public function getEventCode($code)
  {
    $tracking = [
      'PACKET_EVENT_IPS_C' => $this->l("Shipment from country of departure"),
      'PACKET_EVENT_FROM_CONTAINER' => $this->l("Arrival to post office"),
      'PACKET_EVENT_IPS_D' => $this->l("Arrival to destination country"),
      'PACKET_EVENT_SAVED' => $this->l("Saving"),
      'PACKET_EVENT_DELIVERY_CANCELLED' => $this->l("Cancelling of delivery"),
      'PACKET_EVENT_IN_POSTOFFICE' => $this->l("Arrival to Omniva"),
      'PACKET_EVENT_IPS_E' => $this->l("Customs clearance"),
      'PACKET_EVENT_DELIVERED' => $this->l("Delivery"),
      'PACKET_EVENT_FROM_WAYBILL_LIST' => $this->l("Arrival to post office"),
      'PACKET_EVENT_IPS_A' => $this->l("Acceptance of packet from client"),
      'PACKET_EVENT_IPS_H' => $this->l("Delivery attempt"),
      'PACKET_EVENT_DELIVERING_TRY' => $this->l("Delivery attempt"),
      'PACKET_EVENT_DELIVERY_CALL' => $this->l("Preliminary calling"),
      'PACKET_EVENT_IPS_G' => $this->l("Arrival to destination post office"),
      'PACKET_EVENT_ON_ROUTE_LIST' => $this->l("Dispatching"),
      'PACKET_EVENT_IN_CONTAINER' => $this->l("Dispatching"),
      'PACKET_EVENT_PICKED_UP_WITH_SCAN' => $this->l("Acceptance of packet from client"),
      'PACKET_EVENT_RETURN' => $this->l("Returning"),
      'PACKET_EVENT_SEND_REC_SMS_NOTIF' => $this->l("SMS to receiver"),
      'PACKET_EVENT_ARRIVED_EXCESS' => $this->l("Arrival to post office"),
      'PACKET_EVENT_IPS_I' => $this->l("Delivery"),
      'PACKET_EVENT_ON_DELIVERY_LIST' => $this->l("Handover to courier"),
      'PACKET_EVENT_PICKED_UP_QUANTITATIVELY' => $this->l("Acceptance of packet from client"),
      'PACKET_EVENT_SEND_REC_EMAIL_NOTIF' => $this->l("E-MAIL to receiver"),
      'PACKET_EVENT_FROM_DELIVERY_LIST' => $this->l("Arrival to post office"),
      'PACKET_EVENT_OPENING_CONTAINER' => $this->l("Arrival to post office"),
      'PACKET_EVENT_REDIRECTION' => $this->l("Redirection"),
      'PACKET_EVENT_IN_DEST_POSTOFFICE' => $this->l("Arrival to receiver's post office"),
      'PACKET_EVENT_STORING' => $this->l("Storing"),
      'PACKET_EVENT_IPS_EDD' => $this->l("Item into sorting centre"),
      'PACKET_EVENT_IPS_EDC' => $this->l("Item returned from customs"),
      'PACKET_EVENT_IPS_EDB' => $this->l("Item presented to customs"),
      'PACKET_EVENT_IPS_EDA' => $this->l("Held at inward OE"),
      'PACKET_STATE_BEING_TRANSPORTED' => $this->l("Being transported"),
      'PACKET_STATE_CANCELLED' => $this->l("Cancelled"),
      'PACKET_STATE_CONFIRMED' => $this->l("Confirmed"),
      'PACKET_STATE_DELETED' => $this->l("Deleted"),
      'PACKET_STATE_DELIVERED' => $this->l("Delivered"),
      'PACKET_STATE_DELIVERED_POSTOFFICE' => $this->l("Arrived at post office"),
      'PACKET_STATE_HANDED_OVER_TO_COURIER' => $this->l("Transmitted to courier"),
      'PACKET_STATE_HANDED_OVER_TO_PO' => $this->l("Re-addressed to post office"),
      'PACKET_STATE_IN_CONTAINER' => $this->l("In container"),
      'PACKET_STATE_IN_WAREHOUSE' => $this->l("At warehouse"),
      'PACKET_STATE_ON_COURIER' => $this->l("At delivery"),
      'PACKET_STATE_ON_HANDOVER_LIST' => $this->l("In transition sheet"),
      'PACKET_STATE_ON_HOLD' => $this->l("Waiting"),
      'PACKET_STATE_REGISTERED' => $this->l("Registered"),
      'PACKET_STATE_SAVED' => $this->l("Saved"),
      'PACKET_STATE_SORTED' => $this->l("Sorted"),
      'PACKET_STATE_UNCONFIRMED' => $this->l("Unconfirmed"),
      'PACKET_STATE_UNCONFIRMED_NO_TARRIF' => $this->l("Unconfirmed (No tariff)"),
      'PACKET_STATE_WAITING_COURIER' => $this->l("Awaiting collection"),
      'PACKET_STATE_WAITING_TRANSPORT' => $this->l("In delivery list"),
      'PACKET_STATE_WAITING_UNARRIVED' => $this->l("Waiting, hasn't arrived"),
      'PACKET_STATE_WRITTEN_OFF' => $this->l("Written off"),
    ];
    if (isset($tracking[$code]))
      return $tracking[$code];
    return '';
  }

  public static function checkForClass($className)
  {
    if (!class_exists($className)) {
      if (isset(self::$_classMap[$className])) {
        require_once dirname(__FILE__) . '/' . self::$_classMap[$className];
      }
    }
  }
}
