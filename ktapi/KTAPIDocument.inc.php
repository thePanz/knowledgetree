<?php
/**
 * $Id$
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2007 The Jam Warehouse Software (Pty) Limited
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
 * You can contact The Jam Warehouse Software (Pty) Limited, Unit 1, Tramber Place,
 * Blake Street, Observatory, 7925 South Africa. or email info@knowledgetree.com.
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
 * Contributor( s): ______________________________________
 *
 */

class KTAPI_Document extends KTAPI_FolderItem
{
	/**
	 * This is a reference to the internal document object.
	 *
	 * @var Document
	 */
	var $document;
	/**
	 * This is the id of the document.
	 *
	 * @var int
	 */
	var $documentid;
	/**
	 * This is a reference to the parent folder.
	 *
	 * @var KTAPI_Folder
	 */
	var $ktapi_folder;

	public function get_documentid()
	{
		return $this->documentid;
	}

	/**
	 * This is used to get a document based on document id.
	 *
	 * @static
	 * @access public
	 * @param KTAPI $ktapi
	 * @param int $documentid
	 * @return KTAPI_Document
	 */
	public static function &get(&$ktapi, $documentid)
	{
		assert(!is_null($ktapi));
		assert(is_a($ktapi, 'KTAPI'));
		assert(is_numeric($documentid));

		$documentid += 0;

		$document = &Document::get($documentid);
		if (is_null($document) || PEAR::isError($document))
		{
			return new KTAPI_Error(KTAPI_ERROR_DOCUMENT_INVALID,$document );
		}

		$user = $ktapi->can_user_access_object_requiring_permission($document, KTAPI_PERMISSION_READ);

		if (is_null($user) || PEAR::isError($user))
		{
			return $user;
		}

		$folderid = $document->getParentID();

		if (!is_null($folderid))
		{
			$ktapi_folder = &KTAPI_Folder::get($ktapi, $folderid);
		}
		else
		{
			$ktapi_folder = null;
		}
		// We don't do any checks on this folder as it could possibly be deleted, and is not required right now.

		return new KTAPI_Document($ktapi, $ktapi_folder, $document);
	}

	public function is_deleted()
	{
		return ($this->document->getStatusID() == 3);
	}

	/**
	 * This is the constructor for the KTAPI_Folder.
	 *
	 * @access private
	 * @param KTAPI $ktapi
	 * @param Document $document
	 * @return KTAPI_Document
	 */
	public function KTAPI_Document(&$ktapi, &$ktapi_folder, &$document)
	{
		assert(is_a($ktapi,'KTAPI'));
		assert(is_null($ktapi_folder) || is_a($ktapi_folder,'KTAPI_Folder'));

		$this->ktapi = &$ktapi;
		$this->ktapi_folder = &$ktapi_folder;
		$this->document = &$document;
		$this->documentid = $document->getId();
	}

	/**
	 * This checks a document into the repository
	 *
	 * @param string $filename
	 * @param string $reason
	 * @param string $tempfilename
	 * @param bool $major_update
	 */
	public function checkin($filename, $reason, $tempfilename, $major_update=false)
	{
		if (!is_file($tempfilename))
		{
			return new PEAR_Error('File does not exist.');
		}

		$user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if (!$this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_NOT_CHECKED_OUT);
		}

		$options = array('major_update'=>$major_update);

		$currentfilename = $this->document->getFileName();
		if ($filename != $currentfilename)
		{
			$options['newfilename'] = $filename;
		}

		DBUtil::startTransaction();
		$result = KTDocumentUtil::checkin($this->document, $tempfilename, $reason, $user, $options);

		if (PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$result);
		}
		DBUtil::commit();

		$tempfilename=addslashes($tempfilename);
		$sql = "DELETE FROM uploaded_files WHERE tempfilename='$tempfilename'";
		$result = DBUtil::runQuery($sql);
		if (PEAR::isError($result))
		{
			return $result;
		}

	}

	/**
	 * Link a document to another
	 *
	 * @param KTAPI_Document $document
	 */
	public function link_document($document, $type)
	{
		$typeid = $this->ktapi->get_link_type_id($type);
		if (PEAR::isError($typeid))
		{
			return $result;
		}

		$link = new DocumentLink($this->get_documentid(), $document->get_documentid(), $typeid );
		$created = $link->create();
		if ($created === false || PEAR::isError($created))
		{
			return new PEAR_Error(_kt('Could not create link'));
		}
	}

	/**
	 * Unlink a document to another
	 *
	 * @param KTAPI_Document $document
	 */
	public function unlink_document($document)
	{
		$sql = "DELETE FROM document_link WHERE parent_document_id=$this->documentid AND child_document_id=$document->documentid";
		$result = DBUtil::runQuery($sql);
		if (empty($result) || PEAR::isError($created))
		{
			return new PEAR_Error(_kt('Could not remove link'));
		}
	}


	/**
	 *
	 * @return boolean
	 */
	public function is_checked_out()
	{
		return ($this->document->getIsCheckedOut());
	}

	/**
	 * This reverses the checkout process.
	 *
	 * @param string $reason
	 */
	public function undo_checkout($reason)
	{
		$user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if (!$this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_NOT_CHECKED_OUT);
		}

		DBUtil::startTransaction();

		$this->document->setIsCheckedOut(0);
		$this->document->setCheckedOutUserID(-1);
		$res = $this->document->update();
		if (($res === false) || PEAR::isError($res))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res);
		}

		$oDocumentTransaction = & new DocumentTransaction($this->document, $reason, 'ktcore.transactions.force_checkin');

		$res = $oDocumentTransaction->create();
		if (($res === false) || PEAR::isError($res)) {
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res);
		}
		DBUtil::commit();
	}

	public function get_linked_documents()
	{
		$sql = "
		SELECT
			dl.child_document_id as document_id,
			dmv.name as title,
			dcv.size,
			w.name as workflow,
			ws.name as workflow_state,
			dlt.name as link_type
		FROM
			document_link dl
			INNER JOIN document_link_types dlt ON dl.link_type_id=dlt.id
			INNER JOIN documents d ON dl.child_document_id=d.id
			INNER JOIN document_metadata_version dmv ON d.metadata_version_id=dmv.id
			INNER JOIN document_content_version dcv ON dmv.content_version_id=dcv.id
			LEFT OUTER JOIN workflow_documents wd ON d.id=wd.document_id
			LEFT OUTER JOIN workflows w ON w.id=wd.workflow_id
			LEFT OUTER JOIN workflow_states ws ON wd.state_id=ws.id
		WHERE
			dl.parent_document_id=$this->documentid

		";
		$rows = DBUtil::getResultArray($sql);
		if (PEAR::isError($rows))
		{
			return $rows;
		}
		$result=array();
		$read_permission = &KTPermission::getByName(KTAPI_PERMISSION_READ);
		$user = $this->ktapi->get_user();

		foreach($rows as $row)
		{
			$document = Document::get($row['document_id']);
			if (PEAR::isError($document) || is_null($document))
			{
				continue;
			}
			if(!KTPermissionUtil::userHasPermissionOnItem($user, $read_permission, $document))
			{
				continue;
			}



			$result[] = array(
					'document_id'=>(int)$row['document_id'],
					'title'=> $row['title'],
					'size'=>(int)$row['size'],
					'workflow'=>empty($row['workflow'])?'n/a':$row['workflow'],
					'workflow_state'=>empty($row['workflow_state'])?'n/a':$row['workflow_state'],
					'link_type'=>empty($row['link_type'])?'unknown':$row['link_type'],
				);
		}

		return $result;
	}


	/**
	 * This returns a URL to the file that can be downloaded.
	 *
	 * @param string $reason
	 */
	public function checkout($reason)
	{
		$user = $this->can_user_access_object_requiring_permission($this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if ($this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		DBUtil::startTransaction();
		$res = KTDocumentUtil::checkout($this->document, $reason, $user);
		if (PEAR::isError($res))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
		}

		DBUtil::commit();
	}

	/**
	 * This deletes a document from the folder.
	 *
	 * @param string $reason
	 */
	public function delete($reason)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_DELETE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if ($this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		DBUtil::startTransaction();
		$res = KTDocumentUtil::delete($this->document, $reason);
		if (PEAR::isError($res))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
		}

		DBUtil::commit();
	}

	/**
	 * This changes the owner of the file.
	 *
	 * @param string $ktapi_newuser
	 */
	public function change_owner($newusername, $reason='Changing of owner.')
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_CHANGE_OWNERSHIP);

		if (PEAR::isError($user))
		{
			return $user;
		}

        DBUtil::startTransaction();

        $user = &User::getByUserName($newusername);
        if (is_null($user) || PEAR::isError($user))
        {
        	return new KTAPI_Error('User could not be found',$user);
        }

        $newuserid = $user->getId();

        $this->document->setOwnerID($newuserid);

        $res = $this->document->update();

        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR ,$res );
        }

        $res = KTPermissionUtil::updatePermissionLookup($this->document);
        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }

		$oDocumentTransaction = & new DocumentTransaction($this->document, $reason, 'ktcore.transactions.permissions_change');

		$res = $oDocumentTransaction->create();
		if (($res === false) || PEAR::isError($res)) {
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
		}

		DBUtil::commit();
	}

	/**
	 * This copies the document to another folder.
	 *
	 * @param KTAPI_Folder $ktapi_target_folder
	 * @param string $reason
	 * @param string $newname
	 * @param string $newfilename
	 * @return KTAPI_Document
	 */
	public function copy(&$ktapi_target_folder, $reason, $newname=null, $newfilename=null)
	{
		assert(!is_null($ktapi_target_folder));
		assert(is_a($ktapi_target_folder,'KTAPI_Folder'));

		if (empty($newname))
		{
			$newname=null;
		}
		if (empty($newfilename))
		{
			$newfilename=null;
		}

		$user = $this->ktapi->get_user();

		if ($this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		$target_folder = &$ktapi_target_folder->get_folder();

		$result = $this->can_user_access_object_requiring_permission(  $target_folder, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($result))
		{
			return $result;
		}

		$name = $this->document->getName();
		$clash = KTDocumentUtil::nameExists($target_folder, $name);
        if ($clash && !is_null($newname))
        {
        	$name = $newname;
        	$clash = KTDocumentUtil::nameExists($target_folder, $name);
        }
        if ($clash)
        {
        	return new PEAR_Error('A document with this title already exists in your chosen folder.  Please choose a different folder, or specify a new title for the copied document.');
        }

        $filename=$this->document->getFilename();
        $clash = KTDocumentUtil::fileExists($target_folder, $filename);

        if ($clash && !is_null($newname))
        {
			$filename = $newfilename;
            $clash = KTDocumentUtil::fileExists($target_folder, $filename);
        }
        if ($clash)
        {
        	return new PEAR_Error('A document with this filename already exists in your chosen folder.  Please choose a different folder, or specify a new filename for the copied document.');
        }

		DBUtil::startTransaction();

        $new_document = KTDocumentUtil::copy($this->document, $target_folder, $reason);
        if (PEAR::isError($new_document))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$new_document );
        }

        $new_document->setName($name);
        $new_document->setFilename($filename);

        $res = $new_document->update();

        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }

        DBUtil::commit();

        // FIXME do we need to refactor all trigger usage into the util function?
        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('copyDocument', 'postValidate');
        foreach ($aTriggers as $aTrigger) {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                'document' => $new_document,
                'old_folder' => $this->ktapi_folder->get_folder(),
                'new_folder' => $target_folder,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
        }

        return KTAPI_Document::get($this->ktapi, $new_document->getId());
	}

	/**
	 * This moves the document to another folder.
	 *
	 * @param KTAPI_Folder $ktapi_target_folder
	 * @param string $reason
	 * @param string $newname
	 * @param string $newfilename
	 */
	public function move(&$ktapi_target_folder, $reason, $newname=null, $newfilename=null)
	{
		assert(!is_null($ktapi_target_folder));
		assert(is_a($ktapi_target_folder,'KTAPI_Folder'));

		if (empty($newname))
		{
			$newname=null;
		}
		if (empty($newfilename))
		{
			$newfilename=null;
		}

		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_DOCUMENT_MOVE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if ($this->document->getIsCheckedOut())
		{
			return new PEAR_Error(KTAPI_ERROR_DOCUMENT_CHECKED_OUT);
		}

		$target_folder = $ktapi_target_folder->get_folder();

		$result=  $this->can_user_access_object_requiring_permission(  $target_folder, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($result))
		{
			return $result;
		}

		if (!KTDocumentUtil::canBeMoved($this->document))
		{
			return new PEAR_Error('Document cannot be moved.');
		}

		$name = $this->document->getName();
		$clash = KTDocumentUtil::nameExists($target_folder, $name);
        if ($clash && !is_null($newname))
        {
        	$name = $newname;
        	$clash = KTDocumentUtil::nameExists($target_folder, $name);
        }
        if ($clash)
        {
        	return new PEAR_Error('A document with this title already exists in your chosen folder.  Please choose a different folder, or specify a new title for the moved document.');
        }

        $filename=$this->document->getFilename();
        $clash = KTDocumentUtil::fileExists($target_folder, $filename);

        if ($clash && !is_null($newname))
        {
			$filename = $newfilename;
            $clash = KTDocumentUtil::fileExists($target_folder, $filename);
        }
        if ($clash)
        {
        	return new PEAR_Error('A document with this filename already exists in your chosen folder.  Please choose a different folder, or specify a new filename for the moved document.');
        }

		DBUtil::startTransaction();

        $res = KTDocumentUtil::move($this->document, $target_folder, $user, $reason);
        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res );
        }

        $this->document->setName($name);
        $this->document->setFilename($filename);

        $res = $this->document->update();

        if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }

        DBUtil::commit();
	}

	/**
	 * This changes the filename of the document.
	 *
	 * @param string $newname
	 */
	public function renameFile($newname)
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		DBUtil::startTransaction();
		$res = KTDocumentUtil::rename($this->document, $newname, $user);
		if (PEAR::isError($res))
        {
            DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
        }
        DBUtil::commit();
	}

	/**
	 * This changes the document type of the document.
	 *
	 * @param string $newname
	 */
	public function change_document_type($documenttype)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$doctypeid = KTAPI::get_documenttypeid($documenttype);
		if (PEAR::isError($doctypeid))
		{
			return $doctypeid;
		}

		if ($this->document->getDocumentTypeId() != $doctypeid)
		{
			DBUtil::startTransaction();
			$this->document->setDocumentTypeId($doctypeid);
			$res = $this->document->update();

			if (PEAR::isError($res))
			{
				DBUtil::rollback();
				return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR,$res );
			}


			$metadata = $this->get_packed_metadata();

		    $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
            $aTriggers = $oKTTriggerRegistry->getTriggers('edit', 'postValidate');

            foreach ($aTriggers as $aTrigger)
            {
                $sTrigger = $aTrigger[0];
                $oTrigger = new $sTrigger;
                $aInfo = array(
                    "document" => $this->document,
                    "aOptions" => $packed,
                );
                $oTrigger->setInfo($aInfo);
                $ret = $oTrigger->postValidate();
            }

            DBUtil::commit();

		}
	}

	/**
	 * This changes the title of the document.
	 *
	 * @param string $newname
	 */
	public function rename($newname)
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		if ($this->document->getName() != $newname)
		{

			DBUtil::startTransaction();
			$this->document->setName($newname);
			$res = $this->document->update();

			if (PEAR::isError($res))
			{
				DBUtil::rollback();
				return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
			}
			DBUtil::commit();
		}
	}

	/**
	 * This flags the document as 'archived'.
	 *
	 * @param string $reason
	 */
	public function archive($reason)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_WRITE);

		if (PEAR::isError($user))
		{
			return $user;
		}

		list($permission, $user) = $perm_and_user;

		DBUtil::startTransaction();
		$this->document->setStatusID(ARCHIVED);
        $res = $this->document->update();
        if (($res === false) || PEAR::isError($res)) {
           DBUtil::rollback();
           return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $res);
        }

        $oDocumentTransaction = & new DocumentTransaction($this->document, sprintf(_kt('Document archived: %s'), $reason), 'ktcore.transactions.update');
        $oDocumentTransaction->create();

        DBUtil::commit();

        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('archive', 'postValidate');
        foreach ($aTriggers as $aTrigger)
        {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                'document' => $this->document,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
        }
	}

	/**
	 * This starts a workflow on a document.
	 *
	 * @param string $workflow
	 */
	public function start_workflow($workflow)
	{
		$user = $this->can_user_access_object_requiring_permission( $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid = $this->document->getWorkflowId();

		if (!empty($workflowid))
		{
			return new PEAR_Error('A workflow is already defined.');
		}

		$workflow = KTWorkflow::getByName($workflow);
		if (is_null($workflow) || PEAR::isError($workflow))
		{
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $workflow);
		}

		DBUtil::startTransaction();
		$result = KTWorkflowUtil::startWorkflowOnDocument($workflow, $this->document);
		if (is_null($result) || PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $result);
		}
		DBUtil::commit();
	}

	/**
	 * This deletes the workflow on the document.
	 *
	 */
	public function delete_workflow()
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		DBUtil::startTransaction();
		$result = KTWorkflowUtil::startWorkflowOnDocument(null, $this->document);
		if (is_null($result) || PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID,$result);
		}
		DBUtil::commit();
	}

	/**
	 * This performs a transition on the workflow
	 *
	 * @param string $transition
	 * @param string $reason
	 */
	public function perform_workflow_transition($transition, $reason)
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		$transition = &KTWorkflowTransition::getByName($transition);
		if (is_null($transition) || PEAR::isError($transition))
		{
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $transition);
		}

		DBUtil::startTransaction();
		$result = KTWorkflowUtil::performTransitionOnDocument($transition, $this->document, $user, $reason);
		if (is_null($result) || PEAR::isError($result))
		{
			DBUtil::rollback();
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $transition);
		}
		DBUtil::commit();
	}



	/**
	 * This returns all metadata for the document.
	 *
	 * @return array
	 */
	public function get_metadata()
	{
		 $doctypeid = $this->document->getDocumentTypeID();
		 $fieldsets = (array) KTMetadataUtil::fieldsetsForDocument($this->document, $doctypeid);
		 if (is_null($fieldsets) || PEAR::isError($fieldsets))
		 {
		     return array();
		 }

		 $results = array();

		 foreach ($fieldsets as $fieldset)
		 {
		 	if ($fieldset->getIsConditional()) {	/* this is not implemented...*/	continue;	}

		 	$fields = $fieldset->getFields();
		 	$result = array('fieldset' => $fieldset->getName(),
		 					'description' => $fieldset->getDescription());

		 	$fieldsresult = array();

            foreach ($fields as $field)
            {
                $value = 'n/a';

				$fieldvalue = DocumentFieldLink::getByDocumentAndField($this->document, $field);
                if (!is_null($fieldvalue) && (!PEAR::isError($fieldvalue)))
                {
                	$value = $fieldvalue->getValue();
                }

                $controltype = 'string';
                if ($field->getHasLookup())
                {
                	$controltype = 'lookup';
                    if ($field->getHasLookupTree())
                    {
                    	$controltype = 'tree';
                    }
                }

                switch ($controltype)
                {
                	case 'lookup':
                		$selection = KTAPI::get_metadata_lookup($field->getId());
                		break;
                	case 'tree':
                		$selection = KTAPI::get_metadata_tree($field->getId());
                		break;
                	default:
                		$selection= array();
                }


                $fieldsresult[] = array(
                	'name' => $field->getName(),
                	'required' => $field->getIsMandatory(),
                	'value' => $value,
                    'description' => $field->getDescription(),
                    'control_type' => $controltype,
                    'selection' => $selection

                );

            }
            $result['fields'] = $fieldsresult;
            $results [] = $result;
		 }

		 return $results;
	}

	public function get_packed_metadata($metadata=null)
	{
		global $default;

		if (is_null($metadata))
		{
		    $metadata = $this->get_metadata();
		}

		 $packed = array();

		 foreach($metadata as $fieldset_metadata)
		 {
		 	if (is_array($fieldset_metadata))
		 	{
		 		$fieldsetname=$fieldset_metadata['fieldset'];
		 		$fields=$fieldset_metadata['fields'];
		 	}
		 	elseif (is_a($fieldset_metadata, 'stdClass'))
		 	{
		 		$fieldsetname=$fieldset_metadata->fieldset;
		 		$fields=$fieldset_metadata->fields;
		 	}
		 	else
		 	{
		 		$default->log->debug("unexpected fieldset type");
		 		continue;
		 	}

		 	$fieldset = KTFieldset::getByName($fieldsetname);
		 	if (is_null($fieldset) || PEAR::isError($fieldset))
		 	{
		 		$default->log->debug("could not resolve fieldset: $fieldsetname");
		 		// exit graciously
		 		continue;
		 	}

		 	foreach($fields as $fieldinfo)
		 	{
		 		if (is_array($fieldinfo))
		 		{
		 			$fieldname = $fieldinfo['name'];
		 			$value = $fieldinfo['value'];
		 		}
		 		elseif (is_a($fieldinfo, 'stdClass'))
		 		{
		 			$fieldname = $fieldinfo->name;
		 			$value = $fieldinfo->value;
		 		}
		 		else
		 		{
		 			$default->log->debug("unexpected fieldinfo type");
		 			continue;
		 		}

		 		$field = DocumentField::getByFieldsetAndName($fieldset, $fieldname);
		 		if (is_null($field) || PEAR::isError($fieldset))
		 		{
		 			$default->log->debug("could not resolve field: $fieldname");
		 			// exit graciously
		 			continue;
		 		}

		 		$packed[] = array($field, $value);
		 	}
		 }

		 return $packed;
	}

	/**
	 * This updates the metadata on the file. This includes the 'title'.
	 *
	 * @param array This is an array containing the metadata to be associated with the file.
	 */
	public function update_metadata($metadata)
	{
		global $default;
		 $packed = $this->get_packed_metadata($metadata);

		 DBUtil::startTransaction();
		 $result = KTDocumentUtil::saveMetadata($this->document, $packed);

		 if (is_null($result))
		 {
		 	DBUtil::rollback();
		 	return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR);
		 }
		 if (PEAR::isError($result))
		 {
		 	DBUtil::rollback();
		 	return new KTAPI_Error(sprintf(_kt("Unexpected validation failure: %s."), $result->getMessage()));
		 }
		 DBUtil::commit();


        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('edit', 'postValidate');

        foreach ($aTriggers as $aTrigger) {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                "document" => $this->document,
                "aOptions" => $packed,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
        }

	}

	/**
	 * This updates the system metadata on the document.
	 *
	 * @param array $sysdata
	 */
	public function update_sysdata($sysdata)
	{
		if (empty($sysdata))
		{
			return;
		}
		$owner_mapping = array(
						'created_by'=>'creator_id',
						'modified_by'=>'modified_user_id',
						'owner'=>'owner'
						);

		$documents = array();
		$document_content = array();

		foreach($sysdata as $rec)
		{
			if (is_object($rec))
			{
				$name = $rec->name;
				$value = sanitizeForSQL($rec->value);
			}
			elseif(is_array($rec))
			{
				$name = $rec['name'];
				$value = sanitizeForSQL($rec['value']);
			}
			else
			{
				// just ignore
				continue;
			}
			switch($name)
			{
				case 'created_date':
					$documents['created'] = $value;
					break;
				case 'modified_date':
					$documents['modified'] = $value;
					break;
				case 'is_immutable':
					$documents['immutable'] = KTUtil::strToBool($value, false);
					break;
				case 'filename':
					$document_content['filename'] = $value;
					break;
				case 'major_version':
					$document_content['major_version'] = $value;
					break;
				case 'minor_version':
					$document_content['minor_version'] = $value;
					break;
				case 'version':
					$version = number_format($value + 0,5);
					list($major_version, $minor_version) = explode('.', $version);
					$document_content['major_version'] = $major_version;
					$document_content['minor_version'] = $minor_version;
					break;
				case 'mime_type':
					$value = KTMime::getMimeIdByName($value);
					if (PEAR::isError($value))
					{
						return $value;
					}
					$document_content['mime_id'] = $value;
					break;
				case 'owner':
				case 'created_by':
				case 'modified_by':
					$sql = "select id from users where name='$value'";
					$userId = DBUtil::getResultArray($sql);
					if (PEAR::isError($userId))
					{
						return $userId;
					}
					if (empty($userId))
					{
						$sql = "select id from users where username='$value'";
						$userId = DBUtil::getResultArray($sql);
						if (PEAR::isError($value))
						{
							return $value;
						}
					}
					if (empty($userId))
					{
						// if not found, not much we can do
						break;
					}
					$userId=$userId[0];
					$userId=$userId['id'];

					$name = $owner_mapping[$name];
					$documents[$name] = $userId;
					break;
				default:
					return new PEAR_Error('Unexpected field: ' . $name);
			}
		}

		if (count($documents) > 0)
		{
			$sql = "UPDATE documents SET ";
			$i=0;
			foreach($documents as $name=>$value)
			{
				if ($i++ > 0) $sql .= ",";
				$sql .= "$name='$value'";
			}
			$sql .= " WHERE id=$this->documentid";
			$result = DBUtil::runQuery($sql);
			if (PEAR::isError($result))
			{
				return $result;
			}
		}
		if (count($document_content) > 0)
		{
			$content_id = $this->document->getContentVersionId();
			$sql = "UPDATE document_content_version SET ";
			$i=0;
			foreach($documents as $name=>$value)
			{
				if ($i++ > 0) $sql .= ",";
				$sql .= "$name='$value'";
			}
			$sql .= " WHERE id=$content_id";
			$result = DBUtil::runQuery($sql);
			if (PEAR::isError($result))
			{
				return $result;
			}
		}
	}

	private function clearCache()
	{
		// TODO: we should only clear the cache for the document we are working on
		// this is a quick fix but not optimal!!


		$metadataid = $this->document->getMetadataVersionId();
		$contentid = $this->document->getContentVersionId();

		$cache = KTCache::getSingleton();

		$cache->remove('KTDocumentMetadataVersion/id', $metadataid);
		$cache->remove('KTDocumentContentVersion/id', $contentid);
		$cache->remove('KTDocumentCore/id', $this->documentid);
		$cache->remove('Document/id', $this->documentid);
		unset($GLOBALS['_OBJECTCACHE']['KTDocumentMetadataVersion'][$metadataid]);
		unset($GLOBALS['_OBJECTCACHE']['KTDocumentContentVersion'][$contentid]);
		unset($GLOBALS['_OBJECTCACHE']['KTDocumentCore'][$this->documentid]);

		$this->document = &Document::get($this->documentid);
	}

	public function mergeWithLastMetadataVersion()
	{
		// keep latest metadata version
		$metadata_version = $this->document->getMetadataVersion();
		if ($metadata_version == 0)
		{
			return new PEAR_Error('MetadataVersion cannot be merged');
		}

		$metadata_id = $this->document->getMetadataVersionId();

		// get previous version
		$sql = "SELECT id, metadata_version FROM document_metadata_version WHERE id<$metadata_id AND document_id=$this->documentid order by id desc";
		$old = DBUtil::getResultArray($sql);
		if (is_null($old) || PEAR::isError($old))
		{
			return new PEAR_Error('Previous version could not be resolved');
		}
		// only interested in the first one
		$old=$old[0];
		$old_metadata_id = $old['id'];
		$old_metadata_version = $old['metadata_version'];

		DBUtil::startTransaction();

		// delete previous metadata version

		$sql = "DELETE FROM document_metadata_version WHERE id=$old_metadata_id";
		$rs = DBUtil::runQuery($sql);
		if (PEAR::isError($rs))
		{
			DBUtil::rollback();
			return $rs;
		}

		// make latest equal to previous
		$sql = "UPDATE document_metadata_version SET metadata_version=$old_metadata_version WHERE id=$metadata_id";
		$rs = DBUtil::runQuery($sql);
		if (PEAR::isError($rs))
		{
			DBUtil::rollback();
			return $rs;
		}
		$sql = "UPDATE documents SET metadata_version=$old_metadata_version WHERE id=$this->documentid";
		$rs = DBUtil::runQuery($sql);
		if (PEAR::isError($rs))
		{
			DBUtil::rollback();
			return $rs;
		}
		DBUtil::commit();

		$this->clearCache();
	}

	/**
	 * This returns a workflow transition
	 *
	 * @return array
	 */
	public function get_workflow_transitions()
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		$result = array();

		$transitions = KTWorkflowUtil::getTransitionsForDocumentUser($this->document, $user);
		if (is_null($transitions) || PEAR::isError($transitions))
		{
			return new KTAPI_Error(KTAPI_ERROR_WORKFLOW_INVALID, $transitions);
		}
		foreach($transitions as $transition)
		{
			$result[] = $transition->getName();
		}

		return $result;
	}

	/**
	 * This returns the current workflow state
	 *
	 * @return string
	 */
	public function get_workflow_state()
	{
		$user = $this->can_user_access_object_requiring_permission(  $this->document, KTAPI_PERMISSION_WORKFLOW);

		if (PEAR::isError($user))
		{
			return $user;
		}

		$workflowid=$this->document->getWorkflowId();
		if (empty($workflowid))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_NOT_IN_PROGRESS);
		}

		$result = array();

		$state = KTWorkflowUtil::getWorkflowStateForDocument($this->document);
		if (is_null($state) || PEAR::isError($state))
		{
			return new PEAR_Error(KTAPI_ERROR_WORKFLOW_INVALID);
		}

		$statename = $state->getName();

		return $statename;

	}

	/**
	 * This returns detailed information on the document.
	 *
	 * @return array
	 */
	public function get_detail()
	{
		// make sure we ge tthe latest
		$this->clearCache();

		$detail = array();
		$document = $this->document;

		$detail['title'] = $document->getName();

		$documenttypeid=$document->getDocumentTypeID();
		if (is_numeric($documenttypeid))
		{
			$documenttype = DocumentType::get($documenttypeid);

			$documenttype=$documenttype->getName();
		}
		else
		{
			$documenttype = '* unknown *';
		}
		$detail['document_type'] = $documenttype;

		$detail['version'] = $document->getVersion();
		$detail['filename'] = $document->getFilename();

		$detail['created_date'] = $document->getCreatedDateTime();

		$userid = $document->getCreatorID();
		if (is_numeric($userid))
		{
			$user = User::get($userid);
			$username=(is_null($user) || PEAR::isError($user))?'* unknown *':$user->getName();
		}
		else
		{
			$username='n/a';
		}
		$detail['created_by'] = $username;
		$detail['updated_date'] = $document->getLastModifiedDate();
		$detail['modified_date'] = $document->getLastModifiedDate();

		$userid = $document->getModifiedUserId();
		if (is_numeric($userid))
		{
			$user = User::get($userid);
			$username=(is_null($user) || PEAR::isError($user))?'* unknown *':$user->getName();
		}
		else
		{
			$username='n/a';
		}
		$detail['modified_by'] = $username;
		$detail['updated_by'] = $username;
		$detail['document_id'] = (int) $document->getId();
		$detail['folder_id'] = (int) $document->getFolderID();

		$workflowid = $document->getWorkflowId();
		if (is_numeric($workflowid))
		{
			$workflow = KTWorkflow::get($workflowid);
			$workflowname=(is_null($workflow) || PEAR::isError($workflow))?'* unknown *':$workflow->getName();
		}
		else
		{
			$workflowname='n/a';
		}
		$detail['workflow'] = $workflowname;

		$stateid = $document->getWorkflowStateId();
		if (is_numeric($stateid))
		{
			$state = KTWorkflowState::get($stateid);
			$workflowstate=(is_null($state) || PEAR::isError($state))?'* unknown *':$state->getName();
		}
		else
		{
			$workflowstate = 'n/a';
		}
		$detail['workflow_state']=$workflowstate;

		$userid = $document->getOwnerID();

		if (is_numeric($userid))
		{
			$user = User::get($userid);
			$username=(is_null($user) || PEAR::isError($user))?'* unknown *':$user->getName();
		}
		else
		{
			$username = 'n/a';
		}
		$detail['owner'] = $username;

		$detail['is_immutable'] = (bool) $document->getImmutable();


		$userid = $document->getCheckedOutUserID();

		if (is_numeric($userid))
		{
			$user = User::get($userid);
			$username=(is_null($user) || PEAR::isError($user))?'* unknown *':$user->getName();
		}
		else
		{
			$username = 'n/a';
		}
		$detail['checked_out_by'] = $username;

		$detail['checked_out_date'] = $document->getCheckedOutDate();
		if (is_null($detail['checked_out_date'])) $detail['checked_out_date'] = 'n/a';

		$detail['full_path'] = $this->ktapi_folder->get_full_path() . '/' . $this->get_title();

		return $detail;
	}

	public function get_title()
	{
		return $this->document->getDescription();
	}

	/**
	 * This does a download of a version of the document.
	 *
	 * @param string $version
	 */
	public function download($version=null)
	{
		$storage =& KTStorageManagerUtil::getSingleton();
        $options = array();


        $oDocumentTransaction = & new DocumentTransaction($this->document, 'Document downloaded', 'ktcore.transactions.download', $aOptions);
        $oDocumentTransaction->create();
	}

	/**
	 * This returns the transaction history for the document.
	 *
	 * @return array
	 */
	public function get_transaction_history()
	{
        $sQuery = 'SELECT DTT.name AS transaction_name, U.name AS username, DT.version AS version, DT.comment AS comment, DT.datetime AS datetime ' .
            'FROM ' . KTUtil::getTableName('document_transactions') . ' AS DT INNER JOIN ' . KTUtil::getTableName('users') . ' AS U ON DT.user_id = U.id ' .
            'INNER JOIN ' . KTUtil::getTableName('transaction_types') . ' AS DTT ON DTT.namespace = DT.transaction_namespace ' .
            'WHERE DT.document_id = ? ORDER BY DT.datetime DESC';
        $aParams = array($this->documentid);

        $transactions = DBUtil::getResultArray(array($sQuery, $aParams));
        if (is_null($transactions) || PEAR::isError($transactions))
        {
        	return new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $transactions  );
        }

        return $transactions;
	}

	/**
	 * This returns the version history on the document.
	 *
	 * @return array
	 */
	public function get_version_history()
	{
		$metadata_versions = KTDocumentMetadataVersion::getByDocument($this->document);

        $versions = array();
        foreach ($metadata_versions as $version)
        {
        	$document = &Document::get($this->documentid, $version->getId());

        	$version = array();

        	$userid = $document->getModifiedUserId();
			$user = User::get($userid);
			if (PEAR::isError($user))
			{
				$username = $user->getName();
			}
			else
			{
				$username = 'Unknown';
			}

        	$version['user'] = $username;
        	$version['metadata_version'] = $document->getMetadataVersion();
        	$version['content_version'] = $document->getVersion();

            $versions[] = $version;
        }
        return $versions;
	}

	/**
	 * This expunges a document from the system.
	 *
	 * @access public
	 */
	public function expunge()
	{
		if ($this->document->getStatusID() != 3)
		{
			return new PEAR_Error('You should not purge this');
		}
		DBUtil::startTransaction();

		$transaction = & new DocumentTransaction($this->document, "Document expunged", 'ktcore.transactions.expunge');

        $transaction->create();

        $this->document->delete();

        $this->document->cleanupDocumentData($this->documentid);

		$storage =& KTStorageManagerUtil::getSingleton();

		$result= $storage->expunge($this->document);

		DBUtil::commit();
	}

	/**
	 * This expunges a document from the system.
	 *
	 * @access public
	 */
	public function restore()
	{
		DBUtil::startTransaction();

		$storage =& KTStorageManagerUtil::getSingleton();

		$folder = Folder::get($this->document->getRestoreFolderId());
		if (PEAR::isError($folder))
		{
			$this->document->setFolderId(1);
			$folder = Folder::get(1);
		}
		else
		{
			$this->document->setFolderId($this->document->getRestoreFolderId());
		}

		$storage->restore($this->document);

		$this->document->setStatusId(LIVE);
		$this->document->setPermissionObjectId($folder->getPermissionObjectId());
		$res = $this->document->update();

		$res = KTPermissionUtil::updatePermissionLookup($this->document);

		$user = $this->ktapi->get_user();

		$oTransaction = new DocumentTransaction($this->document, 'Restored from deleted state by ' . $user->getName(), 'ktcore.transactions.update');
		$oTransaction->create();

		DBUtil::commit();
	}
}

?>