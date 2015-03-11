<?php   
if (!defined('_PS_VERSION_'))
  exit;

error_reporting(E_ALL);
ini_set('display_errors', 'on');

define('PHONE_REGEX', '/^0{2}[0-9]{12}$|^[0-9]{9}$|^\+[0-9]{12}$/');
define('PHONE_OK', 0);
define('PHONE_NOT_SET', 1);
define('PHONE_NOT_VALID', 2);
define('STATE_APPROVED', 3);
define('STATE_REJECTED', 4);
define('DELIVERY_STATE_SENT', 4);
define('DELIVERY_STATE_CANCELLED', 3);
define('ORDER_STATUS_ACCEPTED', 2);
define('ORDER_STATUS_PROCESSING', 3);
define('ORDER_STATUS_DELIVERED', 5);
define('ORDER_STATUS_CANCELLED', 6);
define('KN_CARRIER_PPL', 3);
define('KN_CARRIER_PERSONAL_COLLECTION_AT_BRANCH', 10);
define('PS_CARRIER_MY_CARRIER', 2);


require_once($_SERVER['DOCUMENT_ROOT'].'/modules/kupnajisto/vendor/KupNajistoApi.php');
require_once('config.php');

class KupNajisto extends PaymentModule
{
	public $mail;
	public $password;
	private $_postErrors = array();
	private $_html = '';

	public function __construct()
	{
		// Module info
		$this->name = 'kupnajisto';
		$this->tab = 'payments_gateways';
		$this->version = '0.1';
		$this->author = 'Kup Najisto';
		$this->need_instance = 1; 
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Kup Najisto');
		$this->description = $this->l('Get your products at home and pay up to 2 weeks later');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		// API values
		Configuration::updateValue('KUPNAJISTO_API_URL', API_URL);
		$config = Configuration::getMultiple(array('KUPNAJISTO_MAIL', 'KUPNAJISTO_PASSWORD'));
		if (!empty($config['KUPNAJISTO_MAIL']))
			$this->mail = $config['KUPNAJISTO_MAIL'];
		if (!empty($config['KUPNAJISTO_PASSWORD']))
			$this->password = $config['KUPNAJISTO_PASSWORD'];

		// Check configuration
		if (!isset($this->mail) || !isset($this->password))
			$this->warning = $this->l('The "email" and "password" fields must be configured before using this module.');

		// Activate RMA (return products)
		Configuration::updateValue('PS_ORDER_RETURN', 1);
	}

	public function install()
	{
		if (!parent::install() ||
			!$this->alterTable('add') ||
			!$this->registerHook('payment') ||
			!$this->registerHook('paymentReturn') ||
			!$this->registerHook('actionOrderStatusPostUpdate'))
				return false;
		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall() ||
			!$this->alterTable('remove') ||
			!Configuration::deleteByName('KUPNAJISTO_MAIL') ||
			!Configuration::deleteByName('KUPNAJISTO_PASSWORD') ||
			!Configuration::deleteByName('KUPNAJISTO_API_URL'))
				return false;
		return true;
	}

	// Add kupnajisto id to the orders table
	public function alterTable($method)
	{
	    switch ($method) {
	        case 'add':
	            $sql = 'ALTER TABLE '._DB_PREFIX_.'orders ADD `id_kupnajisto` INT NOT NULL';
	            break;
	         
	        case 'remove':
	            $sql = 'ALTER TABLE '._DB_PREFIX_.'orders DROP COLUMN `id_kupnajisto`';
	            break;
	    }
	     
	    return Db::getInstance()->Execute($sql);
	}

	// Validate configuration
	private function _postValidation()
	{
		if (Tools::isSubmit('submit'.$this->name))
		{
			if (!Tools::getValue('KUPNAJISTO_MAIL'))
				$this->_postErrors[] = $this->l('Email is required.');
			elseif (!filter_var(strval(Tools::getValue('KUPNAJISTO_MAIL')), FILTER_VALIDATE_EMAIL))
				$this->_postErrors[] = $this->l('Invalid email address');
			elseif (!Tools::getValue('KUPNAJISTO_PASSWORD'))
				$this->_postErrors[] = $this->l('Password is required.');
			else
			{
				try
				{
				    $login = new KupNajistoApi(Tools::getValue('KUPNAJISTO_MAIL'), Tools::getValue('KUPNAJISTO_PASSWORD'), Configuration::get('KUPNAJISTO_API_URL'));
				}
				catch(KupNajistoException $e)
				{
					$this->_postErrors[] = $this->l('Email or password incorrect.');
				}
			}
		}
	}

	// Update configuration
	private function _postProcess()
	{
		if (Tools::isSubmit('submit'.$this->name))
		{
			Configuration::updateValue('KUPNAJISTO_MAIL', Tools::getValue('KUPNAJISTO_MAIL'));
			Configuration::updateValue('KUPNAJISTO_PASSWORD', Tools::getValue('KUPNAJISTO_PASSWORD'));
		}
		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}

	// Configuration handler
	public function getContent()
	{
		if (Tools::isSubmit('submit'.$this->name))
		{
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= $this->displayError($err);
		}
		else
			$this->_html .= '<br />';

		$this->_html .= $this->displayForm();
	    return $this->_html;
	}

	public function displayForm()
	{
	    // Get default language
	    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
	     
	    // Init Fields form array
	    $fields_form[0]['form'] = array(
	        'legend' => array(
	            'title' => $this->l('Settings'),
	        ),
	        'input' => array(
	            array(
	                'type' => 'text',
	                'label' => $this->l('Email'),
	                'name' => 'KUPNAJISTO_MAIL',
	                'required' => true
	            ),
	           	array(
	                'type' => 'password',
	                'label' => $this->l('Password'),
	                'name' => 'KUPNAJISTO_PASSWORD',
	                'required' => true,
	                'autocomplete' => false
	            ),
	        ),
	        'submit' => array(
	            'title' => $this->l('Save'),
	            'class' => 'button'
	        )
	    );
	     
	    $helper = new HelperForm();
	     
	    // Module, token and currentIndex
	    $helper->module = $this;
	    $helper->name_controller = $this->name;
	    $helper->token = Tools::getAdminTokenLite('AdminModules');
	    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
	     
	    // Language
	    $helper->default_form_language = $default_lang;
	    $helper->allow_employee_form_lang = $default_lang;
	     
	    // Title and toolbar
	    $helper->title = $this->displayName;
	    $helper->show_toolbar = true;        
	    $helper->toolbar_scroll = true; 
	    $helper->submit_action = 'submit'.$this->name;
	    $helper->toolbar_btn = array(
	        'save' =>
	        array(
	            'desc' => $this->l('Save'),
	            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
	            '&token='.Tools::getAdminTokenLite('AdminModules'),
	        ),
	        'back' => array(
	            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
	            'desc' => $this->l('Back to list')
	        )
	    );
	     
	    // Load current value
	    $helper->fields_value['KUPNAJISTO_MAIL'] = Configuration::get('KUPNAJISTO_MAIL');
	    $helper->fields_value['KUPNAJISTO_PASSWORD'] = Configuration::get('KUPNAJISTO_PASSWORD');
	     
	    return $helper->generateForm($fields_form);
	}

	// Display kupnajisto payment method to the customer
	public function hookPayment($params)
	{
		if (!$this->active)
			return;

		$this->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}

	// NOT USED. Using paygate
	public function hookPaymentReturn($params)
	{

	}

	private function _getPaymentModule($id_order)
	{
		$sql = 'SELECT module FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int)$id_order;
		return Db::getInstance()->getValue($sql);
	}

	private function _getKupnajistoID($id_order)
	{
		$sql = 'SELECT id_kupnajisto FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int)$id_order;
		return Db::getInstance()->getValue($sql);
	}

	private function _changeDeliveryStateKupnajisto($new_order_status, $id_order)
	{
		if ($new_order_status == ORDER_STATUS_DELIVERED || $new_order_status == ORDER_STATUS_CANCELLED)
		{
			$id_kupnajisto = $this->_getKupnajistoID($id_order);

			if ($new_order_status == ORDER_STATUS_DELIVERED)
				$delivery_state = DELIVERY_STATE_SENT;

			elseif ($new_order_status == ORDER_STATUS_CANCELLED)
				$delivery_state = DELIVERY_STATE_CANCELLED;

			try 
			{
				// Login
			    $config = Configuration::getMultiple(array('KUPNAJISTO_MAIL', 'KUPNAJISTO_PASSWORD', 'KUPNAJISTO_API_URL'));
			    $api = new KupNajistoApi($config['KUPNAJISTO_MAIL'], $config['KUPNAJISTO_PASSWORD'], $config['KUPNAJISTO_API_URL']);

			    // Change state
			    $data = array(
			    	'delivery_state' => $delivery_state
			    );

			    // Update order
			    $api->updateOrder($id_kupnajisto, $data);
			}
			//TODO: handle error?
			catch (KupNajistoException $e) 
			{
			    //echo $e->getMessage();
			}
		}
	}

	// Change kupnajisto delivery_state when changing order status
	public function hookActionOrderStatusPostUpdate($params) 
	{
		$id_order = $params['id_order'];
		$module = $this->_getPaymentModule($id_order);

		if ($module == 'kupnajisto')
		{
			$new_order_status = $params['newOrderStatus']->id;
			$this->_changeDeliveryStateKupnajisto($new_order_status, $id_order);
		}
	}
}
