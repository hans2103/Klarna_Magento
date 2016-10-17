<?php
/**
 * Payment fee Credit memo
 *
 * Class to handle the payment fee on a Credit memo
 *
 */

class Sisow_Model_Paymentfee_Creditmemo_Total extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{

    /**
     * Collect the order total
     *
     * @param object $creditmemo The Creditmemo instance to collect from
     *
     * @return Mage_Sales_Model_Order_Creditmemo_Total_Abstract
     */
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $method = $creditmemo->getOrder()->getPayment()->getMethodInstance();

        if (substr($method->getCode(), 0, 5) != 'sisow') {
            return $this;
        }

        $info = $method->getInfoInstance();

        if (!$info) {
            return $this;
        }

        $invoiceFee =  $info->getAdditionalInformation('invoice_fee');
        $baseInvoiceFee =  $info->getAdditionalInformation('base_invoice_fee');

        if (!$invoiceFee) {
            return $this;
        }

        $creditmemo->setBaseGrandTotal(
            ($creditmemo->getBaseGrandTotal() + $baseInvoiceFee)
        );
        $creditmemo->setGrandTotal(
            ($creditmemo->getGrandTotal() + $invoiceFee)
        );

        $creditmemo->setBaseInvoiceFee($baseInvoiceFee);
        $creditmemo->setInvoiceFee($invoiceFee);
		
		$tax =  $info->getAdditionalInformation('invoice_tax_amount');
        $baseTax = $info->getAdditionalInformation('base_invoice_tax_amount');

        if (!$tax) {
            return $this;
        }

        $creditmemo->setBaseTaxAmount(
            $creditmemo->getBaseTaxAmount() + $baseTax
        );
        $creditmemo->setTaxAmount(
            $creditmemo->getTaxAmount() + $tax
        );

        return $this;
    }
}
