<?php

/**
 * @file plugins/importexport/jats/JATSExportDom.inc.php
 *
 * @class JATSExportDom
 * @ingroup plugins_importexport_jats
 *
 * @brief JATS import/export plugin DOM functions for export
 */

import('lib.pkp.classes.xml.XMLCustomWriter');

define('JATS_DEFAULT_EXPORT_LOCALE', 'en_US');

class JATSExportDOM {
	function &generateIssueDom(&$doc, &$journal, &$issue) {
		$root =& XMLCustomWriter::createElement($doc, 'issue');

		NativeExportDom::generatePubId($doc, $root, $issue, $issue);

		XMLCustomWriter::setAttribute($root, 'published', $issue->getPublished()?'true':'false');

		switch (
			(int) $issue->getShowVolume() .
			(int) $issue->getShowNumber() .
			(int) $issue->getShowYear() .
			(int) $issue->getShowTitle()
		) {
			case '1110': $idType = 'num_vol_year'; break;
			case '1010': $idType = 'vol_year'; break;
			case '0010': $idType = 'year'; break;
			case '1000': $idType = 'vol'; break;
			case '0001': $idType = 'title'; break;
			default: $idType = null;
		}
		XMLCustomWriter::setAttribute($root, 'identification', $idType, false);

		XMLCustomWriter::setAttribute($root, 'current', $issue->getCurrent()?'true':'false');
		XMLCustomWriter::setAttribute($root, 'public_id', $issue->getPubId('publisher-id'), false);

		if (is_array($issue->getTitle(null))) foreach ($issue->getTitle(null) as $locale => $title) {
			$titleNode =& XMLCustomWriter::createChildWithText($doc, $root, 'title', $title, false);
			if ($titleNode) XMLCustomWriter::setAttribute($titleNode, 'locale', $locale);
			unset($titleNode);
		}
		if (is_array($issue->getDescription(null))) foreach ($issue->getDescription(null) as $locale => $description) {
			$descriptionNode =& XMLCustomWriter::createChildWithText($doc, $root, 'description', $description, false);
			if ($descriptionNode) XMLCustomWriter::setAttribute($descriptionNode, 'locale', $locale);
			unset($descriptionNode);
		}
		XMLCustomWriter::createChildWithText($doc, $root, 'volume', $issue->getVolume(), false);
		XMLCustomWriter::createChildWithText($doc, $root, 'number', $issue->getNumber(), false);
		XMLCustomWriter::createChildWithText($doc, $root, 'year', $issue->getYear(), false);

		if (is_array($issue->getShowCoverPage(null))) foreach (array_keys($issue->getShowCoverPage(null)) as $locale) {
			if ($issue->getShowCoverPage($locale)) {
				$coverNode =& XMLCustomWriter::createElement($doc, 'cover');
				XMLCustomWriter::appendChild($root, $coverNode);
				XMLCustomWriter::setAttribute($coverNode, 'locale', $locale);

				XMLCustomWriter::createChildWithText($doc, $coverNode, 'caption', $issue->getCoverPageDescription($locale), false);

				$coverFile = $issue->getFileName($locale);
				if ($coverFile != '') {
					$imageNode =& XMLCustomWriter::createElement($doc, 'image');
					XMLCustomWriter::appendChild($coverNode, $imageNode);
					import('classes.file.PublicFileManager');
					$publicFileManager = new PublicFileManager();
					$coverPagePath = $publicFileManager->getJournalFilesPath($journal->getId()) . '/';
					$coverPagePath .= $coverFile;
					$embedNode =& XMLCustomWriter::createChildWithText($doc, $imageNode, 'embed', base64_encode($publicFileManager->readFile($coverPagePath)));
					XMLCustomWriter::setAttribute($embedNode, 'filename', $issue->getOriginalFileName($locale));
					XMLCustomWriter::setAttribute($embedNode, 'encoding', 'base64');
					XMLCustomWriter::setAttribute($embedNode, 'mime_type', String::mime_content_type($coverPagePath));
				}

				unset($coverNode);
			}
		}

		XMLCustomWriter::createChildWithText($doc, $root, 'date_published', NativeExportDom::formatDate($issue->getDatePublished()), false);

		if (XMLCustomWriter::createChildWithText($doc, $root, 'access_date', NativeExportDom::formatDate($issue->getOpenAccessDate()), false)==null) {
			// This may be an open access issue. Check and flag
			// as necessary.

			if ( // Issue flagged as open, or subscriptions disabled
				$issue->getAccessStatus() == ISSUE_ACCESS_OPEN ||
				$journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN
			) {
				$accessNode =& XMLCustomWriter::createElement($doc, 'open_access');
				XMLCustomWriter::appendChild($root, $accessNode);
			}
		}

		$sectionDao =& DAORegistry::getDAO('SectionDAO');
		foreach ($sectionDao->getSectionsForIssue($issue->getId()) as $section) {
			$sectionNode =& NativeExportDom::generateSectionDom($doc, $journal, $issue, $section);
			XMLCustomWriter::appendChild($root, $sectionNode);
			unset($sectionNode);
		}

		return $root;
	}

	function &generateSectionDom(&$doc, &$journal, &$issue, &$section) {
		$root =& XMLCustomWriter::createElement($doc, 'section');

		if (is_array($section->getTitle(null))) foreach ($section->getTitle(null) as $locale => $title) {
			$titleNode =& XMLCustomWriter::createChildWithText($doc, $root, 'title', $title, false);
			if ($titleNode) XMLCustomWriter::setAttribute($titleNode, 'locale', $locale);
			unset($titleNode);
		}

		if (is_array($section->getAbbrev(null))) foreach ($section->getAbbrev(null) as $locale => $abbrev) {
			$abbrevNode =& XMLCustomWriter::createChildWithText($doc, $root, 'abbrev', $abbrev, false);
			if ($abbrevNode) XMLCustomWriter::setAttribute($abbrevNode, 'locale', $locale);
			unset($abbrevNode);
		}

		if (is_array($section->getIdentifyType(null))) foreach ($section->getIdentifyType(null) as $locale => $identifyType) {
			$identifyTypeNode =& XMLCustomWriter::createChildWithText($doc, $root, 'identify_type', $identifyType, false);
			if ($identifyTypeNode) XMLCustomWriter::setAttribute($identifyTypeNode, 'locale', $locale);
			unset($identifyTypeNode);
		}

		if (is_array($section->getPolicy(null))) foreach ($section->getPolicy(null) as $locale => $policy) {
			$policyNode =& XMLCustomWriter::createChildWithText($doc, $root, 'policy', $policy, false);
			if ($policyNode) XMLCustomWriter::setAttribute($policyNode, 'locale', $locale);
			unset($policyNode);
		}

		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		foreach ($publishedArticleDao->getPublishedArticlesBySectionId($section->getId(), $issue->getId()) as $article) {
			$articleNode =& NativeExportDom::generateArticleDom($doc, $journal, $issue, $section, $article);
			XMLCustomWriter::appendChild($root, $articleNode);
			unset($articleNode);
		}

		return $root;
	}

	function &generateArticleDom(&$doc, &$journal, &$issue, &$section, &$article) {
		$root =& XMLCustomWriter::createElement($doc, 'article');
		XMLCustomWriter::setAttribute($root, 'article-type', 'case-report');
		XMLCustomWriter::setAttribute($root, 'dtd-version', '1.0');
		XMLCustomWriter::setAttribute($root, 'xmlns:xlink', 'http://www.w3.org/1999/xlink');
		// XMLCustomWriter::setAttribute($root, 'public_id', $article->getPubId('publisher-id'), false);
		// XMLCustomWriter::setAttribute($root, 'language', $article->getLanguage(), false);

		// NativeExportDom::generatePubId($doc, $root, $article, $issue);
		
		/* --- Article Front Matter --- */
		$frontNode =& JATSExportDOM::generateArticleFrontDom($doc, $journal, $issue, $section, $article);
		XMLCustomWriter::appendChild($root, $frontNode);
		unset($frontNode);

		/* --- Article Body () --- */
		$bodyNode =& JATSExportDOM::generateArticleBodyDom($doc, $journal, $issue, $article);
		if ($bodyNode !== null) XMLCustomWriter::appendChild($root, $bodyNode);
		unset($bodyNode);

		// XMLCustomWriter::createChildWithText($doc, $root, 'pages', $article->getPages(), false);

		return $root;
	}
	
	function &generateArticleFrontDom(&$doc, &$journal, &$issue, &$section, &$article) {
		$root =& XMLCustomWriter::createElement($doc, 'front');
		
		$journalMeta =& JATSExportDom::generateJournalMetaDom($doc, $journal, $issue);
		XMLCustomWriter::appendChild($root, $journalMeta);
		unset($journalMeta);

		$articleMeta =& JATSExportDom::generateArticleMetaDom($doc, $journal, $issue, $section, $article);
		XMLCustomWriter::appendChild($root, $articleMeta);
		unset($articleMeta);

		return $root;
	}

	function &generateArticleBodyDom(&$doc, &$journal, &$issue, &$article) {
		$root =& XMLCustomWriter::createElement($doc, 'body');
		
		// render the list of galley files as data structure
		$galleysNode =& XMLCustomWriter::createElement($doc, 'galley-files');
		$hasGalleys = false;
		foreach ($article->getLocalizedGalleys() as $galley) {
			if ($galley->isHTMLGalley()) {
				// if we have an html galley, then use it to render the HTML markup for this article;
				$markupNode =& JATSExportDom::generateHtmlBodyCData($doc, $journal, $article, $galley);
				if ($markupNode !== null) XMLCustomWriter::appendChild($root, $markupNode);
			}
			$galleyNode =& JATSExportDom::generateGalleyDom($doc, $journal, $issue, $article, $galley);
			if ($galleyNode !== null) {
				XMLCustomWriter::appendChild($galleysNode, $galleyNode);
				$hasGalleys = true;
			}
			unset($galleyNode);
		}
		if ($hasGalleys) {
			XMLCustomWriter::appendChild($root, $galleysNode);
		}
		
		// render the list of supplemental files as data structure
		$suppFilesNode =& XMLCustomWriter::createElement($doc, 'supplemental-files');
		$hasSuppFiles = false;
		foreach ($article->getSuppFiles() as $suppFile) {
				$suppNode =& JATSExportDom::generateSuppFileDom($doc, $journal, $issue, $article, $suppFile);
				if ($suppNode !== null) {
					XMLCustomWriter::appendChild($suppFilesNode, $suppNode);
					$hasSuppFiles = true;
				}
				unset($suppNode);
		}
		if ($hasSuppFiles) {
			XMLCustomWriter::appendChild($root, $suppFilesNode);
		}
		
		return $root;
	}

	function &generateJournalMetaDom(&$doc, &$journal, &$issue) {
		$root =& XMLCustomWriter::createElement($doc, 'journal-meta');
		// add journal id (publisher type) to meta
		$journalPublisherIdNode =& XMLCustomWriter::createChildWithText($doc, $root, 'journal-id', $journal->getSetting('abbreviation', JATS_DEFAULT_EXPORT_LOCALE));
		XMLCustomWriter::setAttribute($journalPublisherIdNode, 'journal-id-type', 'publisher-id');
		unset($journalPublisherIdNode);
		// add journal title to meta
		$titleGroup =& XMLCustomWriter::createElement($doc, 'journal-title-group');
		XMLCustomWriter::createChildWithText($doc, $titleGroup, 'journal-title', $journal->getTitle(JATS_DEFAULT_EXPORT_LOCALE));
		XMLCustomWriter::appendChild($root, $titleGroup);
		unset($titleGroup);
		// add journal issn to meta
		$issn = $journal->getSetting('onlineIssn');
		if ($issn) {
			$issnNode =& XMLCustomWriter::createChildWithText($doc, $root, 'issn', $issn);
			XMLCustomWriter::setAttribute($issnNode, 'pub-type', 'epub');
		}
		// add publisher info to meta
		$pubName = $journal->getSetting('publisherInstitution');
		$pubLoc = $journal->getSetting('publisherUrl');
		if ($pubName || $pubLoc) {
			$publisherInfo =& XMLCustomWriter::createElement($doc, 'publisher');
			if ($pubName) {
				XMLCustomWriter::createChildWithText($doc, $publisherInfo, 'publisher-name', $pubName);
			}
			if ($pubLoc) {
				$pubLocNode =& XMLCustomWriter::createElement($doc, 'publisher-loc');
				XMLCustomWriter::createChildWithText($doc, $pubLocNode, 'uri', $pubLoc);
				XMLCustomWriter::appendChild($publisherInfo, $pubLocNode);
			}
			XMLCustomWriter::appendChild($root, $publisherInfo);
		}
		
		return $root;
	}

	function &generateArticleMetaDom(&$doc, &$journal, &$issue, &$section, &$article) {
		$root =& XMLCustomWriter::createElement($doc, 'article-meta');
		
		// Add article identifiers provided by plugins
		JATSExportDOM::generateArticleId($doc, $root, $article, $issue);
		// Add article categories like journal section and keywords
		$sectionNode =& JATSExportDOM::generateArticleSectionCategory($doc, $section);
		$subjectNode =& JATSExportDOM::generateArticleSubjectCategory($doc, $article);
		$disciplineNode =& JATSExportDOM::generateArticleDisciplineCategory($doc, $article);
		if ($sectionNode || $subjectNode || $disciplineNode) {
			$categoriesNode = XMLCustomWriter::createElement($doc, 'article-categories');
			if ($sectionNode) {
				XMLCustomWriter::appendChild($categoriesNode, $sectionNode);
			}
			if ($subjectNode) {
				XMLCustomWriter::appendChild($categoriesNode, $subjectNode);
			}
			if ($disciplineNode) {
				XMLCustomWriter::appendChild($categoriesNode, $disciplineNode);
			}
			XMLCustomWriter::appendChild($root, $categoriesNode);
			unset($categoriesNode);
		}
		// Add article title
		$titleNode =& XMLCustomWriter::createElement($doc, 'title-group');
		XMLCustomWriter::createChildWithText($doc, $titleNode, 'article-title', trim($article->getLocalizedTitle(JATS_DEFAULT_EXPORT_LOCALE)));
		XMLCustomWriter::appendChild($root, $titleNode);
		unset($titleNode);
		// Add article contributors
		$contribNode =& JATSExportDOM::generateArticleContributorsDOM($doc, $journal, $issue, $section, $article);
		XMLCustomWriter::appendChild($root, $contribNode);
		unset($contribNode);
		// Add article author notes (a conflict statement, blanket for RCR)
		$conflictNode =& JATSExportDOM::generateArticleConflictStatement($doc);
		XMLCustomWriter::appendChild($root, $conflictNode);
		unset($conflictNode);
		// Add article publication date if provided
		$pubdateNode =& JATSExportDOM::generateArticlePubDateDom($doc, $article);
		if ($pubdateNode) {
			XMLCustomWriter::appendChild($root, $pubdateNode);	
		}
		unset($pubdateNode);
		// add volume and issue numbers
		XMLCustomWriter::createChildWithText($doc, $root, 'volume', $issue->getVolume());
		XMLCustomWriter::createChildWithText($doc, $root, 'issue', $issue->getNumber());
		XMLCustomWriter::createChildWithText($doc, $root, 'elocation-id', $article->getArticleId());
		// permissions (stock for all articles)
		$permsNode =& JATSExportDOM::generateArticlePermissionsDOM($doc, $article);
		XMLCustomWriter::appendChild($root, $permsNode);
		unset($permsNode);
		// self-uri is the view url of this specific article online
		JATSExportDom::createArticleSelfUriNode($doc, $root, $journal, $article);
		// abstract
		$abstractNode =& JATSExportDOM::generateArticleAbstractDOM($doc, $article);
		XMLCustomWriter::appendChild($root, $abstractNode);
		unset($abstractNode);
		
		return $root;
	}

	function &generateArticleSectionCategory(&$doc, &$section) {
		$default = null;
		$sectionTitle = $section->getTitle(JATS_DEFAULT_EXPORT_LOCALE);
		$sectionAbbrev = $section->getAbbrev(JATS_DEFAULT_EXPORT_LOCALE);
		if ($sectionTitle || $sectionAbbrev) {
			$categoriesNode =& XMLCustomWriter::createElement($doc, 'subj-group');
			XMLCustomWriter::setAttribute($categoriesNode, 'subj-group-type', 'section');
			if ($sectionTitle && $sectionAbbrev) {
				$compoundSubj =& XMLCustomWriter::createElement($doc, 'compound-subject');
				$codePart =& XMLCustomWriter::createChildWithText($doc, $compoundSubj, 'compound-subject-part', $sectionAbbrev);
				XMLCustomWriter::setAttribute($codePart, 'content-type', 'code');
				$textPart =& XMLCustomWriter::createChildWithText($doc, $compoundSubj, 'compound-subject-part', $sectionTitle);
				XMLCustomWriter::setAttribute($textPart, 'content-type', 'text');
				XMLCustomWriter::appendChild($categoriesNode, $compoundSubj);
			} else {
				$possibles = array($sectionTitle, $sectionAbbrev);
				foreach ($possibles as $possible) {
					if ($possible) {
						XMLCustomWriter::createChildWithText($doc, $categoriesNode, 'subject', $possible);
					}
				}
			}
			return $categoriesNode;
		}
		return $default;
	}

	function &generateArticleSubjectCategory(&$doc, &$article) {
		$default = null;
		$subject = $article->getLocalizedSubject(JATS_DEFAULT_EXPORT_LOCALE);
		if ($subject === '') {
			return $default;
		}
		$subjectsNode =& XMLCustomWriter::createElement($doc, 'subj-group');
		XMLCustomWriter::setAttribute($subjectsNode, 'subj-group-type', 'keywords');
		$delimiters = array(';',',');
		foreach ($delimiters as $delimiter) {
			$subject_array = explode($delimiter, $subject);
			if (count($subject_array) > 1) {
				$subject = $subject_array;
				break;
			}
		}
		if (!is_array($subject)) {
			$subject = array($subject);
		}
		foreach ($subject as $subj) {
			XMLCustomWriter::createChildWithText($doc, $subjectsNode, 'subject', trim($subj));
		}
		return $subjectsNode;
	}

	function &generateArticleDisciplineCategory(&$doc, &$article) {
		$default = null;
		$subject = $article->getLocalizedDiscipline(JATS_DEFAULT_EXPORT_LOCALE);
		if ($subject === '') {
			return $default;
		}
		$subjectsNode =& XMLCustomWriter::createElement($doc, 'subj-group');
		XMLCustomWriter::setAttribute($subjectsNode, 'subj-group-type', 'disciplines');
		$delimiters = array(';',',');
		foreach ($delimiters as $delimiter) {
			$subject_array = explode($delimiter, $subject);
			if (count($subject_array) > 1) {
				$subject = $subject_array;
				break;
			}
		}
		if (!is_array($subject)) {
			$subject = array($subject);
		}
		foreach ($subject as $subj) {
			XMLCustomWriter::createChildWithText($doc, $subjectsNode, 'subject', trim($subj));
		}
		return $subjectsNode;
	}

	function &generateArticleContributorsDOM(&$doc, &$journal, &$issue, &$section, &$article) {
		// authors
		$root = XMLCustomWriter::createElement($doc, 'contrib-group');
		foreach ($article->getAuthors() as $author) {
			$authorNode =& JATSExportDOM::generateAuthorDom($doc, $journal, $issue, $article, $author);
			XMLCustomWriter::appendChild($root, $authorNode);
			unset($authorNode);
		}
		return $root;
	}

	function &generateAuthorDom(&$doc, &$journal, &$issue, &$article, &$author) {
		$root =& XMLCustomWriter::createElement($doc, 'contrib');
		XMLCustomWriter::setAttribute($root, 'contrib-type', 'author');
		if ($author->getPrimaryContact()) XMLCustomWriter::setAttribute($root, 'corresp', 'yes');
		
		// Generate Name
		$nameNode =& XMLCustomWriter::createElement($doc, 'name');
		XMLCustomWriter::createChildWithText($doc, $nameNode, 'surname', $author->getLastName(), false);
		$givenNames = $author->getData('firstName') . ' ' . ($author->getData('middleName') != '' ? $author->getData('middleName') : '');
		XMLCustomWriter::createChildWithText($doc, $nameNode, 'given-names', $givenNames);
		XMLCustomWriter::createChildWithText($doc, $root, 'prefix', $author->getSalutation(), false);
		XMLCustomWriter::createChildWithText($doc, $root, 'suffix', $author->getSuffix(), false);
		XMLCustomWriter::appendChild($root, $nameNode);
		unset($nameNode);
		
		// Generate Address info
		$addrNode =& XMLCustomWriter::createElement($doc, 'address');
		XMLCustomWriter::createChildWithText($doc, $addrNode, 'country', $author->getCountry(), false);
		XMLCustomWriter::createChildWithText($doc, $addrNode, 'email', $author->getEmail());
		XMLCustomWriter::createChildWithText($doc, $addrNode, 'url', $author->getUrl(), false);
		XMLCustomWriter::appendChild($root, $addrNode);
		unset($addrNode);
		
		// Generate affiliations (XXX: There is a good amount of 'dirty' data in 
		//	  this setting, things like 'M.D.' or 'radiologist').	 For this reason
		//	  we will only insert affiliation for the corresponding author.
		if ($author->getPrimaryContact()) {
			$affiliations = $author->getAffiliation(null);
			if (is_array($affiliations)) foreach ($affiliations as $locale => $affiliation) {
				$affiliationNode =& XMLCustomWriter::createChildWithText($doc, $root, 'aff', $affiliation, false);
				unset($affiliationNode);
			}
		}

		// Generate bio element
		if (is_array($author->getBiography(null))) foreach ($author->getBiography(null) as $locale => $biography) {
			$biography = str_replace("\r\n", "\n", $biography);
			$biography = str_replace("\r", "\n", $biography);
			$biography = preg_replace("/\n{2,}/", "\n\n", $biography);
			$biographyNode =& XMLCustomWriter::createElement($doc, 'bio');
			$biographyGraph =& XMLCustomWriter::createChildWithText($doc, $biographyNode, 'p', strip_tags($biography, '<p>'), false);
			if ($biographyGraph != null) {
				XMLCustomWriter::appendChild($root, $biographyNode);
			}
			unset($biographyGraph);
			unset($biographyNode);
		}

		return $root;
	}
	
	function &generateArticleConflictStatement(&$doc) {
		$root =& XMLCustomWriter::createElement($doc, 'author-notes');
		$fn_node =& XMLCustomWriter::createElement($doc, 'fn');
		XMLCustomWriter::setAttribute($fn_node, 'fn-type', 'conflict');
		XMLCustomWriter::appendChild($root, $fn_node);
		$stock_statement = "No conflicts of interest have been declared";
		$conflict =& XMLCustomWriter::createChildWithText($doc, $fn_node, 'p', $stock_statement);
		
		return $root;
	}
	
	function &generateArticlePubDateDom(&$doc, &$article) {
		// get date-time as iso-8601 string
		$pubdateAsString = $article->getDatePublished();
		if ($pubdateAsString != '') {
			// create DateTime object so we can get bits and pieces at will
			$datetime = new DateTime($pubdateAsString);
			$root =& XMLCustomWriter::createElement($doc, 'pub-date');
			XMLCustomWriter::setAttribute($root, 'pub-type', 'epub');
			$date = $datetime->format('Y-m-d');
			XMLCustomWriter::setAttribute($root, 'iso-8601-date', $datetime->format('Y-m-d'));
			XMLCustomWriter::createChildWithText($doc, $root, 'day', $datetime->format('d'));
			XMLCustomWriter::createChildWithText($doc, $root, 'month', $datetime->format('m'));
			XMLCustomWriter::createChildWithText($doc, $root, 'year', $datetime->format('Y'));
			return $root;	
		}
		return false;
	}

	function createArticleSelfUriNode(&$doc, &$root, &$journal, &$article) {
		$journalUrl = $journal->getUrl();
		$articleId = $article->getBestArticleId($journal);
		$url = $journalUrl . '/article/view/' . $articleId;
		$statement = "This article is available online at " . $url;
		$node = XMLCustomWriter::createChildWithText($doc, $root, 'self-uri', $url);
		XMLCustomWriter::setAttribute($node, 'xlink:href', $url);
	}
	
	function &generateArticleAbstractDOM(&$doc, &$article) {
		$root = XMLCustomWriter::createElement($doc, 'abstract');
		$rawAbstract = $article->getLocalizedAbstract();
		if ($rawAbstract) {
			$as_br = nl2br($rawAbstract);
			$parts = explode('<br />', $as_br);
			foreach ($parts as $part) {
				$part = trim($part);
				if (strlen($part) > 0) {
					XMLCustomWriter::createChildWithText($doc, $root, 'p', $part);
				}
			}
		}
		return $root;
	}
	
	function &generateArticlePermissionsDOM(&$doc, &$article) {
		$root = XMLCustomWriter::createElement($doc, 'permissions');
		$copyrightText = "Copyright for this article is retained by the authors.";
		$year = false;
		$pubdateAsString = $article->getDatePublished();
		if ($pubdateAsString != '') {
			// create DateTime object so we can get bits and pieces at will
			$datetime = new DateTime($pubdateAsString);
			$year = $datetime->format('Y');
			$copyrightText = "Copyright: © " . $year . " The Authors.";
		}
		XMLCustomWriter::createChildWithText($doc, $root, 'copyright-statement', $copyrightText);
		if ($year) {
			XMLCustomWriter::createChildWithText($doc, $root, 'copyright-year', $year);
		}
		$licenseUrl = 'http://creativecommons.org/licenses/by-nc-nd/2.5/';
		$licenseNode = XMLCustomWriter::createElement($doc, 'license');
		XMLCustomWriter::setAttribute($licenseNode, 'license-type', 'open-access');
		XMLCustomWriter::setAttribute($licenseNode, 'xlink:href', $licenseUrl);
		$licenseText = " This is an open-access article distributed under the terms of the Creative Commons Attribution-NonCommercial-NoDerivs 2.5 License, which permits reproduction and distribution, provided the original work is properly cited. Commercial use and derivative works are not permitted.";
		XMLCustomWriter::createChildWithText($doc, $licenseNode, 'license-p', $licenseText);
		XMLCustomWriter::appendChild($root, $licenseNode);
		return $root;
	}

	function &generateHtmlBodyCData(&$doc, &$journal, &$article, &$galley) {
		$root =& XMLCustomWriter::createElement($doc, 'article-markup');
		XMLCustomWriter::setAttribute($root, 'galley-id', $galley->getBestGalleyId($journal));
		
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($article->getId());
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		
		$fileContent = $articleFileManager->readFile($galley->getFileId());
		$cdataSection = $doc->createCDATASection($fileContent);
		XMLCustomWriter::appendChild($root, $cdataSection);
		
		return $root;
	}

	function &generateGalleyDom(&$doc, &$journal, &$issue, &$article, &$galley) {
		$isHtml = $galley->isHTMLGalley();

		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($article->getId());
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');

		$root =& XMLCustomWriter::createElement($doc, $isHtml?'htmlgalley':'galley');
		XMLCustomWriter::setAttribute($root, 'public-id', $galley->getPubId('publisher-id'), false);
		XMLCustomWriter::setAttribute($root, 'galley-id', $galley->getBestGalleyId($journal));

		JATSExportDOM::generatePubId($doc, $root, $galley, $issue);

		XMLCustomWriter::createChildWithText($doc, $root, 'label', $galley->getLabel());

		/* --- Galley file --- */
		$fileNode =& XMLCustomWriter::createElement($doc, 'file');
		XMLCustomWriter::appendChild($root, $fileNode);
		// $embedNode =& XMLCustomWriter::createChildWithText($doc, $fileNode, 'embed', base64_encode($articleFileManager->readFile($galley->getFileId())));
		$pathNode =& XMLCustomWriter::createTextNode($doc, $galley->getFilePath());
		$articleFile =& $articleFileDao->getArticleFile($galley->getFileId());
		if (!$articleFile) return $articleFile; // Stupidity check
		XMLCustomWriter::appendChild($fileNode, $pathNode);
		XMLCustomWriter::setAttribute($fileNode, 'filename', $articleFile->getOriginalFileName());
		XMLCustomWriter::setAttribute($fileNode, 'mimetype', $articleFile->getFileType());

		/* --- HTML-specific data: Stylesheet and/or images --- */

		if ($isHtml) {
			$styleFile = $galley->getStyleFile();
			if ($styleFile) {
				$styleNode =& XMLCustomWriter::createElement($doc, 'stylesheet');
				XMLCustomWriter::appendChild($root, $styleNode);
				$pathNode =& XMLCustomWriter::createTextNode($doc, $styleFile->getFilePath());
				XMLCustomWriter::appendChild($styleNode, $pathNode);
				XMLCustomWriter::setAttribute($styleNode, 'filename', $styleFile->getOriginalFileName());
				XMLCustomWriter::setAttribute($styleNode, 'mimetype', 'text/css');
				unset($styleNode);
				unset($pathNode);
			}

			foreach ($galley->getImageFiles() as $imageFile) {
				$imageNode =& XMLCustomWriter::createElement($doc, 'image');
				XMLCustomWriter::appendChild($root, $imageNode);
				$pathNode =& XMLCustomWriter::createTextNode($doc, $imageFile->getFilePath());
				XMLCustomWriter::appendChild($imageNode, $pathNode);
				XMLCustomWriter::setAttribute($imageNode, 'filename', $imageFile->getOriginalFileName());
				XMLCustomWriter::setAttribute($imageNode, 'mimetype', $imageFile->getFileType());
				unset($imageNode);
				unset($pathNode);
			}
		}

		return $root;
	}

	function &generateSuppFileDom(&$doc, &$journal, &$issue, &$article, &$suppFile) {
		$root =& XMLCustomWriter::createElement($doc, 'supplemental-file');

		JATSExportDOM::generatePubId($doc, $root, $suppFile, $issue);

		// FIXME: These should be constants!
		switch ($suppFile->getType()) {
			case __('author.submit.suppFile.researchInstrument'):
				$suppFileType = 'research_instrument';
				break;
			case __('author.submit.suppFile.researchMaterials'):
				$suppFileType = 'research_materials';
				break;
			case __('author.submit.suppFile.researchResults'):
				$suppFileType = 'research_results';
				break;
			case __('author.submit.suppFile.transcripts'):
				$suppFileType = 'transcripts';
				break;
			case __('author.submit.suppFile.dataAnalysis'):
				$suppFileType = 'data_analysis';
				break;
			case __('author.submit.suppFile.dataSet'):
				$suppFileType = 'data_set';
				break;
			case __('author.submit.suppFile.sourceText'):
				$suppFileType = 'source_text';
				break;
			default:
				$suppFileType = 'other';
				break;
		}

		XMLCustomWriter::setAttribute($root, 'type', $suppFileType);
		XMLCustomWriter::setAttribute($root, 'public_id', $suppFile->getPubId('publisher-id'), false);
		XMLCustomWriter::setAttribute($root, 'language', $suppFile->getLanguage(), false);

		import('classes.file.ArticleFileManager');
		$pathNode =& XMLCustomWriter::createTextNode($doc, $suppFile->getFilePath());
		XMLCustomWriter::appendChild($root, $pathNode);
		XMLCustomWriter::setAttribute($root, 'filename', $suppFile->getOriginalFileName());
		XMLCustomWriter::setAttribute($root, 'encoding', 'base64');
		XMLCustomWriter::setAttribute($root, 'mime_type', $suppFile->getFileType());

		return $root;
	}

	function formatDate($date) {
		if ($date == '') return null;
		return date('Y-m-d', strtotime($date));
	}

	/**
	 * Add ID-nodes to the given node.
	 * @param $doc DOMDocument
	 * @param $node DOMNode
	 * @param $pubObject object
	 * @param $issue Issue
	 */
	function generatePubId(&$doc, &$node, &$pubObject, &$issue) {
		$pubIdPlugins =& PluginRegistry::loadCategory('pubIds', true, $issue->getJournalId());
		if (is_array($pubIdPlugins)) foreach ($pubIdPlugins as $pubIdPlugin) {
			if ($issue->getPublished()) {
				$pubId = $pubIdPlugin->getPubId($pubObject);
			} else {
				$pubId = $pubIdPlugin->getPubId($pubObject, true);
			}
			if ($pubId) {
				$pubIdType = $pubIdPlugin->getPubIdType();
				$idNode =& XMLCustomWriter::createChildWithText($doc, $node, 'id', $pubId);
				XMLCustomWriter::setAttribute($idNode, 'type', $pubIdType);
			}
		}
	}
	
	function generateArticleId(&$doc, &$node, &$article, &$issue) {
		$pubIdPlugins =& PluginRegistry::loadCategory('pubIds', true, $issue->getJournalId());
		if (is_array($pubIdPlugins)) foreach ($pubIdPlugins as $pubIdPlugin) {
			if ($issue->getPublished()) {
				$pubId = $pubIdPlugin->getPubId($article);
			} else {
				$pubId = $pubIdPlugin->getPubId($article, true);
			}
			if ($pubId) {
				$pubIdType = $pubIdPlugin->getPubIdType();
				$idNode =& XMLCustomWriter::createChildWithText($doc, $node, 'article-id', $pubId);
				XMLCustomWriter::setAttribute($idNode, 'pub-id-type', $pubIdType);
			}
		}
	}
}

?>
