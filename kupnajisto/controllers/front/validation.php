<?php

class KupNajistoValidationModuleFrontController extends ModuleFrontController
{
	private function _isValidPhone($phone)
	{
		return preg_match(PHONE_REGEX, $phone);
	}

	private function _getOrderId($id_cart)
	{
		$sql = 'SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_cart = '.(int)$id_cart;
		return Db::getInstance()->getValue($sql);		
	}

	private function _createPrestashopOrder($cart)
	{
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || $cart->id_carrier == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'kupnajisto')
			{
				$authorized = true;
				break;
			}
		if (!$authorized)
			die($this->module->l('This payment method is not available.', 'payment'));

		$new_customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($new_customer))
			Tools::redirect('index.php?controller=order&step=1');

		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

		// Validate order
		$this->module->validateOrder($cart->id, ORDER_STATUS_PROCESSING, $total, $this->module->displayName, NULL, NULL, (int)$currency->id, false, $new_customer->secure_key);
	}

	private function _createKupNajistoOrder($cart, $phone, $carrier)
	{
		$id_order = $this->_getOrderId($cart->id);
		$obj_order = new Order($id_order);
		$customer_obj = $this->context->customer;
		$address_invoice = new Address($cart->id_address_invoice);
		$address_delivery = new Address($cart->id_address_delivery);
		$products = $cart->getProducts(true);

		// Create kup najisto order
		$customer = array(
		    'name' => $customer_obj->firstname.' '.$customer_obj->lastname,
		    'email' => $customer_obj->email
		);

		$billing_street = $address_invoice->address1;
		if (!empty($address_invoice->address2))
			$billing_street .= ' '.$address_invoice->address2;
		$billing_address = array(
		    'name' => $customer_obj->firstname.' '.$customer_obj->lastname,
		    'country' => $address_invoice->country,
		    'street' => $billing_street,
		    'zip_code' => $address_invoice->postcode,
		    'city' => $address_invoice->city
		);

		$delivery_street = $address_delivery->address1;
		if (!empty($address_delivery->address2))
			$delivery_street .= ' '.$address_delivery->address2;
		$delivery_address = array(
		    'name' => $customer_obj->firstname.' '.$customer_obj->lastname,
		    'country' => $address_delivery->country,
		    'street' => $delivery_street,
		    'zip_code' => $address_delivery->postcode,
		    'city' => $address_delivery->city
		);

		$items = array();

		foreach ($products as $product) 
		{
			$items[] = array(
			    'code' => $product['id_product'],
			    'title' => $product['name'],
			    'price' => $product['total_wt'],
			    'amount' => (int)$product['cart_quantity'],
			    'state' => 1				
			);
		}

		$orderData = array(
		    'customer' => $customer,
		    'billing_address' => $billing_address,
		    'delivery_address' => $delivery_address,
		    'items' => $items,
		    'total_price' => round($cart->getOrderTotal(true), 2),
		    'ext_id' => $id_order,
		    'ext_variable_symbol' => $obj_order->reference,
		    'phone' => $phone,
		    'delivery_carrier' => $carrier,
		    'delivery_state' => 1,
		    'ip_address' => $_SERVER['REMOTE_ADDR'],
		    'paygate' => true,
		    'success_url' => $this->context->link->getModuleLink('kupnajisto', 'success'), 
		    'failure_url' => $this->context->link->getModuleLink('kupnajisto', 'failure'), 
		    'callback_url' => $this->context->link->getModuleLink('kupnajisto', 'callback')
		);

		// API
		try 
		{
			// Login
		    $config = Configuration::getMultiple(array('KUPNAJISTO_MAIL', 'KUPNAJISTO_PASSWORD', 'KUPNAJISTO_API_URL'));
		    $api = new KupNajistoApi($config['KUPNAJISTO_MAIL'], $config['KUPNAJISTO_PASSWORD'], $config['KUPNAJISTO_API_URL']);

		    // Create order
		    $response = $api->createOrder($orderData);

		    // Update order with kupnajisto id
			Db::getInstance()->update(
				'orders', 
				array(
					'id_kupnajisto' => (int)$response['id']
				),
				'id_order = '.(int)$id_order
			);
   
   			// Redirect to paygate
		 	Tools::redirect($config['KUPNAJISTO_API_URL'].$response['paygate_url']);
		} 
		catch (KupNajistoException $e) 
		{
			// Cancel order //TODO: would be better: delete created order and re-create the same cart
			$obj_order = new Order($id_order);
			$history = new OrderHistory();
			$history->id_order = (int)$obj_order->id;
			$history->changeIdOrderState(ORDER_STATUS_CANCELLED, (int)($obj_order->id));

			$this->context->smarty->assign(array(
				'error' => $e->getMessage()
			));

		    $this->setTemplate('error.tpl');
		}
	}

	private function _mapCarrier($carrier_ps)
	{
		if ($carrier_ps == PS_CARRIER_MY_CARRIER)
			return KN_CARRIER_PPL;
		return KN_CARRIER_PERSONAL_COLLECTION_AT_BRANCH;
	}

	public function postProcess()
	{
		$cart = $this->context->cart;
		$carrier = $this->_mapCarrier($cart->id_carrier);
		$phone = Tools::getValue('phone_input');		
		if (!$this->_isValidPhone($phone) || $carrier > 11 || $carrier < 1)
			Tools::redirect('index.php?controller=order&step=1');

		$this->_createPrestashopOrder($cart);
		$this->_createKupNajistoOrder($cart, $phone, $carrier);
	}
}
