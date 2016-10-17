<?php
abstract class Sisow_Model_Methods_Abstract extends Mage_Payment_Model_Method_Abstract
{
	protected $_isGateway               = false;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
	protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = false;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc 				= false;
	
	public function getQuote() {
        return $this->getCheckout()->getQuote();
    }

    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }
	
	public function getOrderPlaceRedirectUrl()
    {		
		/*
		 * Redirect to Sisow
		 * method = paymentcode from Sisow
		 * additional params (fields from the checkout page)
		*/
		$url = Mage::getUrl('sisow/checkout/redirect/', array('_secure' => true));
		if (!strpos($url, "?")) $url .= '?';
		else $url .= '&';
		$url .= 'method='.$this->_paymentcode;
		return $url;
    }
	
	public function getPaymentInstructions()
	{
		return Mage::getStoreConfig('payment/sisow_'.$this->_paymentcode.'/instructions');
	}
		
	public function getFeeArray()
	{
		return Mage::helper('sisow/Paymentfee')->getPaymentFeeArray($this->_code, $this->getQuote());
	}
		
	public function capture(Varien_Object $payment, $amount)
    {		
        return $this;
    }
	
	/**
     * Refund a capture transaction
     *
     * @param Varien_Object $payment
     * @param float $amount
     */
    public function refund(Varien_Object $payment, $amount)
    {
		$trxid = $this->_getParentTransactionId($payment);

		if($trxid)
		{
			$base = Mage::getModel('sisow/base');
			$base->amount = $amount;
			if(($ex = $base->RefundRequest($trxid)) < 0)
			{
				Mage::log($trxid . ': Sisow RefundRequest failed('.$ex.', '.$base->errorCode.', '.$base->errorMessage.')', null, 'log_sisow.log');
			}
			else
			{	
				$order = $payment->getOrder();
				$transaction = Mage::getModel('sales/order_payment')
							->setMethod($this->_code)
							->setTransactionId($ex)
							->setIsTransactionClosed(true);
							
				$order->setPayment($transaction);
				$transaction->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
						
				$order->save();	
			}
		}
		else
		{
			Mage::log($trxid . ': refund failed no transactionId found', null, 'log_sisow.log');
			Mage::throwException(Mage::helper('sisow')->__("Impossible to issue a refund transaction because the transactionId can't be loaded."));
		}
		
		return $this;
	}
	
	public function processInvoice($invoice, $payment)
    {
		$info = $payment->getMethodInstance()->getInfoInstance();
		$trxid = $info->getAdditionalInformation('trxId');
		
		$invoice->setTransactionId($trxid);
		$invoice->save();
		if($this->_paymentcode == 'klarna' || $this->_paymentcode == 'klarnaacc')
		{		
			if(Mage::getStoreConfig('payment/sisow_'.$this->_paymentcode.'/sendklarnainvoice') > 1)
			{		
				$base = Mage::getModel('sisow/base');
				if( ($ex = $base->InvoiceRequest($trxid)) < 0)
				{
					Mage::getSingleton('adminhtml/session')->addSuccess( Mage::helper('sisow')->__("Klarna Invoice can't be created") . '!' );
				}
				else
				{
					Mage::getSingleton('adminhtml/session')->addSuccess( Mage::helper('sisow')->__("Klarna Invoice created") . '!' );
										
					$comm = $this->_paymentcode == 'focum' ? "Sisow: Focum Factuur aangemaakt. <br/>" : "Sisow: Klarna Factuur aangemaakt. <br/>";
					if(!empty($base->invoiceNo))
					{
						$comm .= 'Factuurnummer: ' . $base->invoiceNo . '. <br/>';
						if($this->_paymentcode == 'klarna' || $this->_paymentcode == 'klarnaacc')
							$comm .= "<a href=\"https://online.klarna.com/invoices/".$base->invoiceNo.".pdf\" target=\"_blank\">Open Klarna Factuur</a>";
					}
					$order = $invoice->getOrder();
					$order->addStatusHistoryComment($comm);
					$order->save();
				}
			}
		}
		
        return $this;
    }
	
	protected function _getParentTransactionId(Varien_Object $payment)
    {
        return $payment->getParentTransactionId() ? $payment->getParentTransactionId() : $payment->getLastTransId();
    }
}
?>