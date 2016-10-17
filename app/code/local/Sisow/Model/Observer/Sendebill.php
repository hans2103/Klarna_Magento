<?php
class Sisow_Model_Observer_Sendebill
{
	public function sendEbill(Varien_Event_Observer $observer)
	{
		$order = $observer->getEvent()->getOrder();
		
		if( $order->getPayment()->getMethodInstance()->getCode() == 'sisow_ebill' || $order->getPayment()->getMethodInstance()->getCode() == 'sisow_overboeking')
		{
			if(!Mage::app()->getStore()->isAdmin())
				return $this;
			
			$arg = array();
			$base = Mage::getModel('sisow/base');
			
			$base->payment = $order->getPayment()->getMethodInstance()->getCode() == 'sisow_ebill' ? 'ebill' : 'overboeking';
			
			$arg['billing_firstname'] = $order->getBillingAddress()->getFirstname();
			$arg['billing_lastname'] = $order->getBillingAddress()->getLastname();
			$arg['billing_countrycode'] = $order->getBillingAddress()->getCountry();
			$arg['testmode'] = (Mage::getStoreConfig('payment/'.$order->getPayment()->getMethodInstance()->getCode().'/testmode')) ? 'true' : 'false';
			
			
			if($order->getPayment()->getMethodInstance()->getCode() == 'sisow_overboeking')
			{
				$arg['days'] = Mage::getStoreConfig('payment/sisow_overboeking/days');
				$arg['including'] = (Mage::getStoreConfig('payment/sisow_overboeking/include')) ? 'true' : 'false'; 
			}
			else
				$arg['days'] = Mage::getStoreConfig('payment/sisow_ebill/days');
			
			$arg['billing_mail'] = $order->getBillingAddress()->getEmail();
			if(empty($arg['billing_mail']))
				$arg['billing_mail'] = $order->getCustomerEmail();
			
			$base->amount = round($order->getGrandTotal(), 2);
			$base->purchaseId = $order->getCustomerId() . $order->getRealOrderId();
			$base->entranceCode = str_replace('-', '', $order->getRealOrderId());
			
			$base->description = $order->getRealOrderId();
			
			$invalidchar = strpos($order->getRealOrderId(), '-') !== FALSE ? 'true' : 'false';
			$base->notifyUrl = Mage::getUrl('sisow/checkout/notify', array('_secure' => true, 'invalidchar' => $invalidchar));
			$base->returnUrl = Mage::getBaseUrl();
			
			if( ($ex = $base->TransactionRequest($arg)) < 0)
			{
				Mage::getSingleton('adminhtml/session')->addError( 'Sisow error: ' . $ex . ', ' . $base->errorCode );
				return $this;
			}	
			
			$payment = $order->getPayment();
			$comm = 'Sisow Ebill created.<br />';
			$comm .= 'Transaction ID: ' . $base->trxId . '<br/>';
			$st = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
			$payment->setAdditionalInformation('trxId', $base->trxId)
				->setAdditionalInformation('documentId', $base->documentId)
				->save();
			$order->setState($st, $st, $comm);
			$order->save();
							
			$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
			$order->getPayment()->setAdditionalInformation('trxId', $base->trxId)->save();
				
			$transaction = Mage::getModel('sales/order_payment')
						->setMethod('sisow_'.$method)
						->setTransactionId($base->trxId)
						->setIsTransactionClosed(false);
						
			$order->setPayment($transaction);
			$transaction->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);	
			$order->save();	
			
			Mage::getSingleton('adminhtml/session')->addSuccess( 'The Ebill/Overboeking has been created and send to the customer.' );
		}
		
		return $this;
	}
}
?>