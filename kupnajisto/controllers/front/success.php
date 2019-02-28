<?php

class KupNajistoSuccessModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		$this->setTemplate('success.tpl');
	}
}
