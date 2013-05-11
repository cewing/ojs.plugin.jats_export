<?php

/**
 * @file plugins/importexport/jats/JATSImportExportPlugin.inc.php
 *
 * @class JATSImportExportPlugin
 * @ingroup plugins_importexport_jats
 *
 * @brief JATS import/export plugin
 */

import('classes.plugins.ImportExportPlugin');

import('lib.pkp.classes.xml.XMLCustomWriter');

define('JATS_DTD_URL', 'http://jats.nlm.nih.gov/archiving/1.0/JATS-archivearticle1.dtd');
define('JATS_DTD_ID', '-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD v1.0 20120330//EN');

class JATSImportExportPlugin extends ImportExportPlugin {
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'JATSImportExportPlugin';
	}

	function getDisplayName() {
		return __('plugins.importexport.jats.displayName');
	}

	function getDescription() {
		return __('plugins.importexport.jats.description');
	}

	function display(&$args, $request) {
		$templateMgr =& TemplateManager::getManager();
		parent::display($args, $request);

		$issueDao =& DAORegistry::getDAO('IssueDAO');

		$journal =& $request->getJournal();
		switch (array_shift($args)) {
			case 'exportIssues':
				$issueIds = $request->getUserVar('issueId');
				if (!isset($issueIds)) $issueIds = array();
				$issues = array();
				foreach ($issueIds as $issueId) {
					$issue =& $issueDao->getIssueById($issueId, $journal->getId());
					if (!$issue) $request->redirect();
					$issues[] =& $issue;
					unset($issue);
				}
				$this->exportIssues($journal, $issues);
				break;
			case 'exportIssue':
				$issueId = array_shift($args);
				$issue =& $issueDao->getIssueById($issueId, $journal->getId());
				if (!$issue) $request->redirect();
				$this->exportIssue($journal, $issue);
				break;
			case 'exportArticle':
				$articleIds = array(array_shift($args));
				$result = array_shift(ArticleSearch::formatResults($articleIds));
				$this->exportArticle($journal, $result['issue'], $result['section'], $result['publishedArticle']);
				break;
			case 'exportArticles':
				$articleIds = $request->getUserVar('articleId');
				if (!isset($articleIds)) $articleIds = array();
				$results =& ArticleSearch::formatResults($articleIds);
				$this->exportArticles($results);
				break;
			case 'issues':
				// Display a list of issues for export
				$this->setBreadcrumbs(array(), true);
				AppLocale::requireComponents(LOCALE_COMPONENT_OJS_EDITOR);
				$issueDao =& DAORegistry::getDAO('IssueDAO');
				$issues =& $issueDao->getIssues($journal->getId(), Handler::getRangeInfo('issues'));

				$templateMgr->assign_by_ref('issues', $issues);
				$templateMgr->display($this->getTemplatePath() . 'issues.tpl');
				break;
			case 'articles':
				// Display a list of articles for export
				$this->setBreadcrumbs(array(), true);
				$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
				$rangeInfo = Handler::getRangeInfo('articles');
				$articleIds = $publishedArticleDao->getPublishedArticleIdsAlphabetizedByJournal($journal->getId(), false);
				$totalArticles = count($articleIds);
				if ($rangeInfo->isValid()) $articleIds = array_slice($articleIds, $rangeInfo->getCount() * ($rangeInfo->getPage()-1), $rangeInfo->getCount());
				import('lib.pkp.classes.core.VirtualArrayIterator');
				$iterator = new VirtualArrayIterator(ArticleSearch::formatResults($articleIds), $totalArticles, $rangeInfo->getPage(), $rangeInfo->getCount());
				$templateMgr->assign_by_ref('articles', $iterator);
				$templateMgr->display($this->getTemplatePath() . 'articles.tpl');
				break;
			case 'import':
			   // this is unsupported, report that error immediately without doing anything else
			   $errors = array();
      		$errors[] = array('plugins.importexport.jats.import.error.unsupported');
      		$templateMgr->assign_by_ref('errors', $errors);
				return $templateMgr->display($this->getTemplatePath() . 'importError.tpl');
				break;
			default:
				$this->setBreadcrumbs();
				$templateMgr->display($this->getTemplatePath() . 'index.tpl');
		}
	}

	function exportIssue(&$journal, &$issue, $outputFile = null) {
		$this->import('JATSExportDom');
		$doc =& XMLCustomWriter::createDocument('issue', JATS_DTD_ID, JATS_DTD_URL);
      // $issueNode =& JATSExportDom::generateIssueDom($doc, $journal, $issue);
      // XMLCustomWriter::appendChild($doc, $issueNode);

		if (!empty($outputFile)) {
			if (($h = fopen($outputFile, 'wb'))===false) return false;
			fwrite($h, XMLCustomWriter::getXML($doc));
			fclose($h);
		} else {
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: attachment; filename=\"issue-" . $issue->getId() . ".xml\"");
			XMLCustomWriter::printXML($doc);
		}
		return true;
	}

	function exportArticle(&$journal, &$issue, &$section, &$article, $outputFile = null) {
		$this->import('JATSExportDom');
		$doc =& XMLCustomWriter::createDocument('article', JATS_DTD_ID, JATS_DTD_URL);
      // $articleNode =& JATSExportDom::generateArticleDom($doc, $journal, $issue, $section, $article);
      // XMLCustomWriter::appendChild($doc, $articleNode);

		if (!empty($outputFile)) {
			if (($h = fopen($outputFile, 'w'))===false) return false;
			fwrite($h, XMLCustomWriter::getXML($doc));
			fclose($h);
		} else {
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: attachment; filename=\"article-" . $article->getId() . ".xml\"");
			XMLCustomWriter::printXML($doc);
		}
		return true;
	}

	function exportIssues(&$journal, &$issues, $outputFile = null) {
		$this->import('JATSExportDom');
		$doc =& XMLCustomWriter::createDocument('issues', JATS_DTD_ID, JATS_DTD_URL);
		$issuesNode =& XMLCustomWriter::createElement($doc, 'issues');
		XMLCustomWriter::appendChild($doc, $issuesNode);

      // foreach ($issues as $issue) {
      //    $issueNode =& JATSExportDom::generateIssueDom($doc, $journal, $issue);
      //    XMLCustomWriter::appendChild($issuesNode, $issueNode);
      // }

		if (!empty($outputFile)) {
			if (($h = fopen($outputFile, 'w'))===false) return false;
			fwrite($h, XMLCustomWriter::getXML($doc));
			fclose($h);
		} else {
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: attachment; filename=\"issues.xml\"");
			XMLCustomWriter::printXML($doc);
		}
		return true;
	}

	function exportArticles(&$results, $outputFile = null) {
		$this->import('JATSExportDom');
		$doc =& XMLCustomWriter::createDocument('articles', JATS_DTD_ID, JATS_DTD_URL);
		$articlesNode =& XMLCustomWriter::createElement($doc, 'articles');
		XMLCustomWriter::appendChild($doc, $articlesNode);

      // foreach ($results as $result) {
      //    $article =& $result['publishedArticle'];
      //    $section =& $result['section'];
      //    $issue =& $result['issue'];
      //    $journal =& $result['journal'];
      //    $articleNode =& JATSExportDom::generateArticleDom($doc, $journal, $issue, $section, $article);
      //    XMLCustomWriter::appendChild($articlesNode, $articleNode);
      // }

		if (!empty($outputFile)) {
			if (($h = fopen($outputFile, 'w'))===false) return false;
			fwrite($h, XMLCustomWriter::getXML($doc));
			fclose($h);
		} else {
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: attachment; filename=\"articles.xml\"");
			XMLCustomWriter::printXML($doc);
		}
		return true;
	}

	/**
	 * Execute import/export tasks using the command-line interface.
	 * @param $args Parameters to the plugin
	 */
	function executeCLI($scriptName, &$args) {
		$command = array_shift($args);
		$xmlFile = array_shift($args);
		$journalPath = array_shift($args);

		AppLocale::requireComponents(LOCALE_COMPONENT_APPLICATION_COMMON);

		$journalDao =& DAORegistry::getDAO('JournalDAO');
		$issueDao =& DAORegistry::getDAO('IssueDAO');
		$sectionDao =& DAORegistry::getDAO('SectionDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');

		$journal =& $journalDao->getJournalByPath($journalPath);

		if (!$journal) {
			if ($journalPath != '') {
				echo __('plugins.importexport.jats.cliError') . "\n";
				echo __('plugins.importexport.jats.error.unknownJournal', array('journalPath' => $journalPath)) . "\n\n";
			}
			$this->usage($scriptName);
			return;
		}

		switch ($command) {
			case 'import':
			   echo __('plugins.importexport.jats.cliError') . "\n";
			   echo __('plugins.importexport.jats.import.error.unsupported') . "\n";
				return;
				break;
			case 'export':
				if ($xmlFile != '') switch (array_shift($args)) {
					case 'article':
						$articleId = array_shift($args);
						$publishedArticle =& $publishedArticleDao->getPublishedArticleByBestArticleId($journal->getId(), $articleId);
						if ($publishedArticle == null) {
							echo __('plugins.importexport.jats.cliError') . "\n";
							echo __('plugins.importexport.jats.export.error.articleNotFound', array('articleId' => $articleId)) . "\n\n";
							return;
						}
						$issue =& $issueDao->getIssueById($publishedArticle->getIssueId(), $journal->getId());

						$sectionDao =& DAORegistry::getDAO('SectionDAO');
						$section =& $sectionDao->getSection($publishedArticle->getSectionId());

						if (!$this->exportArticle($journal, $issue, $section, $publishedArticle, $xmlFile)) {
							echo __('plugins.importexport.jats.cliError') . "\n";
							echo __('plugins.importexport.jats.export.error.couldNotWrite', array('fileName' => $xmlFile)) . "\n\n";
						}
						return;
					case 'articles':
						$results =& ArticleSearch::formatResults($args);
						if (!$this->exportArticles($results, $xmlFile)) {
							echo __('plugins.importexport.jats.cliError') . "\n";
							echo __('plugins.importexport.jats.export.error.couldNotWrite', array('fileName' => $xmlFile)) . "\n\n";
						}
						return;
					case 'issue':
						$issueId = array_shift($args);
						$issue =& $issueDao->getIssueByBestIssueId($issueId, $journal->getId());
						if ($issue == null) {
							echo __('plugins.importexport.jats.cliError') . "\n";
							echo __('plugins.importexport.jats.export.error.issueNotFound', array('issueId' => $issueId)) . "\n\n";
							return;
						}
						if (!$this->exportIssue($journal, $issue, $xmlFile)) {
							echo __('plugins.importexport.jats.cliError') . "\n";
							echo __('plugins.importexport.jats.export.error.couldNotWrite', array('fileName' => $xmlFile)) . "\n\n";
						}
						return;
					case 'issues':
						$issues = array();
						while (($issueId = array_shift($args))!==null) {
							$issue =& $issueDao->getIssueByBestIssueId($issueId, $journal->getId());
							if ($issue == null) {
								echo __('plugins.importexport.jats.cliError') . "\n";
								echo __('plugins.importexport.jats.export.error.issueNotFound', array('issueId' => $issueId)) . "\n\n";
								return;
							}
							$issues[] =& $issue;
						}
						if (!$this->exportIssues($journal, $issues, $xmlFile)) {
							echo __('plugins.importexport.jats.cliError') . "\n";
							echo __('plugins.importexport.jats.export.error.couldNotWrite', array('fileName' => $xmlFile)) . "\n\n";
						}
						return;
				}
				break;
		}
		$this->usage($scriptName);
	}

	/**
	 * Display the command-line usage information
	 */
	function usage($scriptName) {
		echo __('plugins.importexport.jats.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}
}

?>
