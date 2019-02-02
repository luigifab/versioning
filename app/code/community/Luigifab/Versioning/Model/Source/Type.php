<?php
/**
 * Created M/27/12/2011
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

class Luigifab_Versioning_Model_Source_Type {

	public function toOptionArray() {

		$help    = Mage::helper('versioning');
		$models  = $this->searchFiles(Mage::getModuleDir('', 'Luigifab_Versioning').'/Model/Scm');
		$options = array();

		foreach ($models as $model) {
			$model = Mage::getSingleton($model);
			$type  = mb_strtolower($model->getType());
			$label = $model->isSoftwareInstalled() ?
				$help->__('%s (%s)', mb_strtoupper($model->getType()), $model->getSoftwareVersion()) :
				$help->__('%s (not available)', mb_strtoupper($model->getType()));
			$options[$type] = array('value' => $type, 'label' => $label);
		}

		ksort($options);
		return $options;
	}

	private function searchFiles($source) {

		$files = array();
		$ressource = opendir($source);

		while (($file = readdir($ressource)) !== false) {
			if ((mb_strpos($file, '.') !== 0) && is_file($source.'/'.$file))
				$files[] = 'versioning/scm_'.mb_strtolower(mb_substr($file, 0, -4));
		}

		closedir($ressource);
		return $files;
	}
}