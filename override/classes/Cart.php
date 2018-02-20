<?php
Cart::$definition['fields']['omnivalt_terminal'] = array('type' => ObjectModel::TYPE_STRING, 'size' => 10);
class Cart extends CartCore
{	
	public $omnivalt_terminal;
    
  public function setOmnivaltTerminal($terminal_id){
     //$sql = "UPDATE `"._DB_PREFIX_ ."cart` SET omnivalt_terminal='".$terminal_id."' WHERE id_cart = ".(int)$this->id.";";
     return Db::getInstance()->update('cart', array('omnivalt_terminal'=>$terminal_id), 'id_cart = '.(int)$this->id );
  }
}