<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class OmnivaltshippingOmnivaltadminajaxModuleFrontController extends ModuleFrontController {


    /**
     * <p>Exits script with message not logged in message when user is not logged in as admin.</p>
     */

    private $_module = NULL;
    public $module = 'omnivaltshipping';
    private $labelsMix = 4;
    public function __construct() {

        $context = Context::getContext();
        $cookie = new Cookie ('psAdmin');
        $employee = new Employee ($cookie->id_employee);
        $context->employee = $employee;
        $context->cookie = $cookie;
        if(!Context::getContext()->employee->isLoggedBack()){
            exit('Restricted.');
        }

        $this->_module = new OmnivaltShipping();
        $this->module = 'omnivaltshipping';

        $this->parseActions();
        parent::__construct();
        exit();
    }

    private function parseActions(){
        $action = Tools::getValue('action');

        switch($action){
            case 'saveorderinfo': $this->saveOrderInfo(); break;
            case 'masssaveorderinfo': $this->massSaveorderinfo(); break;
            case 'printlabels': $this->printLabels(); break;
            case 'bulklabels': $this->printBulkLabels(); break;
            case 'bulkmanifests': $this->printBulkManifests(); break;
            case 'bulkmanifestsall': $this->saveManifest(); break;
            
        }
    }


    protected function saveOrderInfo(){
        if(!empty($this->_module->warning)){
            return false;
        }
        $orderId = Tools::getValue('order_id', NULL);
        $order = new Order((int)$orderId);
        if(!$order){
            return false;
        }
        OmnivaltShipping::checkForClass('OrderInfo');
        $OrderObj = new OrderInfo();
        $saveResult = $OrderObj->saveOrderInfo();

        if(isset($saveResult['success'])){
          $this->_module->changeOrderStatus($orderId, $this->_module->getCustomOrderState());
          ob_clean(); // remove possible errors from prestashop
          echo json_encode($this->_module->l('Saved')); exit();
        }
        echo json_encode($saveResult); exit();
    }

    /**
     * Call API to get label PDF.
     */
    protected function printLabels($orderId = false){
        if (!$orderId)
          $orderId = $_POST['order_id'];
        if(empty($orderId) || $orderId == ''){
            echo json_encode(array('error'=>$this->_module->l('No order ID provided.')));
            exit();
        }

        /*
        OmnivaltShipping::checkForClass('OrderInfo');
        $orderInfo = new OrderInfo();
        $orderInfo = $orderInfo->getOrderInfo($orderIds);
        */
        $order = new Order((int)$orderId);
        OmnivaltShipping::checkForClass('OrderInfo');
        $orderInfoObj = new OrderInfo();
        $orderInfo = $orderInfoObj->getOrderInfo($orderId);
        if(empty($orderInfo)){
             echo json_encode(array('error'=>$this->_module->l('Order info not saved. Please save before generating labels')));
             exit();
        }
   
        $status = OmnivaltShipping::get_tracking_number($orderId);
        if ($status['status']){
          $order->setWsShippingNumber($status['barcodes'][0]);
          $order->save();
          $this->setOmnivaOrder($orderId);
          //$return .= '<div class="alert alert-success">'.implode(' ',$status['barcodes']).'</div>';
          echo json_encode($status['barcodes']);
          $label_status = OmnivaltShipping::getShipmentLabels($status['barcodes'],$orderId);
          if (!$label_status['status']){
            $orderInfoObj->saveError($orderId,addslashes($label_status['msg']));
            $this->_module->changeOrderStatus($orderId, $this->_module->getErrorOrderState());
            echo json_encode(array('error'=>$label_status['msg']));
            exit();
          }
        } else {
          $orderInfoObj->saveError($orderId,($status['msg']));
          $this->_module->changeOrderStatus($orderId, $this->_module->getErrorOrderState());
          echo json_encode(array('error'=>$status['msg']));
          exit();
        }
        $orderInfoObj->saveError($orderId,'');

    }
    
    protected function printBulkLabels(){
        require_once(_PS_MODULE_DIR_.'omnivaltshipping/tcpdf/tcpdf.php');
        require_once(_PS_MODULE_DIR_.'omnivaltshipping/fpdi/autoload.php');
        $orderIds = trim($_REQUEST['order_ids'],',');
        $orderIds = explode(',',$orderIds);
        OmnivaltShipping::checkForClass('OrderInfo');
        $object = '';
        $pdf = new \setasign\Fpdi\TcpdfFpdi('P');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        if (is_array($orderIds)){
          $carrier_ids = OmnivaltShipping::getCarrierIds();
          foreach($orderIds as $orderId){
            $orderInfoObj = new OrderInfo();
            $orderInfo = $orderInfoObj->getOrderInfo($orderId);
            if(empty($orderInfo)){
              $OrderObj = new OrderInfo();
              $saveResult = $OrderObj->saveOrderInfo($orderId);
              $orderInfo = $orderInfoObj->getOrderInfo($orderId);
            }
            
            if(empty($orderInfo))
              continue;
            $order = new Order((int)$orderId);
            if (!in_array((int)$order->id_carrier, $carrier_ids))
              continue;
            $track_numer = $order->getWsShippingNumber();
            if ($track_numer == ''){
              
              $status = OmnivaltShipping::get_tracking_number($orderId);
              if ($status['status']){
                $order->setWsShippingNumber($status['barcodes'][0]);
                $order->save();
                $this->setOmnivaOrder($orderId);
                
                $track_numer = $status['barcodes'][0];
                if (file_exists(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf')){
                  unlink(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf');
                }
              } else {
                $orderInfoObj->saveError($orderId,addslashes($status['msg']));
                $this->_module->changeOrderStatus($orderId, $this->_module->getErrorOrderState());
                if (count($orderIds) > 1) {
                  continue;
                } else {
                  echo $status['msg'];
                  exit();
                }
              }
            }
            $label_url = '';
            if (file_exists(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf')){
              $label_url = _PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf';
            }
            if ($label_url == ''){
              $label_status = OmnivaltShipping::getShipmentLabels(array($track_numer),$orderId);
              if ($label_status['status']){
                if (file_exists(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf')){
                  $label_url = _PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf';
                }
              } else {
                $orderInfoObj->saveError($orderId,addslashes($label_status['msg']));
                $this->_module->changeOrderStatus($orderId, $this->_module->getErrorOrderState());
              }
              if ($label_url == '')
                continue;
            }
            $this->_module->changeOrderStatus($orderId, $this->_module->getCustomOrderState());
            $pagecount = $pdf->setSourceFile($label_url);
            if (file_exists($label_url)) { unlink($label_url); }

            $print_type = Configuration::get('omnivalt_print_type');
            if ($print_type === 'single') {
              for ($i = 1; $i <= $pagecount; $i++) {
                $tplidx = $pdf->ImportPage($i);
                $s = $pdf->getTemplatesize($tplidx);
                $pdf->AddPage('P', array($s['width'], $s['height']));
                $pdf->useTemplate($tplidx);  
              }
            } else {
              $newPG = array(0,4,8,12,16,20,24,28,32);
              if ( $this->labelsMix >= 4) {
                $pdf->AddPage();
                $page = 1;
                $templateId = $pdf->importPage($page);
                $this->labelsMix = 0;
              }
              $tplidx = $pdf->ImportPage(1);
              if ($this->labelsMix == 0) {
                $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, false);
              } else if ($this->labelsMix == 1) {
                $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, false);
              } else if ($this->labelsMix == 2) {
                $pdf->useTemplate($tplidx, 5, 160, 94.5, 108, false);  
              } else if ($this->labelsMix == 3) {
                $pdf->useTemplate($tplidx, 110, 160, 94.5, 108, false);  
              } else {
                echo $this->_module->l('Problems with labels count, please, select one order!!!');
                exit();
              }
              $this->labelsMix++;
            }
          }
        }
        $pdf->Output('Omnivalt_labels.pdf', 'I');
    }
    public function setOmnivaOrder($id_order = '')
    {
      //exit();
      $id_order = intval($id_order);
      $sql2 = "SELECT omnivalt_manifest FROM "._DB_PREFIX_."cart WHERE id_cart = (SELECT id_cart FROM "._DB_PREFIX_."orders WHERE id_order =".$id_order.")";
      $isPrinted = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql2);
      if($isPrinted[0]['omnivalt_manifest'] == null) {
        $currentManifest = intval(Configuration::get('omnivalt_manifest'));
        
        $saveManifest = "UPDATE "._DB_PREFIX_."cart 
        SET omnivalt_manifest = ".$currentManifest."
        WHERE id_cart = (SELECT id_cart FROM "._DB_PREFIX_."orders WHERE id_order = ".$id_order.");";
        Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($saveManifest);
      }

    }

    public function saveManifest()
    {
      if(Tools::getValue('type') == 'new') {
        if(Tools::getValue('order_ids')==null) {
          print $this->_module->l('Here is nothing to print!!!');
          exit();
        }
      }
      if(Tools::getValue('type')=='skip'){
        $orderIds = trim($_REQUEST['order_ids'],',');
        $orderIds = explode(',',$orderIds);
        for($i=0;$i<count($orderIds);$i++){
          $saveManifest = "UPDATE "._DB_PREFIX_."cart 
          SET omnivalt_manifest = '-1'
          WHERE id_cart = (SELECT id_cart FROM "._DB_PREFIX_."orders WHERE id_order = ".$orderIds[$i].");";
          Db::getInstance(_PS_USE_SQL_SLAVE_)->execute($saveManifest);
        }
        //var_dump($orderIds);
        exit();
      }
      $item = $this->printBulkManifests();
      
    }
    
    protected function printBulkManifests(){
        global $cookie;
        require_once(_PS_MODULE_DIR_.'omnivaltshipping/tcpdf/tcpdf.php');

        $lang = Configuration::get('omnivalt_manifest_lang');
        if (empty($lang)) $lang = 'en';
        $orderIds = trim($_REQUEST['order_ids'],',');
        $orderIds = explode(',',$orderIds);
        OmnivaltShipping::checkForClass('OrderInfo');
        $object = '';
        $orderInfoObj = new OrderInfo();
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $order_table = '';
        $count = 1;
        if (is_array($orderIds)){
          $carrier_ids = OmnivaltShipping::getCarrierIds();
          $carrier_terminal_ids = OmnivaltShipping::getCarrierIds(['omnivalt_pt']);
          foreach($orderIds as $orderId){
            if (!$orderId)
              continue;
            $orderInfo = new OrderInfo();
            $orderInfo = $orderInfo->getOrderInfo($orderId);
            if(empty($orderInfo)){
              $OrderObj = new OrderInfo();
              $saveResult = $OrderObj->saveOrderInfo($orderId);
              //$orderInfo = $orderInfoObj->getOrderInfo($orderId);
              $orderInfo = $OrderObj->getOrderInfo($orderId);
            }
            if(empty($orderInfo))
              continue;
            $order = new Order((int)$orderId);
            if (!in_array($order->id_carrier, $carrier_ids))
              continue;
            $track_numer = $order->getWsShippingNumber();
            if ($track_numer == ''){
              $status = OmnivaltShipping::get_tracking_number($orderId);
              if ($status['status']){
                $order->setWsShippingNumber($status['barcodes'][0]);
                $order->save();
                //$this->setOmnivaOrder($orderId);
                $track_numer = $status['barcodes'][0];
                if (file_exists(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf')){
                  unlink(_PS_MODULE_DIR_.'omnivaltshipping/pdf/'.$order->id.'.pdf');
                }
              } else {
                $orderInfoObj->saveError($orderId,addslashes($status['msg']));
                $this->_module->changeOrderStatus($orderId, $this->_module->getErrorOrderState());
                if (count($orderIds) > 1) {
                  continue;
                } else {
                  echo $status['msg'];
                  exit();
                }
              }
            }
            $this->setOmnivaOrder($orderId);
            $pt_address = '';
            if (in_array($order->id_carrier, $carrier_terminal_ids)){
              $cart = new Cart($order->id_cart);
              $pt_address = OmnivaltShipping::getTerminalAddress($cart->omnivalt_terminal);
            }
            
            $address = new Address($order->id_address_delivery);
            $client_address = $address->firstname.' '.$address->lastname.', '.$address->address1.', '.$address->postcode.', '.$address->city.' '.$address->country;
            if ($pt_address != '')
              $client_address = '';
            $order_table .= '<tr><td width = "40" align="right">'.$count.'.</td><td>'.$track_numer.'</td><td width = "60">'.date('Y-m-d').'</td><td width = "40">'.$orderInfo['packs'].'</td><td width = "60">'.($orderInfo['packs']*$orderInfo['weight']).'</td><td width = "210">'.$client_address.$pt_address.'</td></tr>';
            $count++;
            //make order shipped after creating manifest
            $history = new OrderHistory();
            $history->id_order = (int)$orderId;
            $history->id_employee = (int)$cookie->id_employee;
            $history->changeIdOrderState((int)Configuration::get('PS_OS_SHIPPING'), $order);
            $history->add();
            //$history->addWithEmail(true); // broken in 1.7.6
            
          }}
        $pdf->SetFont('freeserif', '', 14);
        $id_lang = $cookie->id_lang;
        
        $shop_country = new Country(Country::getByIso(Configuration::get('omnivalt_countrycode')));
        
        $shop_addr = '<table cellspacing="0" cellpadding="1" border="0"><tr><td>'.date('Y-m-d H:i:s').'</td><td>'.OmnivaltShipping::getTranslate('Sender address',$lang).':<br/>'.Configuration::get('omnivalt_company').'<br/>'.Configuration::get('omnivalt_address').', '.Configuration::get('omnivalt_postcode').'<br/>'.Configuration::get('omnivalt_city').', '.$shop_country->name[$id_lang].'<br/></td></tr></table>';
       
        $pdf->writeHTML($shop_addr, true, false, false, false, '');
        $tbl = '
        <table cellspacing="0" cellpadding="4" border="1">
          <thead>
            <tr>
              <th width = "40" align="right">'.OmnivaltShipping::getTranslate('No.',$lang).'</th>
              <th>'.OmnivaltShipping::getTranslate('Shipment number',$lang).'</th>
              <th width = "60">'.OmnivaltShipping::getTranslate('Date',$lang).'</th>
              <th width = "40">'.OmnivaltShipping::getTranslate('Amount',$lang).'</th>
              <th width = "60">'.OmnivaltShipping::getTranslate('Weight (kg)',$lang).'</th>
              <th width = "210">'.OmnivaltShipping::getTranslate('Recipient address',$lang).'</th>
            </tr>
          </thead>
          <tbody>
            '.$order_table.'
          </tbody>
        </table><br/><br/>
        ';
        $pdf->SetFont('freeserif', '', 9);
        $pdf->writeHTML($tbl, true, false, false, false, '');
        $pdf->SetFont('freeserif', '', 14);
        $sign = OmnivaltShipping::getTranslate('Courier name, surname, signature',$lang) . ' ________________________________________________<br/><br/>';
        $sign .= OmnivaltShipping::getTranslate('Sender name, surname, signature',$lang) . ' ________________________________________________';
        $pdf->writeHTML($sign, true, false, false, false, '');
        $pdf->Output('Omnivalt_manifest.pdf','I');  
        
        
        
        if(Tools::getValue('type') == 'new') {

          $current = intval(Configuration::get('omnivalt_manifest'));
          $current++;
          Configuration::updateValue('omnivalt_manifest', $current);
        }
    }
}
