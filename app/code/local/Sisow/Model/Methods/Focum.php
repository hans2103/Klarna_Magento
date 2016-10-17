<?php
class Sisow_Model_Methods_Focum extends Sisow_Model_Methods_Abstract
{
    protected $_code = 'sisow_focum'; //sisow = modulenaam, ideal = paymentcode sisow
	protected $_paymentcode = 'focum';
	
	//blocks for loading templates in checkout
    protected $_formBlockType = 'sisow/paymentmethod_focum';
    protected $_infoBlockType = 'sisow/paymentmethod_defaultInfo';
	
	protected $_isGateway 				= true;
	protected $_canUseCheckout          = true;
	
	public function getOrderPlaceRedirectUrl()
    {
		$gender = $_POST['payment']['sisow_gender'];
		$phone = $_POST['payment']['sisow_phone'];
		$day = $_POST['payment']['sisow_day'];
		$month = $_POST['payment']['sisow_month'];
		$year = $_POST['payment']['sisow_year'];
		$iban = $_POST['payment']['sisow_iban'];
		
		$dob = $day . $month . $year;
		
		/*
		 * Redirect to Sisow
		 * method = paymentcode from Sisow
		 * additional params (fields from the checkout page)
		*/
		$url = Mage::getUrl('sisow/checkout/redirect/', array('_secure' => true));
		if (!strpos($url, "?")) $url .= '?';
		else $url .= '&';
		$url .= 'method=focum';
		$url .= '&gender='.$gender;
		$url .= '&phone='.$phone;
		$url .= '&dob='.$dob;
		$url .= '&iban='.$iban;
		
		return $url;
    }
	
	public function getPhone()
    {
		$phone = $this->getQuote()->getBillingAddress()->getTelephone();
		if (!$phone && $this->getQuote()->getShippingAddress())
			$phone = $this->getQuote()->getShippingAddress()->getTelephone();
		return $phone;
	}
}	