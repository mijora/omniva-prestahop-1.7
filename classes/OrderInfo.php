<?php
/**
 * 
 */
class OrderInfo {
  
    public function __construct() {

    }

    public function saveOrderInfo($default_data = false){
        //check if entry with such order ID already exists
        if ($default_data){
          $data = array('order_id' => $default_data);
          $orderId = $default_data;
          
          $order = new Order((int)$orderId);
          $cart = new Cart((int)$order->id_cart);
          $carrier_ids = OmnivaltShipping::getCarrierIds();
          if (!in_array($order->id_carrier, $carrier_ids))
            return array('error'=>'Invalid order carrier.');
          $terminal_id = $cart->omnivalt_terminal;
 
          $packs = 1;
          $weight = $order->getTotalWeight();
          $isCod = (strpos($order->module, 'cashondelivery') !== false);
          $codAmount = $order->total_paid_tax_incl;
          $terminal = $terminal_id;
          $carrier = $order->id_carrier;
          if($weight==NULL || !Validate::isFloat($weight) || $weight<=0){
            $weight = 1;
          }
          
        } else {
          $data = $_POST;

          if(empty($_POST)){
              return false;
          }

          $orderId = Tools::getValue('order_id', NULL);
          $packs = Tools::getValue('packs', NULL);
          $weight = Tools::getValue('weight', NULL);
          $isCod = Tools::getValue('is_cod', NULL);
          $codAmount = Tools::getValue('cod_amount', NULL);
          $terminal = Tools::getValue('parcel_terminal', NULL);
          $carrier = Tools::getValue('carrier', NULL);
        }
        if($isCod==NULL){ $isCod = '0'; }

        if(empty($orderId) || !is_numeric($data['order_id'])){
            return array('error'=>'Bad order ID.');
        }
        
        $order = new Order((int)$orderId);
        
        if(!$order){
            return array('error'=>'Invalid order.');
        }
        $order->id_carrier = (int)$carrier ;
        $order->save();
        
        
        $order_carrier = new OrderCarrier((int)$order->getIdOrderCarrier());;
        $order_carrier->id_carrier = (int)$carrier;
        $order_carrier->save();
        
        $cart = new Cart((int)$order->id_cart);
        $cart->id_carrier = (int)$carrier;
        $cart->save();
        $cart->setOmnivaltTerminal($terminal);

        //validate fields
        
        //packs
        if($packs==NULL || !is_numeric($packs) || (int)$packs<1){
            return array('error'=>'Bad packs number.');
        }
        if($weight==NULL || !Validate::isFloat($weight) || $weight<=0){
            return array('error'=>'Bad weight.');
        }
        if($isCod!='0' && $isCod!='1'){
            return array('error'=>'Bad COD value.');
        }
        if($isCod=='1' && ($codAmount=='' || !Validate::isFloat($codAmount))){
            return array('error'=>'Bad COD amount.');
        }
        
      
        //check if entry for order_id is not already inserted
        $orderDataCheck = $this->getOrderInfo($orderId);
        if(!empty($orderDataCheck)){
            $this->deleteOrderInfo($orderId);
            $order = new Order((int)$orderId);
            $order->setWsShippingNumber('');
            $order->save();
            if (file_exists(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf')){
              unlink(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf');
            }
            //return array('error'=>'Entry for this order already exists.');
        }
        $result = Db::getInstance()->insert('omnivalt_order_info', array(
                'order_id' => $orderId,
                'packs' => $packs,
                'weight' => $weight,
                'is_cod' => $isCod,
                'cod_amount' => $codAmount,
                'error' => '',
         
            ));
            $lastId = Db::getInstance()->Insert_ID();
   

        if($result){
            return array('success'=>$orderId);
        }else{
            return array('error'=>$result);
        }
    }
    
    public function saveError($order_id,$error){
      if (is_array($error))
        $error = implode(', ',$error);
      Db::getInstance()->update('omnivalt_order_info', array('error'=>$error),'order_id='.(int)$order_id);
    }
    
    public function deleteOrderInfo($order_id){
      Db::getInstance()->delete('omnivalt_order_info', 'order_id='.(int)$order_id);
    }

    public function getOrderInfo($orderId){
        $db = Db::getInstance();
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'omnivalt_order_info WHERE order_id='.(int)$orderId;
        return $db->getRow($sql);
    }

}
