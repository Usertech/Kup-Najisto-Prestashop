<?php

class KupNajistoPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;
	private $phone_tpl;

	private function _checkPhone($mobile, $phone)
	{
		if (empty($mobile) && empty($phone))
			throw new Exception('Phone not set', PHONE_NOT_SET);
		else
		{
			if (!empty($mobile)) 
				$phone = $mobile;
			$this->phone_tpl = $phone;
			if (!preg_match(PHONE_REGEX, $phone))
				throw new Exception('Phone not valid', PHONE_NOT_VALID);
		}
	}

	public function initContent()
	{
		parent::initContent();

		// Load objects
		$cart = $this->context->cart;
		$customer = $this->context->customer;
		$address_invoice = new Address($cart->id_address_invoice);
		$address_delivery = new Address($cart->id_address_delivery);

		// Check invoice address phone
		$phone_status = PHONE_OK;
		try
		{
			$this->_checkPhone($address_invoice->phone_mobile, $address_invoice->phone);
		}
		catch(Exception $e)
		{
			$phone_status = $e->getCode();
		}

		// If invoice phone not set, check delivery address phone
		if ($phone_status == PHONE_NOT_SET)
		{ 
			try
			{
				$this->_checkPhone($address_delivery->phone_mobile, $address_delivery->phone);
			}
			catch(Exception $e)
			{
				$phone_status = $e->getCode();
			}
		}

		$this->context->smarty->assign(array(
			'num_products' => $cart->nbProducts(),
			'total' => $cart->getOrderTotal(true),
			'phone_status' => $phone_status,
			'phone' => $this->phone_tpl,
			'this_path' => $this->module->getPathUri(),
			'this_path_bw' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('payment_execution.tpl');
	}
}
