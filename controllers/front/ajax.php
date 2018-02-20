<?php
class OmnivaltshippingAjaxModuleFrontController extends ModuleFrontController
{
 
	public function initContent()
	{
		$this->ajax = true;
		parent::initContent();
	}
 
	public function displayAjax()
	{
    $context = Context::getContext();
    if (isset($_POST['terminal']) && $_POST['terminal'] != '' && isset($context->cookie->id_cart))
    {
      $cart = new Cart((int)$context->cookie->id_cart);
      $cart->setOmnivaltTerminal($_POST['terminal']);
      die(Tools::jsonEncode('OK'));
    }
    die(Tools::jsonEncode('not_changed'));
    
	}
 
}