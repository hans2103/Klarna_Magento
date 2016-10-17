<?php
class Sisow_Helper_Paymentfee extends Mage_Payment_Helper_Data
{
	/**
     * getPaymentFeeAmount
     * Return string value payment fee inlcuding tax
     * @return string
     */
	public function getPaymentFeeArray($paymentcode, $quote)
	{
		$inctax 				= Mage::getStoreConfig('payment/'.$paymentcode.'/payment_fee_inc_ex');
		$paymentfee 			= Mage::getStoreConfig('payment/'.$paymentcode.'/payment_fee');
		$paymentfee_taxclass	= Mage::getStoreConfig('payment/'.$paymentcode.'/payment_fee_tax');
		
		if($paymentfee == '')
		{
			Mage::getSingleton('core/session')->setSisowFeeInc(0);
			return;
		}
		
		if( Mage::getSingleton('core/session')->getSisowTotal() > 0)
		{			
			if( $quote->getGrandTotal()  > 0 && Mage::getSingleton('core/session')->getSisowTotal() <> $quote->getGrandTotal() )
			{
				Mage::getSingleton('core/session')->setSisowTotal($quote->getGrandTotal());
				$order_total = Mage::getSingleton('core/session')->getSisowTotal();
			}
			$order_total = Mage::getSingleton('core/session')->getSisowTotal();
		}
		else
		{
			Mage::getSingleton('core/session')->setSisowTotal($quote->getGrandTotal());
			$order_total = $quote->getGrandTotal();
		}
		
		$charge = 0;
		if(strpos($paymentfee, ';') > 0)
		{
			$fees = explode(";", $paymentfee);
			if($fees[0] > 0)
				$charge += $fees[0];
			else
				$charge += $order_total * (($fees[0] * -1) / 100.0);
			
			if($fees[1] > 0)
				$charge += $fees[1];
			else
				$charge += $order_total * (($fees[1] * -1) / 100.0);
		}
		else if ($paymentfee > 0) {
			$charge = $paymentfee;
		} else if ($paymentfee < 0) {
			$charge = $order_total * (($paymentfee * -1) / 100.0);
		}	
		
		//Get the correct rate to use
		$address = $quote->getShippingAddress();
		$taxClassId = $quote->getCustomerTaxClassId();
		
        $store = Mage::app()->getStore();
        $calc = Mage::getSingleton('tax/calculation');
        $rateRequest = $calc->getRateRequest($address, $address, $taxClassId, $store);
        $rateRequest->setProductClassId($paymentfee_taxclass);
        $rate = $calc->getRate($rateRequest);
		
		if($inctax == '1')
		{
			$value = $calc->calcTaxAmount($charge, $rate, true, false);
            $excl = ($charge - $value);
            $feeArray =  array(
                'excl' => $excl,
                'base_excl' => $this->calcBaseValue($excl),
                'incl' => $charge,
                'base_incl' => $this->calcBaseValue($charge),
                'taxamount' => $value,
                'base_taxamount' => $this->calcBaseValue($value),
                'rate' => $rate
            );
		}
		else
		{
			//Fee entered without tax
			$value = $calc->calcTaxAmount($charge, $rate, false, false);
			$incl = ($charge + $value);

			$feeArray = array(
				'excl' => $charge,
				'base_excl' => $this->calcBaseValue($charge),
				'incl' => $incl,
				'base_incl' => $this->calcBaseValue($incl),
				'taxamount' => $value,
				'base_taxamount' => $this->calcBaseValue($value),
				'rate' => $rate
			);
		}
		
		Mage::getSingleton('core/session')->setSisowFeeInc($feeArray['incl']);
		return $feeArray;
	}
	
	public function addToBlock($block)
    {

        $order = $block->getOrder();
        $info = $order->getPayment()->getMethodInstance()->getInfoInstance();
        $storeId = Mage::app()->getStore()->getId();
        $taxOption = Mage::getStoreConfig("tax/sales_display/shipping", $storeId);
        $country = $order->getShippingAddress()->getCountry();
        //$lang = Mage::helper('klarnaPaymentModule/lang');
		$label = Mage::getStoreConfig('payment/'.$order->getPayment()->getMethod().'/payment_fee_label');
		
        $paymentFee = $info->getAdditionalInformation('invoice_fee');
        $basePaymentFee = $info->getAdditionalInformation('base_invoice_fee');
        $paymentFeeExcludingVat = $info->getAdditionalInformation('invoice_fee_exluding_vat');
        $basePaymentFeeExcludingVat = $info->getAdditionalInformation('base_invoice_fee_exluding_vat');

        /**
         * 1 : Show exluding tax
         * 2 : Show including tax
         * 3 : Show both
         */
		 
        if (($taxOption === '1') || ($taxOption === '3')) {
			$label = Mage::getStoreConfig('payment/'.$order->getPayment()->getMethod().'/payment_fee_label');
            $fee = new Varien_Object();
            $fee->setCode('invoice_fee_excl');
            if ($taxOption == '3') {
                $label .= ' (Excl.Tax)';
            }
            $fee->setLabel($label);
            $fee->setBaseValue($basePaymentFeeExcludingVat);
            $fee->setValue($paymentFeeExcludingVat);
            $block->addTotalBefore($fee, 'shipping');
        }
        if (($taxOption === '2') || ($taxOption === '3')) {
			$label = Mage::getStoreConfig('payment/'.$order->getPayment()->getMethod().'/payment_fee_label');
            $fee = new Varien_Object();
            $fee->setCode('invoice_fee_incl');
            if ($taxOption == '3') {
                $label .= ' (Incl.Tax)';
            }
            $fee->setLabel($label);
            $fee->setBaseValue($basePaymentFee);
            $fee->setValue($paymentFee);
            $block->addTotalBefore($fee, 'shipping');
        }

        return $block;
    }
	
	/**
     * Try to calculate the value of the payment fee with the base currency
     * of the store if the purchase was done with a different currency.
     *
     * @param float $value value to calculate on
     *
     * @return float
     */
    private function calcBaseValue($value) {
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        if ($currentCurrencyCode !== $baseCurrencyCode) {
            $currencyModel = Mage::getModel('directory/currency');
            $currencyRates = $currencyModel->getCurrencyRates($baseCurrencyCode, array($currentCurrencyCode));
            return ($value / $currencyRates[$currentCurrencyCode]);
        }
        return $value;
    }
}
?>