<?php
/**
 * Created S/03/12/2011
 * Updated J/28/02/2019
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

class Luigifab_Versioning_Model_Scm_Git {

	private $version;
	private $revision;
	private $items;


	// indique si le gestionnaire de version est installé
	// le tout à partir de la réponse de la commande 'git'
	public function isSoftwareInstalled() {
		exec('git --version', $data);
		return (preg_match('#(\d+\.\d+\.\d+)#', trim(implode($data)), $this->version) !== 0);
	}

	public function getSoftwareVersion() {
		if (empty($this->version))
			$this->isSoftwareInstalled();
		return !empty($this->version) ? trim($this->version[0]) : null;
	}

	public function getType() {
		return 'git';
	}


	// génère une collection à partir de l'historique des commits du dépôt
	// met en forme les données à partir de la réponse de la commande 'git log'
	// utilise GIT_SSH si le fichier de configuration existe
	public function getCommitsCollection() {

		if (!empty($this->items))
			return $this->items;

		// lecture de l'historique des commits
		if (!is_dir('./.git/') && !is_dir('../.git/'))
			Mage::throwException('The .git directory does not exist!');

		$configsh = realpath('./.git/ssh/config.sh');
		if (!is_string($configsh))
			$configsh = realpath('../.git/ssh/config.sh');

		$desc = version_compare($this->getSoftwareVersion(), '1.7.2', '>=') ? '%B' : '%s%n%b';
		$nb   = intval(Mage::getStoreConfig('versioning/scm/number'));

		if (is_string($configsh) && is_executable($configsh)) {
			exec('
				export LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8;
				export GIT_SSH="'.$configsh.'";
				git fetch 2>&1;
				git log "origin/`git branch | grep "*" | cut -c3-`" --all --pretty=format:"<log><revno>%h</revno><parents> %p </parents><committer>%an</committer><timestamp>%ai</timestamp><message><![CDATA['.$desc.']]></message></log>" -'.$nb.' | iconv -f UTF8//IGNORE -t UTF-8 -c 2>&1;
			', $data, $val);
		}
		else {
			exec('
				export LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8;
				git fetch 2>&1;
				git log "origin/`git branch | grep "*" | cut -c3-`" --all --pretty=format:"<log><revno>%h</revno><parents> %p </parents><committer>%an</committer><timestamp>%ai</timestamp><message><![CDATA['.$desc.']]></message></log>" -'.$nb.' | iconv -f UTF8//IGNORE -t UTF-8 -c 2>&1;
			', $data, $val);
		}

		// nettoyage du résultat
		$data = implode("\n", $data);
		$data = preg_replace('#<\!\[CDATA\[\s+\]\]>#', '', $data);
		$data = str_replace("\n\n", "\n", $data);

		// traitement de la réponse
		if (($val !== 0) || (mb_strpos($data, '</log>') === false) || (mb_strpos($data, 'error: ') !== false) || (mb_strpos($data, 'fatal: ') !== false)) {

			$data = is_array($data) ? implode("\n", $data) : $data;
			$data = (mb_strpos($data, '<log') !== false) ? mb_substr($data, 0, mb_strpos($data, '<log')) : $data;
			$data = '<u>Response:</u>'."\n".$data;

			$config = '<u>The git/config file:</u>'."\n".
				htmlspecialchars(trim(file_get_contents(is_file('./.git/config') ? './.git/config' : '../.git/config')));

			Mage::throwException('Can not get commit history, invalid response!'."\n\n".trim($data)."\n\n".trim($config));
		}
		else {
			$data    = (mb_strpos($data, '<') !== 0) ? mb_substr($data, mb_strpos($data, '<')) : $data;
			$branchs = array($this->getCurrentBranch());

			$xml = new DOMDocument();
			$xml->loadXML('<root>'.$data.'</root>');

			// extraction des données
			// construction de la collection des commits
			$this->items = new Varien_Data_Collection();

			foreach ($xml->getElementsByTagName('log') as $logentry) {

				$revision = trim($logentry->getElementsByTagName('revno')->item(0)->firstChild->nodeValue);
				$parents = trim($logentry->getElementsByTagName('parents')->item(0)->firstChild->nodeValue);
				$author = trim($logentry->getElementsByTagName('committer')->item(0)->firstChild->nodeValue);
				$timestamp = trim($logentry->getElementsByTagName('timestamp')->item(0)->firstChild->nodeValue);
				$description = trim($logentry->getElementsByTagName('message')->item(0)->firstChild->nodeValue);

				preg_match('#\s*\{([^\}]+)\}\s*$#', $description, $branch);
				if (count($branch) >= 1) {
					$description = preg_replace('#\s*\{[^\}]+\}\s*$#', '', $description);
					$branch = trim(array_pop($branch));
				}
				else {
					$branch = 'unknown';
				}

				if (!in_array($branch, $branchs))
					$branchs[] = $branch;

				$item = new Varien_Object();
				$item->setData('current_revision', $this->getCurrentRevision());
				$item->setData('branch', $branch);
				$item->setData('revision', $revision);
				$item->setData('parents', explode(' ', $parents));
				$item->setData('date', date('c', strtotime($timestamp)));
				$item->setData('author', preg_replace('#<[^>]+>#', '', $author));
				$item->setData('description', htmlspecialchars($description));

				$this->items->addItem($item);
			}

			// gestion des colonnes
			// en fonction des branches
			foreach ($this->items as $item) {
				$item->setData('column', array_search($item->getData('branch'), $branchs));
			}
		}

		return $this->items;
	}


	// renvoi la branche actuelle à partir de la réponse de la commande 'git branch'
	// renvoi le numéro de la révision actuelle de la copie locale (à partir de la réponse de la commande 'git log')
	// renvoi l'état de la copie locale à partir de la réponse des commandes 'git status' et 'git diff'
	public function getCurrentBranch() {

		if (!empty($this->branch))
			return $this->branch;

		exec('git branch | grep "*" | cut -c3-', $data);
		$data = trim(implode($data));
		$data = !empty($data) ? $data : null;

		$this->branch = $data;
		return $this->branch;
	}

	public function getCurrentRevision() {

		if (!empty($this->revision))
			return $this->revision;

		exec('git rev-parse --short HEAD', $data);
		$data = trim(implode($data));
		$data = !empty($data) ? $data : null;

		$this->revision = $data;
		return $this->revision;
	}

	public function getCurrentDiff($from = null, $to = null, $dir = null) {

		// --diff-filter=[(A|C|D|M|R|T|U|X|B)...[*]]
		// Select only files that are Added (A), Copied (C), Deleted (D), Modified (M), Renamed (R),
		// have their Type changed (T), are Unmerged (U), are Unknown (X), or have had their pairing Broken (B).
		if (version_compare($this->getSoftwareVersion(), '1.8.4', '>='))
			$command = 'git diff -U1 --diff-filter=MTUXB --ignore-all-space --ignore-blank-lines';
		else if (version_compare($this->getSoftwareVersion(), '1.5', '>='))
			$command = 'git diff -U1 --diff-filter=MTUXB --ignore-all-space';
		else
			$command = 'git diff -U1 --diff-filter=MTUXB';

		if (!empty($from) && !empty($to))
			$command .= ' '.escapeshellarg($from).'..'.escapeshellarg($to);
		else if (!empty($from))
			$command .= ' '.escapeshellarg($from).'..';

		if (!empty($dir))
			$command .= ' '.str_replace(' ', "' '", escapeshellarg($dir));

		$i = 0;
		exec('LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8 '.$command, $lines);

		foreach ($lines as &$line) {

			if (empty($line))
				unset($lines[$i]);
			else if (mb_strpos($line, '--- a/') === 0)
				unset($lines[$i]);
			else if (mb_strpos($line, '+++ b/') === 0)
				unset($lines[$i]);
			else if ($line == '\\ No newline at end of file')
				unset($lines[$i]);
			else if (mb_strpos($line ,'diff --git a') === 0)                 // 13 = mb_strlen('diff --git a/')
				$line = "\n".'<strong>=== '.mb_substr(htmlspecialchars($line), 13, mb_strpos($line, ' b/') - 13).'</strong>';
			else if ($line[0] == '+')
				$line = '<ins>'.htmlspecialchars($line).' </ins>';
			else if ($line[0] == '-')
				$line = '<del>'.htmlspecialchars($line).' </del>';
			else
				$line = htmlspecialchars($line);

			$i++;
		}

		return '<span>'.str_replace('\'', '', $command).'</span>'."\n".
			'Select only files that are Added (A), Copied (C), Deleted (D), Modified (M), Renamed (R),'."\n".
			'have their Type changed (T), are Unmerged (U), are Unknown (X), or have had their pairing Broken (B).'."\n".
			str_replace("\t", '    ', implode("\n", $lines));
	}

	public function getCurrentDiffStatus($from, $to, $dir = null) {

		$help = Mage::helper('versioning');

		if (version_compare($this->getSoftwareVersion(), '1.8.4', '>='))
			$command = 'git diff --name-status --ignore-all-space --ignore-blank-lines';
		else if (version_compare($this->getSoftwareVersion(), '1.5', '>='))
			$command = 'git diff --name-status --ignore-all-space';
		else
			$command = 'git diff --name-status';

		if (!empty($from) && !empty($to))
			$command .= ' '.escapeshellarg($from).'..'.escapeshellarg($to);
		else if (!empty($from))
			$command .= ' '.escapeshellarg($from).'..';

		if (!empty($dir))
			$command .= ' '.str_replace(' ', "' '", escapeshellarg($dir));

		exec('LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8 '.$command, $lines);

		// Added (A), Copied (C), Deleted (D), Modified (M), Renamed (R), Type changed (T), Unmerged (U), Unknown (X), pairing Broken (B)
		// C and R are always followed by a score (denoting the percentage of similarity between the source and target of the move or copy)
		foreach ($lines as &$line) {

			if (mb_strpos($line, 'A') === 0)
				$line = str_replace('A'."\t", "\t\t".str_replace('-', ' ', $help->__('new file:-------')), $line);
			else if (mb_strpos($line, 'C') === 0)
				$line = preg_replace("#C\d*\t#", "\t\t".str_replace('-', ' ', $help->__('copied:---------')), $line);
			else if (mb_strpos($line, 'D') === 0)
				$line = str_replace('D'."\t", "\t\t".str_replace('-', ' ', $help->__('deleted:--------')), $line);
			else if (mb_strpos($line, 'M') === 0)
				$line = str_replace('M'."\t", "\t\t".str_replace('-', ' ', $help->__('modified:-------')), $line);
			else if (mb_strpos($line, 'R') === 0)
				$line = preg_replace("#R\d*\t#", "\t\t".str_replace('-', ' ', $help->__('renamed:--------')), $line);
			else if (mb_strpos($line, 'T') === 0)
				$line = str_replace('T'."\t", "\t\t".str_replace('-', ' ', $help->__('type changed:---')), $line);
			else if (mb_strpos($line, 'U') === 0)
				$line = str_replace('U'."\t", "\t\t".str_replace('-', ' ', $help->__('unmerged:-------')), $line);
			else if (mb_strpos($line, 'X') === 0)
				$line = str_replace('X'."\t", "\t\t".str_replace('-', ' ', $help->__('unknown:--------')), $line);
			else if (mb_strpos($line, 'B') === 0)
				$line = str_replace('B'."\t", "\t\t".str_replace('-', ' ', $help->__('pairing broken:-')), $line);
		}

		return '<span>'.str_replace('\'', '', $command).'</span>'."\n".$help->__('For the current diff')."\n\n".
			str_replace("\t", '    ', htmlspecialchars(implode("\n", $lines)));
	}

	public function getCurrentStatus() {
		exec('LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8 git status', $data);
		return '<span>git status</span>'."\n".htmlspecialchars(implode("\n", $data));
	}


	// met à jour la copie locale avec 'git reset' (après avoir annulé les éventuelles modifications avec 'git clean')
	// prend soin de vérifier le code de retour de la commande 'git reset' et d'enregistrer les détails de la mise à jour
	// n'utilise pas GIT_SSH étant donnée que tout est disponible sur le dépôt local
	public function upgradeToRevision($obj, $log, $revision) {

		$revision = escapeshellarg($revision);

		if (is_dir('../.git/')) {
			exec('export LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8;
				echo "<span>git fetch</span>" >> '.$log.';
				git fetch;
				echo "<span>git clean -f -d</span>" >> '.$log.';
				git clean -f -d .. >> '.$log.' 2>&1;
				echo "<span>git reset --hard '.$revision.'</span>" >> '.$log.';
				git reset --hard '.$revision.' >> '.$log.' 2>&1;', $data, $val);
		}
		else {
			exec('export LANG='.Mage::getSingleton('core/translate')->getLocale().'.utf8;
				echo "<span>git fetch</span>" >> '.$log.';
				git fetch;
				echo "<span>git clean -f -d</span>" >> '.$log.';
				git clean -f -d >> '.$log.' 2>&1;
				echo "<span>git reset --hard '.$revision.'</span>" >> '.$log.';
				git reset --hard '.$revision.' >> '.$log.' 2>&1;', $data, $val);
		}

		$data  = trim(file_get_contents($log));
		$lines = explode("\n", $data);

		$obj->writeCommand($data);

		foreach ($lines as $line) {
			if (mb_strpos($line, 'fatal: ') === 0)
				Mage::throwException(str_replace('fatal: ', '', $line));
		}

		if ($val !== 0)
			Mage::throwException($data);
	}
}