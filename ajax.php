<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
//include_once(_PS_MODULE_DIR_.'/deliverydateswizardpro/lib/bootstrap.php');
 
$module = Module::getInstanceByName('omnivaltshipping');
 
$module->handleAjaxRequest();