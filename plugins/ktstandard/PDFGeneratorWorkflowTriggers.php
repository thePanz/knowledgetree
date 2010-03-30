<?php
/**
 * $Id$
 *
 * KnowledgeTree Community Edition
 * Document Management Made Simple
 * Copyright (C) 2008, 2009 KnowledgeTree Inc.
 * 
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 3 as published by the
 * Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco, 
 * California 94120-7775, or email info@knowledgetree.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * KnowledgeTree" logo and retain the original copyright notice. If the display of the 
 * logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
 * must display the words "Powered by KnowledgeTree" and retain the original 
 * copyright notice.
 * Contributor( s): thePanz (thepanz@gmail.com)
 *
 */

require_once(KT_LIB_DIR . '/workflow/workflowtrigger.inc.php');
require_once(KT_DIR . '/plugins/pdfConverter/pdfConverter.php');

class PDFGeneratorWorkflowTriggerDuplicatePDF extends KTWorkflowTrigger {
  var $sNamespace;
  var $sFriendlyName;
  var $sDescription;
  var $oTriggerInstance;
  var $aConfig = array();

  // generic requirements - both can be true
  var $bIsGuard = false;
  var $bIsAction = true;

  public function PDFGeneratorWorkflowTriggerDuplicatePDF() {
    $this->sNamespace = 'ktcore.workflowtriggers.pdfgenerator.duplicate';
    $this->sFriendlyName = _kt('Copy the Document as PDF');
    $this->sDescription = _kt('This action will create a pdf copy of the document as a new document.');
  }

  // perform more expensive checks -before- performTransition.
  // Taken from : plugins\ktcore\KTWorkflowTriggers.inc.php
  public function precheckTransition($oDocument, $oUser) {
    $iFolderId = KTUtil::arrayGet($this->aConfig, 'folder_id');
    $oFolder = Folder::get($iFolderId);
    if (PEAR::isError($oFolder)) {
      return PEAR::raiseError(_kt('The folder to which this document should be copied as PDF does not exist.  Cancelling the transition - please contact a system administrator.'));
    }

    return true;
  }
  
  /**
   * Actually duplicate the Document: PDF creation, document copy, and PDF association
   */
  function performTransition($oDocument, $oUser) {
    global $default;
    $iFolderId = KTUtil::arrayGet($this->aConfig, 'folder_id');
    $oToFolder = Folder::get($iFolderId);
    if (PEAR::isError($oFolder)) {
      return PEAR::raiseError(_kt('The folder to which this document should be copied as PDF does not exist.  Cancelling the transition - please contact a system administrator.'));
    }

    // Create the PDF
    $default->log->error("Create the PDF");
    $pdfFile = $this->createPDF($oDocument);
    if (PEAR::isError($pdfFile)) {
      return $pdfFile;
    }

    $default->log->error("Duplicate-and-Copy the Document");
    //  Duplicate/Copy the Document
    $oNewDocument = KTDocumentUtil::copy($oDocument, $oToFolder);
    if (PEAR::isError($oNewDocument)) {
      return $oNewDocument;
    }

    $default->log->error("Associate PDF with the new document [$pdfFile]");
    // Associate PDF with the new document
    $aOptions = array(
      'temp_file' => $pdfFile,
      'cleanup_initial_file' => false,
    );

    $res = KTDocumentUtil::storeContents($oNewDocument, $oContents = null, $aOptions);
    if (PEAR::isError($res)) {
      $default->log->error("PDF Error: can't associate PDF file to document. storeDocument error = " . $res->message);
      // Remove the created document (not-correct)
      KTDocumentUtil::delete($oNewDocument, _kt("PDF WorkflowTrigger error: can't create associate PDF file to document."));
      return $res;
    }
    
    // Change filename reporeted by Document: renamed to .PDF
    // TODO: maybe use the KTDocumentUtil::rename($oDocument, $sFilename, $oUser) for rename+MimeType?
    $sFilename= pathinfo($oNewDocument->getFileName(), PATHINFO_FILENAME);
    $oNewDocument->setFileName($sFilename . '.pdf');
    
    // Changing MIME-Filetype
    $iMimeTypeId = KTMime::getMimeTypeID('application/pdf');
    $oNewDocument->setMimeTypeId($iMimeTypeId);
    
    $bSuccess = $oNewDocument->update();
    if ($bSuccess !== true) {
      return PEAR::raiseError(_kt("PDF WorkflowTrigger error: can't finalize document data as PDF."));
    }
    
    // Returns the new document, so other plugin can extend this class to further handle the new PDF document
    return $oNewDocument;
  }


  /**
   * Create the PDF file for the given document (or re-use the already created one)
   * Returns the PDF filename or PEAR::error
   */
  private function createPDF(&$oDocument) {
    global $default;
    $dir = $default->pdfDirectory;
    $iDocId = $oDocument->iId;
    $file = $dir .'/'. $iDocId . '.pdf';
    if (!file_exists($file)) {
      // If not - create one
      $converter = new pdfConverter();
      $converter->setDocument($oDocument);
      $res = $converter->processDocument();
      if ($res !== true) {
        $default->log->error(__class__ . '::' . __function__ . '() PDF file could not be generated.');
        return PEAR::raiseError(_kt('PDF file could not be generated; Please contact your System Administrator for assistance.'));
      }
    }
    return $file;
  }

  function displayConfiguration($args) {
    $oTemplating =& KTTemplating::getSingleton();
    $oTemplate = $oTemplating->loadTemplate('ktcore/workflowtriggers/copyaction');

    require_once(KT_LIB_DIR . '/browse/DocumentCollection.inc.php');
    require_once(KT_LIB_DIR . '/browse/columnregistry.inc.php');

    $collection = new AdvancedCollection;
    $oColumnRegistry = KTColumnRegistry::getSingleton();
    $aColumns = array();
    $aColumns[] = $oColumnRegistry->getColumn('ktcore.columns.singleselection');
    $aColumns[] = $oColumnRegistry->getColumn('ktcore.columns.title');

    $collection->addColumns($aColumns);

    $aOptions = $collection->getEnvironOptions(); // extract data from the environment


    $qsFrag = array();
    foreach ($args as $k => $v) {
      if ($k == 'action') { $v = 'editactiontrigger'; } // horrible hack - we really need iframe embedding.
      $qsFrag[] = sprintf('%s=%s',urlencode($k), urlencode($v));
    }
    $qs = implode('&',$qsFrag);
    $aOptions['result_url'] = KTUtil::addQueryStringSelf($qs);
    $aOptions['show_documents'] = false;

    $fFolderId = KTUtil::arrayGet($_REQUEST, 'fFolderId', KTUtil::arrayGet($this->aConfig, 'folder_id', 1));

    $oFolder = Folder::get($fFolderId);
    if(PEAR::isError($oFolder)) {
      $iRoot = 1;
      $oFolder = Folder::get($iRoot);
      $fFolderId = 1;
    }

    $collection->setOptions($aOptions);
    $collection->setQueryObject(new BrowseQuery($fFolderId, $this->oUser));
    $collection->setColumnOptions('ktcore.columns.singleselection', array(
      'rangename' => 'folder_id',
      'show_folders' => true,
      'show_documents' => false,
    ));

    $collection->setColumnOptions('ktcore.columns.title', array(
      'direct_folder' => false,
      'folder_link' => $aOptions['result_url'],
    ));


    $aBreadcrumbs = array();
    $folder_path_names = $oFolder->getPathArray();
    $folder_path_ids = explode(',', $oFolder->getParentFolderIds());
    $folder_path_ids[] = $oFolder->getId();
    if ($folder_path_ids[0] == 0) {
      array_shift($folder_path_ids);
      array_shift($folder_path_names);
    }

    foreach (range(0, count($folder_path_ids) - 1) as $index) {
      $id = $folder_path_ids[$index];
      $qsFrag2 = $qsFrag;
      $qsFrag2[] = sprintf('fFolderId=%d', $id);
      $qs2 = implode('&',$qsFrag2);
      $url = KTUtil::addQueryStringSelf($qs2);
      $aBreadcrumbs[] = sprintf('<a href="%s">%s</a>', $url, htmlentities($folder_path_names[$index], ENT_NOQUOTES, 'UTF-8'));
    }

    $sBreadcrumbs = implode(' &raquo; ', $aBreadcrumbs);

    $aTemplateData = array(
      'context' => $this,
      'breadcrumbs' => $sBreadcrumbs,
      'collection' => $collection,
      'args' => $args,
    );
    return $oTemplate->render($aTemplateData);
  }

  function saveConfiguration() {
    $folder_id = KTUtil::arrayGet($_REQUEST, 'folder_id', null);
    $oFolder = Folder::get($folder_id);
    if (PEAR::isError($oFolder)) {
      // silenty ignore
      $folder_id = null;
    }

    $config = array();
    $config['folder_id'] = $folder_id;

    $this->oTriggerInstance->setConfig($config);
    $res = $this->oTriggerInstance->update();

    return $res;
  }

  function getConfigDescription() {
    if (!$this->isLoaded()) {
        return _kt('This trigger has no configuration.');
    }
    // the actual permissions are stored in the array.
    $perms = array();
    if (empty($this->aConfig) || is_null($this->aConfig['folder_id'])) {
         return _kt('<strong>This transition cannot be performed:  no folder has been selected.</strong>');
    }
    $oFolder = Folder::get($this->aConfig['folder_id']);
    if (PEAR::isError($oFolder)) {
        return _kt('<strong>The folder required for this trigger has been deleted, so the transition cannot be performed.</strong>');
    } else {
      return sprintf(_kt('The document will be copied as PDF to folder "<a href="%s">%s</a>".'), KTBrowseUtil::getUrlForFolder($oFolder), htmlentities($oFolder->getName(), ENT_NOQUOTES, 'UTF-8'));
    }
  }
}