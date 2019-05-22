<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
IncludeModuleLangFile(__FILE__);

if (!CModule::IncludeModule('sale')) return;
?>
<style>
    #wrape{    min-height: calc(100vh - 235px);}
</style>
<?
require_once(realpath(dirname(dirname(__FILE__))) ."/config.php");

$isOrderConverted = \Bitrix\Main\Config\Option::get("main", "~sale_converted_15", 'N');
$errorMessage = '';

$arUser = CUser::GetByID($USER->GetID())->Fetch();
$arOrder = CSaleOrder::GetByID($_REQUEST["ID"]);

$checkParams = true;
if(empty($_REQUEST["ID"]) || empty($_REQUEST["orderId"]) || empty($arOrder) ) {
    $checkParams = false;   
}

if ($checkParams) {

    $order_id = $_GET["orderId"];
    $order_number = $_REQUEST["ID"];
    $paySystem = new CSalePaySystemAction();
    $paySystem->InitParamArrays($arOrder, $arOrder["ID"]);
    $order_number = $arOrder["ID"];
    $orderNumberPrint = $paySystem->GetParamValue('ORDER_NUMBER');


    if ($arOrder['PAYED'] == "N") {

        require_once("rbs.php");

        if ($paySystem->GetParamValue("TEST_MODE") == 'Y') {
            $test_mode = true;
        } else {
            $test_mode = false;
        }

        if ($paySystem->GetParamValue("LOGGING") == 'Y') {
            $logging = true;
        } else {
            $logging = false;
        }

        $params['user_name'] = $paySystem->GetParamValue("USER_NAME");
        $params['password'] = $paySystem->GetParamValue("PASSWORD");
        $params['test_mode'] = $test_mode;
        $params['logging'] = $logging;

        $rbs = new RBS($params);

        $response = $rbs->get_order_status_by_orderId($order_id);
        $pos  = strripos($response['orderNumber'], "_");
        if ($pos === false) {
            
        }
        else{
            $resultId = explode("_", $response['orderNumber'] );
            array_pop($resultId);
            $resultId = implode('_', $resultId);
            $orderTrue = true;
            if($resultId != $orderNumberPrint) {
                $orderTrue = false;
                $title = GetMessage('RBS_PAYMENT_ORDER_ERROR3');
                $message = GetMessage('RBS_PAYMENT_ORDER_NOT_FOUND', array('#ORDER_ID#' => htmlspecialchars(\Bitrix\Main\Application::getInstance()->getContext()->getRequest()->get('ORDER_ID'), ENT_QUOTES)));
                $APPLICATION->SetTitle($title);
                echo $message;
                die;
            }
         }


        if (($response['errorCode'] == 0) && $orderTrue && (($response['orderStatus'] == 1) || ($response['orderStatus'] == 2))) {

            $arOrderFields = array(
                "PS_SUM" => $response["amount"] / 100,
                "PS_CURRENCY" => $response["currency"],
                "PS_RESPONSE_DATE" => Date(CDatabase::DateFormatToPHP(CLang::GetDateFormat("FULL", LANG))),
                "PS_STATUS" => "Y",
                "PS_STATUS_DESCRIPTION" => $response["cardAuthInfo"]["pan"] . ";" . $response['cardAuthInfo']["cardholderName"],
                "PS_STATUS_MESSAGE" => $response["paymentAmountInfo"]["paymentState"],
                "PS_STATUS_CODE" => "Y",
            );

            CSaleOrder::StatusOrder($order_number, RESULT_ORDER_STATUS);
            CSaleOrder::PayOrder($order_number, "Y", true, true);

            if ($paySystem->GetParamValue("SHIPMENT_ENABLE") == 'Y') {
                if ($isOrderConverted != "Y") {
                    CSaleOrder::DeliverOrder($order_number, "Y");
                } else {
                    $r = \Bitrix\Sale\Compatible\OrderCompatibility::allowDelivery($order_number, true);
                    if (!$r->isSuccess(true)) {
                        foreach ($r->getErrorMessages() as $error) {
                            $errorMessage .= " " . $error;
                        }
                    }
                }
            }

            

            $title = GetMessage('RBS_PAYMENT_ORDER_THANK');
            if ($response['orderStatus'] == 1) {
                $message = GetMessage('RBS_PAYMENT_ORDER_AUTH', array('#ORDER_ID#' => $orderNumberPrint));
            } else {
                $message = GetMessage('RBS_PAYMENT_ORDER_FULL_AUTH', array('#ORDER_ID#' => $orderNumberPrint));
            }

            $title = GetMessage('RBS_PAYMENT_ORDER_THANK');
            $message = GetMessage('RBS_PAYMENT_ORDER_PAY1', array('#ORDER_ID#' => $orderNumberPrint));

        } else if ($response['errorCode'] == 0) {
            $arOrderFields["PS_STATUS_MESSAGE"] = "[" . $response["orderStatus"] . "] " . $response["actionCodeDescription"];
            $title = GetMessage('RBS_PAYMENT_ORDER_PAY', array('#ORDER_ID#' => $orderNumberPrint));
            //$message = GetMessage('RBS_PAYMENT_ORDER_STATUS', array('#ORDER_ID#' => $response["orderStatus"], '#DESCRIPTION#' => $response["actionCodeDescription"]));
            $message = GetMessage('RBS_PAYMENT_ORDER_STATUS', array('#DESCRIPTION#' => $response["actionCodeDescription"]));

        } else {
            $arOrderFields["PS_STATUS_MESSAGE"] = GetMessage('RBS_PAYMENT_ORDER_ERROR', array('#ERROR_CODE#' => $response["errorCode"], '#ERROR_MESSAGE#' => $response["errorMessage"]));
            $title = GetMessage('RBS_PAYMENT_ORDER_PAY', array('#ORDER_ID#' => $orderNumberPrint));
            $message = GetMessage('RBS_PAYMENT_ORDER_ERROR2', array('#ERROR_CODE#' => $response["errorCode"], '#ERROR_MESSAGE#' => $response["errorMessage"]));
        }

        CSaleOrder::Update($order_number, $arOrderFields);

    } else {

        $title = GetMessage('RBS_PAYMENT_ORDER_THANK');
        $message = GetMessage('RBS_PAYMENT_ORDER_PAY1', array('#ORDER_ID#' => $orderNumberPrint));

    }


} else {
    $title = GetMessage('RBS_PAYMENT_ORDER_ERROR3');
    $message = GetMessage('RBS_PAYMENT_ORDER_NOT_FOUND', array('#ORDER_ID#' => htmlspecialchars(\Bitrix\Main\Application::getInstance()->getContext()->getRequest()->get('ORDER_ID'), ENT_QUOTES)));
}

$APPLICATION->SetTitle($title);
// if($response["actionCode"]==-2007){
//     //echo "<pre>"; print_r($response["amount"] / 100); echo "</pre>";
//     $myAmount = $response["amount"];
//     $myCurrency = $response["currency"];
//     $module_id = RBS_MODULE_ID;
//     $MODULE_PARAMS = [];
//     $MODULE_PARAMS['RETURN_PAGE'] = COption::GetOptionString($module_id, "RETURN_PAGE_VALUE", '/sale/payment/result.php');
//     $MODULE_PARAMS['GATE_TRY'] = COption::GetOptionString($module_id, "GATE_TRY", API_GATE_TRY);
//     $MODULE_PARAMS['GATE_SEND_COMMENT'] = unserialize(COption::GetOptionString($module_id, "GATE_SEND_COMMENT", serialize(array())));
//     $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== "off" ? 'https://' : 'http://';
//     $return_url = $protocol . $_SERVER['SERVER_NAME'] . $MODULE_PARAMS['RETURN_PAGE'] . '?ID=' . $arOrder['ID'];
//     //$hello = $rbs->register_order($order_number . '_' . $i, $myAmount, $return_url, $myCurrency);
//     //$hello = $rbs->register_order($order_number . '_' . "2", $myAmount, $return_url, $myCurrency);
// }
//$hello1 = $rbs->get_order_status_by_orderNumber("1062_2");
$countMy;
$countElem = 1;
$payok;
$staticFirstLink;
//echo "<pre>"; print_r($arOrder['PAYED']); echo "</pre>";
//echo "<pre>"; print_r($hello1); echo "</pre>";
if($arOrder['PAYED']=="N"){
    $checkFirstLink = $rbs->get_order_status_by_orderNumber($order_number);
    if($checkFirstLink["actionCode"]==0){
    $staticFirstLink = "Y";
    }
    else{
        for ($i = 0; $i <= 20; $i++) {
            $hello2 = $rbs->get_order_status_by_orderNumber($order_number."_".$i);
            if($hello2["actionCode"]){
                if($hello2["actionCode"]==0){
                    $payok = $countElem;
                    break;
                }
                if($hello2["actionCode"]==-100){
                    break;
                }
                $countElem++;
            }
            $countMy = $i;
        }
    }
    
}
$checkedPay = CSaleOrder::GetByID($arOrder["ID"]);
if($checkedPay["PAYED"]=="Y"){
CSaleOrder::StatusOrder($arOrder["ID"], "P");
echo "Заказ оплачен";
}
else{
    //echo "<pre>"; print_r($countElem); echo "</pre>";
    if($staticFirstLink=="Y"){
        $check = $rbs->get_order_status_by_orderNumber($order_number);  
    }
    else{
    $check = $rbs->get_order_status_by_orderNumber($order_number."_".$countElem);
    }
    if($check['actionCode']==-100){
        $my_link = "https://3dsec.sberbank.ru/payment/merchants/sbersafe/payment_ru.html?mdOrder=".$check['attributes'][0]["value"];
        //echo "<pre>"; print_r($my_link); echo "</pre>";
        ?>
         <br>
         <div class="sberbank-info-about-payment">
        <p><?echo $message;?></p>
        <a class="style2_122" href="<?=$my_link?>">Оплатить заказ</a>
         </div>
        <?
    }
    else{
        //$countElem++;
        $myAmount = $response["amount"];
        $myCurrency = $response["currency"];
        $module_id = RBS_MODULE_ID;
        $MODULE_PARAMS = [];
        $MODULE_PARAMS['RETURN_PAGE'] = COption::GetOptionString($module_id, "RETURN_PAGE_VALUE", '/sale/payment/result.php');
        $MODULE_PARAMS['GATE_TRY'] = COption::GetOptionString($module_id, "GATE_TRY", API_GATE_TRY);
        $MODULE_PARAMS['GATE_SEND_COMMENT'] = unserialize(COption::GetOptionString($module_id, "GATE_SEND_COMMENT", serialize(array())));
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== "off" ? 'https://' : 'http://';
        $return_url = $protocol . $_SERVER['SERVER_NAME'] . $MODULE_PARAMS['RETURN_PAGE'] . '?ID=' . $arOrder['ID'];
        $gate_comment="";
        if(!empty($checkedPay['USER_DESCRIPTION'])){
        $gate_comment .= "Комментарий к заказу :"."\n"."\n".$checkedPay['USER_DESCRIPTION'];
        }
        $hello = $rbs->register_order($order_number . '_' . $countElem, $myAmount, $return_url, $myCurrency,$gate_comment);

        $resProps = CSaleOrderPropsValue::GetOrderProps($order_number);
        while ($arProp = $resProps->Fetch())   
        {
            if($arProp['CODE']=="UNIQUE_NUMBER_SBERBANK")
            {
                $arProps[$arProp['CODE']] = $arProp;
            }
        }
        $nowRegisterOrder = $rbs->get_order_status_by_orderNumber($order_number."_".$countElem);
        $arProps["UNIQUE_NUMBER_SBERBANK"]["VALUE"] = $nowRegisterOrder['attributes'][0]["value"];
        $arFields = array(
            "UNIQUE_NUMBER_SBERBANK" => $arProps["UNIQUE_NUMBER_SBERBANK"]["VALUE"],
         );
         if(CSaleOrderPropsValue::Update($arProps["UNIQUE_NUMBER_SBERBANK"]["ID"], array("VALUE"=>$arProps["UNIQUE_NUMBER_SBERBANK"]["VALUE"])))
         {
         }

        $my_link = $hello['formUrl'];
        ?>
        <br>
        <div class="sberbank-info-about-payment">
        <p><?echo $message;?></p>
        <a class="style2_122" href="<?=$my_link?>">Оплатить заказ</a>
        </div>
        <?
        //echo "<pre>"; print_r($my_link); echo "</pre>";
    }
    
}

