<?php
/**
 * Created L/13/02/2012
 * Updated M/15/01/2019
 *
 * Copyright 2011-2019 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * https://www.luigifab.fr/magento/versioning
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Luigifab_Versioning_Block_Adminhtml_Status extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$this->_controller = 'adminhtml_status';
		$this->_blockGroup = 'versioning';

		$type = Mage::getStoreConfig('versioning/scm/type');
		$from = $this->getRequest()->getParam('from');
		$to   = $this->getRequest()->getParam('to');

		if (!empty($from) && !empty($to)) {
			$this->_headerText = !empty($branch = Mage::registry('versioning')->getCurrentBranch()) ?
				$this->__('Differences between revisions %s and %s (<span id="scmtype">%s</span>, %s)', $from, $to, $type, $branch) :
				$this->__('Differences between revisions %s and %s (<span id="scmtype">%s</span>)', $from, $to, $type);
		}
		else {
			$this->_headerText = !empty($branch = Mage::registry('versioning')->getCurrentBranch()) ?
				$this->__('Repository status (<span id="scmtype">%s</span>, %s)', $type, $branch) :
				$this->__('Repository status (<span id="scmtype">%s</span>)', $type);
		}

		$this->_removeButton('add');

		$this->_addButton('back', array(
			'label'   => $this->__('Back'),
			'onclick' => "setLocation('".$this->getUrl('*/*/index')."');",
			'class'   => 'back'
		));

		$this->_addButton('history', array(
			'label'   => $this->__('Updates history'),
			'onclick' => "setLocation('".$this->getUrl('*/*/history')."');",
			'class'   => 'go'
		));

		if (!empty($from) && !empty($to)) {
			$this->_addButton('status', array(
				'label'   => $this->__('Repository status'),
				'onclick' => "setLocation('".$this->getUrl('*/*/status')."');",
				'class'   => 'go'
			));
		}
	}

	public function getGridHtml() {

		$model = Mage::getSingleton('versioning/scm_'.Mage::getStoreConfig('versioning/scm/type'));
		$from  = $this->getRequest()->getParam('from');
		$to    = $this->getRequest()->getParam('to');

		if (!empty($from) && !empty($to))
			return '<pre lang="mul">'.$model->getCurrentDiffStatus($from, $to).'</pre>'.
			       '<pre lang="mul">'.$model->getCurrentDiff($from, $to).'</pre>';
		else
			return '<pre lang="mul">'.$model->getCurrentStatus().'</pre><pre lang="mul">'.$model->getCurrentDiff().'</pre>';
	}

	public function getHeaderCssClass() {
		return 'icon-head '.parent::getHeaderCssClass().' '.Mage::getStoreConfig('versioning/scm/type');
	}

	protected function _prepareLayout() {
		// nothing to do
	}
}