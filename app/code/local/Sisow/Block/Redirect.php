<?php
class Sisow_Block_Redirect extends Mage_Core_Block_Abstract
{
	/**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    private function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    private function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

	protected function _toHtml()
    {		
		$orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		$method = (isset($_GET['method'])) ? $_GET['method'] : '';
		
		if($orderIncrementId == '')
		{
			header('Location: '. Mage::getUrl('checkout/cart'));
			exit;
		}
		
		$billto = $order->getBillingAddress();
		if (!$billto) {
			$billto = $this->getQuote()->getBillingAddress();
		}
		$shipto = $order->getShippingAddress();
		if (!$shipto) {
			$shipto = $this->getQuote()->getShippingAddress();
			if (!$shipto) {
				$shipto = $billto;
			}
		}
		
		$arg = array();
		//shipping address
		$arg['shipping_firstname'] 		= $shipto->getFirstname();
		$arg['shipping_lastname'] 		= $shipto->getLastname();
		$arg['shipping_mail'] 			= $shipto->getEmail();
		$arg['shipping_company'] 		= $shipto->getCompany();
		$arg['shipping_address1'] 		= $shipto->getStreet1();
		$arg['shipping_address2'] 		= $shipto->getStreet2();
		$arg['shipping_zip'] 			= $shipto->getPostcode();
		$arg['shipping_city']			= $shipto->getCity();
		//$arg['shipping_country'] 		= $shipto;
		$arg['shipping_countrycode'] 	= $shipto->getCountry();
		$arg['shipping_phone'] 			= (isset($_GET['phone']) && $_GET['phone'] != '') ? $_GET['phone'] : $shipto->getTelephone();
		
		//billing address
		$arg['billing_firstname'] 		= $billto->getFirstname();
		$arg['billing_lastname'] 		= $billto->getLastname();
		$arg['billing_mail'] 			= $billto->getEmail();
		$arg['billing_company'] 		= $billto->getCompany();
		$arg['billing_address1'] 		= $billto->getStreet1();
		$arg['billing_address2'] 		= $billto->getStreet2();
		$arg['billing_zip'] 			= $billto->getPostcode();
		$arg['billing_city'] 			= $billto->getCity();
		//$arg['billing_country'] = $order->getBillingAddress()->;
		$arg['billing_countrycode'] 	= $billto->getCountry();
		$arg['billing_phone'] 			= (isset($_GET['phone']) && $_GET['phone'] != '') ? $_GET['phone'] : $billto->getTelephone();
		
		$i = 0;
		foreach($order->getAllVisibleItems() as $item)
		{
			$i++;
			$sku = $item->getSku();
			$arg['product_id_' . $i] = !empty($sku) ? $sku : 'sku' . $i;
			$arg['product_description_' . $i] = $item->getName();
			$arg['product_quantity_' . $i] = (int)($item->getQtyOrdered() ? $item->getQtyOrdered() : $item->getQty());
			$arg['product_tax_' . $i] = round($item->getTaxAmount() * 100, 0);
			$arg['product_taxrate_' . $i] = round($item->getTaxPercent() * 100, 0);
			$arg['product_netprice_' . $i] = round($item->getPrice() * 100, 0);
			$arg['product_price_' . $i] = round($item->getPriceInclTax() * 100, 0);
			$arg['product_nettotal_' . $i] = round($item->getRowTotal() * 100, 0);
			$arg['product_total_' . $i] = round($item->getRowTotalInclTax() * 100, 0);
		}
				
		$shipping = $order->getShippingAmount();
		if ($shipping > 0) {
			$i++;
			$shiptax = $shipping + $order->getShippingTaxAmount();
			$arg['product_id_' . $i] = 'shipping';
			$arg['product_description_' . $i] = 'Verzendkosten';
			$arg['product_quantity_' . $i] = 1;
			$arg['product_weight_' . $i] = 0;
			$arg['product_tax_' . $i] = round($order->getShippingTaxAmount() * 100, 0);
			$arg['product_taxrate_' . $i] = round($this->_getShippingTaxRate($order) * 100, 0);
			$arg['product_netprice_' . $i] = round($shipping * 100, 0);
			$arg['product_price_' . $i] = round($shiptax * 100, 0);
			$arg['product_nettotal_' . $i] = round($shipping * 100, 0);
			$arg['product_total_' . $i] = round($shiptax * 100, 0);
		}
		
		$fee = Mage::helper('sisow/paymentfee')->getPaymentFeeArray('sisow_'.$method, $this->getQuote());

		if (is_array($fee) && $fee['incl'] > 0) {
			$i++;
			$arg['product_id_' . $i] = 'paymentfee';
			$arg['product_description_' . $i] = Mage::getStoreConfig('payment/sisow_'.$method.'/payment_fee_label'); //'Payment Fee';
			$arg['product_quantity_' . $i] = 1;
			$arg['product_weight_' . $i] = 0;
			$arg['product_tax_' . $i] = round($fee['taxamount'] * 100, 0);
			$arg['product_taxrate_' . $i] = round($fee['rate'] * 100, 0);
			$arg['product_netprice_' . $i] = round($fee['excl'] * 100, 0);
			$arg['product_price_' . $i] = round(($fee['incl']) * 100, 0);
			$arg['product_nettotal_' . $i] = round($fee['excl'] * 100, 0);
			$arg['product_total_' . $i] = round(($fee['incl']) * 100, 0);
		}
		
		$giftCardsAmount = $order->getGiftCardsAmount();
		if ($giftCardsAmount > 0) {
			$i++;
			$giftCardsAmount = -1 * $giftCardsAmount;
			$arg['product_id_' . $i] = 'giftcard';
			$arg['product_description_' . $i] = 'Gift Card';
			$arg['product_quantity_' . $i] = 1;
			$arg['product_weight_' . $i] = 0;
			$arg['product_tax_' . $i] = round(0 * 100, 0);
			$arg['product_taxrate_' . $i] = round(0 * 100, 0);
			$arg['product_netprice_' . $i] = round($giftCardsAmount * 100, 0);
			$arg['product_price_' . $i] = round($giftCardsAmount * 100, 0);
			$arg['product_nettotal_' . $i] = round($giftCardsAmount * 100, 0);
			$arg['product_total_' . $i] = round($giftCardsAmount * 100, 0);
		}
		
		$customerBalance = $order->getCustomerBalanceAmount();
		if ($customerBalance > 0) {
			$i++;
			$customerBalance = -1 * $customerBalance;
			$arg['product_id_' . $i] = 'storecredit';
			$arg['product_description_' . $i] = 'Store Credit';
			$arg['product_quantity_' . $i] = 1;
			$arg['product_weight_' . $i] = 0;
			$arg['product_tax_' . $i] = round(0 * 100, 0);
			$arg['product_taxrate_' . $i] = round(0 * 100, 0);
			$arg['product_netprice_' . $i] = round($customerBalance * 100, 0);
			$arg['product_price_' . $i] = round($customerBalance * 100, 0);
			$arg['product_nettotal_' . $i] = round($customerBalance * 100, 0);
			$arg['product_total_' . $i] = round($customerBalance * 100, 0);
		}
			
		$rewardCurrency = $order->getRewardCurrencyAmount();
		if ($rewardCurrency > 0) {
			$i++;
			$rewardCurrency = -1 * $rewardCurrency;
			$arg['product_id_' . $i] = 'rewardpoints';
			$arg['product_description_' . $i] = 'Reward points';
			$arg['product_quantity_' . $i] = 1;
			$arg['product_weight_' . $i] = 0;
			$arg['product_tax_' . $i] = round(0 * 100, 0);
			$arg['product_taxrate_' . $i] = round(0 * 100, 0);
			$arg['product_netprice_' . $i] = round($rewardCurrency * 100, 0);
			$arg['product_price_' . $i] = round($rewardCurrency * 100, 0);
			$arg['product_nettotal_' . $i] = round($rewardCurrency * 100, 0);
			$arg['product_total_' . $i] = round($rewardCurrency * 100, 0);
		}
		
		$discount = $order->getDiscountAmount();
		if ($discount && $discount < 0) {
			$i++;
			
			$code = $order->getDiscountDescription();
			$title = Mage::helper('sales')->__('Discount (%s)', $code);
			
			$arg['product_id_' . $i] = 'discount';
			$arg['product_description_' . $i] = $title;
			$arg['product_quantity_' . $i] = 1;
			$arg['product_weight_' . $i] = 0;
			$arg['product_tax_' . $i] = round(0 * 100, 0);
			$arg['product_taxrate_' . $i] = round(0 * 100, 0);
			$arg['product_netprice_' . $i] = round($discount * 100, 0);
			$arg['product_price_' . $i] = round($discount * 100, 0);
			$arg['product_nettotal_' . $i] = round($discount * 100, 0);
			$arg['product_total_' . $i] = round($discount * 100, 0);
		}
		
		//additional customer information
		$arg['customer'] = $order->getBillingAddress()->getCustomerId();
		if(isset($_GET['gender']))
			$arg['gender'] = $_GET['gender'];
		if(isset($_GET['dob']))
			$arg['birthdate'] = $_GET['dob'];
		if(isset($_GET['iban']))
			$arg['iban'] = $_GET['iban'];
		if(isset($_GET['bic']))
			$arg['bic'] = $_GET['bic'];

		$arg['ipaddress'] = $_SERVER['REMOTE_ADDR'];
		
		//Klarna
		if( $method == 'klarna' || $method == 'klarnaacc' )
		{
			$arg['directinvoice'] = (Mage::getStoreConfig('payment/sisow_'.$method.'/sendklarnainvoice') == 3) ? 'true' : 'false';
			$arg['mailinvoice'] = (Mage::getStoreConfig('payment/sisow_'.$method.'/sendklarnainvoice') == 3) ? 'true' : 'false';
			
			if($method == 'klarnaacc')
				$arg['pclass'] = $_GET['pclass'];
		}
		
		if(Mage::getStoreConfig('sisow_core/basecurrency'))
			$arg['currency'] = $order->getBaseCurrencyCode();
		else
			$arg['currency'] = $order->getOrderCurrencyCode();		
		
		$arg['tax'] = round( ($order->getBaseTaxAmount() * 100.0) );
		$arg['weight'] = round( ($order->getWeight() * 100.0) );
		$arg['shipping'] = round( ($order->getBaseShippingAmount() * 100.0) );
		
		if($method == 'overboeking')
		{
			$arg['days'] = Mage::getStoreConfig('payment/sisow_'.$method.'/days');
			$arg['including'] = (Mage::getStoreConfig('payment/sisow_'.$method.'/include')) ? 'true' : 'false'; 
		}

		//testmode
		$arg['testmode'] = (Mage::getStoreConfig('payment/sisow_'.$method.'/testmode')) ? 'true' : 'false';
		
		$base = Mage::getModel('sisow/base');
		
		if(!is_object($base) || $method == '')
		{
			if($method == '')
				Mage::log($orderIncrementId . ': No payment method', null, 'log_sisow.log');
			else
				Mage::log($orderIncrementId . ": Sisow model can't be loaded", null, 'log_sisow.log');
		}
		
		$base->payment = $method;
		
		if(isset($_GET['issuer']))
			$base->issuerId = $_GET['issuer'];
			
		if(Mage::getStoreConfig('sisow_core/basecurrency'))
			$base->amount = $order->getBaseGrandTotal();
		else
			$base->amount = $order->getGrandTotal();
		
		if ($method == 'overboeking') {
			$base->purchaseId = $order->getCustomerId() . $orderIncrementId;
			$base->entranceCode = $orderIncrementId;
		}
		else
			$base->purchaseId = $orderIncrementId;
		$base->description =  Mage::getStoreConfig('payment/sisow_'.$method.'/prefix') . $orderIncrementId;
		$base->notifyUrl = Mage::getUrl('sisow/checkout/notify', array('_secure' => true));
		$base->returnUrl = Mage::getUrl('sisow/checkout/return', array('_secure' => true));

		if(($ex = $base->TransactionRequest($arg)) < 0)
		{
			Mage::log($orderIncrementId . ': Sisow TransactionRequest failed('.$ex.', '.$base->errorCode.', '.$base->errorMessage.')', null, 'log_sisow.log');
			if($base->errorCode == 'IBAN')
				Mage::getSingleton('checkout/session')->addError("U heeft een verkeerd IBAN ingevoerd.");
			else if( ($base->payment == 'klarna' || $base->payment == 'klarnaacc') && $base->errorMessage != '')
				Mage::getSingleton('checkout/session')->addError($base->errorMessage);
			else if ($base->payment == 'klarna' || $base->payment == 'klarnaacc' || $base->payment == focum)
				Mage::getSingleton('checkout/session')->addError("Betalen met Achteraf Betalen is op dit moment niet mogelijk, betaal anders.");
			else
				Mage::getSingleton('checkout/session')->addError("Sisow: " . $this->__('No communication')." (". $ex .", ". $base->errorCode . ")");
			
			if(!Mage::getStoreConfig('sisow_core/cancelorder'))
			{
				$order->cancel();
				$order->save();
			}
			
			$url = Mage::getUrl('checkout/cart');
			header('Location: ' . $url);
			exit;
		}
		else
		{
			$transaction_closed = false;
			$transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;

			if(Mage::helper("sisow")->GetNewMailConfig("sisow_".$method) == "after_confirmation")
				$order->sendNewOrderEmail();
			
			if($base->payment == 'overboeking')
			{
				$payment = $order->getPayment();
				$comm = 'Sisow OverBoeking created.<br />';
				$comm .= 'Transaction ID: ' . $base->trxId . '<br/>';
				$st = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
				$payment->setAdditionalInformation('trxId', $base->trxId)
					->setAdditionalInformation('documentId', $base->documentId)
					->save();
				$order->setState($st, $st, $comm);
				$order->save();
								
				$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
				$url = Mage::getUrl("sisow/checkout/success");
			}
			else if($base->payment == 'klarna' || $base->payment == 'klarnaacc' || $base->payment == 'focum')
			{
				$title = '';
				
				switch($base->payment)
				{
					case "klarna":
						$title = 'Sisow Klarna Factuur';
						break;
					case "klarnaacc":
						$title = 'Sisow Klarna Account';
						break;
					case "focum":
						$title = 'Sisow Focum Achteraf Betalen';
						break;
					default:
						$title = 'Sisow achteraf betalen';
						break;
				}
				$state = Mage_Sales_Model_Order::STATE_PROCESSING;
				$payment = $order->getPayment();

				if ($base->pendingKlarna) 
				{
					$state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
					$comm = $title.' Pending.<br />';
					$comm .= 'Transaction ID: ' . $base->trxId . '<br/>';
					if (is_array($fee) && $fee['incl'] > 0) {
						$comm .= $title.' payment fee ' . $fee['incl'] . '<br />';
					}
					if ($method == 'klarnaacc' && $_GET['sisow_monthly']) {
						$comm .= $title.' monthly ' . round($_GET['sisow_monthly'] / 100.0, 2) . '<br/>';
					}
					$st = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
					$payment->setAdditionalInformation('trxId', $base->trxId)->save();
						
					$transaction_closed = false;
					$transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
				}
				else if ($base->invoiceNo) 
				{
					$comm = $title.' invoice created.<br/>';
					$comm .= 'Transaction ID: ' . $base->trxId . '<br/>';
				
					if ($method == 'klarnaacc' && $_GET['sisow_monthly']) {
						$comm .= $title.' monthly ' . round($_GET['sisow_monthly'] / 100.0, 2) . '<br/>';
					}
					$comm .= $title.' invoiceNo ' . $base->invoiceNo . '<br/>';

					$st = Mage::getStoreConfig('sisow_core/status_success');
					if (!$st) {
						$st = Mage_Sales_Model_Order::STATE_PROCESSING;
					}
					
					$payment->setAdditionalInformation('trxId', $base->trxId)
						->setAdditionalInformation('invoiceNo', $base->invoiceNo)
						->setAdditionalInformation('documentId', $base->documentId)
						->save();
					
					$transaction_closed = true;
					$transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
					
					if(Mage::helper("sisow")->GetNewMailConfig($payment->getMethodInstance()->getCode()) == "after_notify_without_cancel" || Mage::helper("sisow")->GetNewMailConfig($payment->getMethodInstance()->getCode()) == "after_notify_with_cancel")
						$order->sendNewOrderEmail();
				}
				else 
				{
					$comm = $text.' reservation created.<br />';
					$comm .= 'Transaction ID: ' . $base->trxId . '<br/>';
					if (is_array($fee) && $fee['incl'] > 0) {
						$comm .= $text.' payment fee ' . $fee['incl'] . '<br />';
					}
					if ($method == 'klarnaacc' && $_GET['sisow_monthly']) {
						$comm .= $text.' monthly ' . round($_GET['sisow_monthly'] / 100.0, 2) . '<br/>';
					}
					$st = Mage::getStoreConfig('sisow_core/status_success');
					if (!$st) {
						$st = Mage_Sales_Model_Order::STATE_PROCESSING;
					}
					$payment->setAdditionalInformation('trxId', $base->trxId)
						->save();
					
					$transaction_closed = true;
					$transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
					
					if(Mage::helper("sisow")->GetNewMailConfig($payment->getMethodInstance()->getCode()) == "after_notify_without_cancel" || Mage::helper("sisow")->GetNewMailConfig($payment->getMethodInstance()->getCode()) == "after_notify_with_cancel")
						$order->sendNewOrderEmail();
				}

				$order->setState($state, $st, $comm);
				$order->save();
				
				if($base->payment == 'focum')
				{
					$mail = (Mage::getStoreConfig('payment/'.$payment->getMethod().'/autoinvoice') > 0) ? Mage::getStoreConfig('payment/'.$payment->getMethod().'/autoinvoice') : Mage::getStoreConfig('sisow_core/autoinvoice');
					
					if($mail > 1)
					{
						if ($order->canInvoice()) {
							$invoice = $order->prepareInvoice();
							$invoice->register()->capture();
							$invoice->setTransactionId($trxid);
							Mage::getModel('core/resource_transaction')
									->addObject($invoice)
									->addObject($invoice->getOrder())
									->save();

							if ($mail == 3) {
								$invoice->sendEmail();
								$invoice->setEmailSent(true);
							}
							$invoice->save();
						}
					}
				}
				
				$url = Mage::getUrl("sisow/checkout/success");
			}
			else
			{
				$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
				$url = $base->issuerUrl;
			}
			
			if(isset($base->trxId) && $base->trxId != '')
			{
				$order->getPayment()->setAdditionalInformation('trxId', $base->trxId)->save();
				
				$transaction = Mage::getModel('sales/order_payment')
							->setMethod('sisow_'.$method)
							->setTransactionId($base->trxId)
							->setIsTransactionClosed($transaction_closed);
							
				$order->setPayment($transaction);
				$transaction->addTransaction($transaction_type);	
			}
			
			if(($method == 'klarna' || $method == 'klarnaacc') && !$base->pendingKlarna)
			{
				$mail = (Mage::getStoreConfig('payment/'.$payment->getMethod().'/autoinvoice') > 0) ? Mage::getStoreConfig('payment/'.$payment->getMethod().'/autoinvoice') : Mage::getStoreConfig('sisow_core/autoinvoice');
				if($mail > 1)
				{
					if ($order->canInvoice()) {
						$invoice = $order->prepareInvoice();
						$invoice->register()->capture();
						$invoice->setTransactionId($trxid);
						Mage::getModel('core/resource_transaction')
								->addObject($invoice)
								->addObject($invoice->getOrder())
								->save();

						if ($mail == 3) {
							$invoice->sendEmail();
							$invoice->setEmailSent(true);
						}
						$invoice->save();
					}
				}
			}
			
			$order->save();		
			header('Location: ' . $url);
			exit;
		}
    }
	
	private function _getShippingTaxRate($order)
	{
        // Load the customer so we can retrevice the correct tax class id
       	$customer = Mage::getModel('customer/customer')
            ->load($order->getCustomerId());
       	$taxClass = Mage::getStoreConfig(
            'tax/classes/shipping_tax_class',
       	    $order->getStoreId()
        );
       	$calculation = Mage::getSingleton('tax/calculation');
        $request = $calculation->getRateRequest(
       	    $order->getShippingAddress(),
            $order->getBillingAddress(),
       	    $customer->getTaxClassId(),
            Mage::app()->getStore($order->getStoreId())
       	);
        return $calculation->getRate($request->setProductClassId($taxClass));
	}
}
?>