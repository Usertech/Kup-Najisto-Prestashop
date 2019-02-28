<?php

class KupNajistoCallbackModuleFrontController extends ModuleFrontController
{

	private function _getPrestashopOrderId($id_kupnajisto)
	{
		$sql = 'SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_kupnajisto = '.(int)$id_kupnajisto;
		return Db::getInstance()->getValue($sql);
	}


	public function postProcess()
	{
		$post = json_decode(file_get_contents('php://input'), true);
		$id_kupnajisto = $post['id'];

		if ($id_kupnajisto)
		{		
			$id_order = $this->_getPrestashopOrderId($id_kupnajisto);
			if ($id_order)
			{
				// API
				try
				{
					// Login
					$config = Configuration::getMultiple(array('KUPNAJISTO_MAIL', 'KUPNAJISTO_PASSWORD', 'KUPNAJISTO_API_URL'));
					$api = new KupNajistoApi($config['KUPNAJISTO_MAIL'], $config['KUPNAJISTO_PASSWORD'], $config['KUPNAJISTO_API_URL']);

				    // GET
					$response = $api->requestOrder($id_kupnajisto);

					// Get order history
					$obj_order = new Order($id_order);
					$history = new OrderHistory();
					$history->id_order = (int)$obj_order->id;

					// Confirm and change order status to Accepted
					if ($response['state'] == STATE_APPROVED) 
					{
						$response = $api->confirmOrder($id_kupnajisto);
						$history->changeIdOrderState(ORDER_STATUS_ACCEPTED, (int)($obj_order->id)); 
					}

					// Change order status to Cancelled
					elseif ($response['state'] == STATE_REJECTED)
					{
						$history->changeIdOrderState(ORDER_STATUS_CANCELLED, (int)($obj_order->id));
						//TODO: Inform customer that the payment is rejected (via mail?)
					}
					
					// Change order status to Cancelled //TODO: KN STATE 5 (gateway timeout), after the timeout is callback called? (Most probably not -> Cancel order)
					else
						$history->changeIdOrderState(ORDER_STATUS_CANCELLED, (int)($obj_order->id));
				}
				//TODO: handle error?
				catch (KupNajistoException $e)
				{
				    //echo $e->getMessage();
				}
			}
		}
	}
}
