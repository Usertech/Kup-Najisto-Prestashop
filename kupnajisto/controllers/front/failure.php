<?php

class KupNajistoFailureModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		//TODO: Can I get failure info from KN??
		$this->context->smarty->assign(array(
			'this_path' => $this->module->getPathUri(),
			'this_path_bw' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('failure.tpl');
	}
}
