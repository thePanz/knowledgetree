<?php
/**
* BL information for viewing a Discussion	
*
* @author Omar Rahbeeni
* @date 8 May 2003
* @package presentation.lookAndFeel.knowledgeTree.
*
*/

	require_once("../../../../config/dmsDefaults.php");

if (checkSession()) {    
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCreate.inc");    
    require_once("viewDiscussionUI.inc");     
	require_once("$default->fileSystemRoot/presentation/lookAndFeel/knowledgeTree/foldermanagement/folderUI.inc");
    require_once("$default->fileSystemRoot/lib/documentmanagement/Document.inc");
    require_once("$default->fileSystemRoot/lib/foldermanagement/Folder.inc");
    require_once("$default->fileSystemRoot/lib/users/User.inc");    
    require_once("$default->fileSystemRoot/lib/security/permission.inc");
    require_once("$default->fileSystemRoot/presentation/webpageTemplate.inc");
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCustom.inc");    
    require_once("$default->fileSystemRoot/lib/discussions/DiscussionThread.inc");  
    require_once("$default->fileSystemRoot/lib/discussions/DiscussionComment.inc");   
    require_once("$default->fileSystemRoot/presentation/Html.inc");

    $oPatternCustom = & new PatternCustom();

if(checksession) {	
		if (isset($fForDiscussion)) {		
			if ($fDocumentID > 0) { 	
				$iThreadID = DiscussionThread::getThreadIDforDoc($fDocumentID);						
				if ($iThreadID) {// if thread ID does exist
					$oThread = DiscussionThread::get($iThreadID);					
					if($oThread) { //  if thread object exists
						// Iterate through the number of comments
						$sAllCommentID = $oThread->getAllCommentID();
						$arrAllCommentID = explode(",", $sAllCommentID);										
						$iNumMax = $oThread->getNumberOfReplies();						
						for ($i = 0; $i < $iNumMax; $i++) {
							$iCommentID = $arrAllCommentID[$i];
							$oComment = DiscussionComment::get($iCommentID);
							$oUser =  User::get($oComment->getUserID());										
							$oPatternCustom->addHtml(getViewComment($i+1,$oThread,$oComment,$oUser));							
						}					
						// On opening, increment the number of views of current thread & update database
						if($_SESSION['Discussion'][0]->bViews !=true ){
							$oThread->setNumberOfViews();					
							if($oThread->Update() == false) $oPatternCustom->addHtml("Failed to update. Please Contact Database Administrator in this regard") ;
							$_SESSION['Discussion'][0]->bViews = true;
						} 																
					} else { $oPatternCustom->setHtml(getViewFailPage("")) ;}						
				} else { // No current thread, option to create one		
					
					$oPatternCustom->addHtml(getNewThreadOption($fDocumentID)); 
					
					
					}	
			} else { }
		} else if (isset($fViewComment)){		
			if (isset($iCommentID)) {
				$oComment = DiscussionComment::get($iCommentID);
			  	$oUser = User::get($oComment->getUserID());	  	
		
			  	$main->setFormAction("/presentation/lookAndFeel/knowledgeTree/discussions/addCommentBL.php?fDocumentID=$iDocumentID&fCommentID=$iCommentID&fReplyComment=1");		
			  	$oPatternCustom->setHtml(getCommentBody($oComment,$iDocumentID,$oUser)) ;	  	
			}
		} else if (isset($fNewThread)){
			
			
						
		} else { // If no discussion exists 
		}	

	} // end of if checksession
	
	$main->setCentralPayload($oPatternCustom);
    $main->render();    

}
?>
