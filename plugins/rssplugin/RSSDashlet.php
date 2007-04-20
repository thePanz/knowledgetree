<?php

/**
 *
 * Copyright (c) 2007 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * -------------------------------------------------------------------------
 *
 * You can contact the copyright owner regarding licensing via the contact
 * details that can be found on the KnowledgeTree web site:
 *
 *         http://www.knowledgetree.com/
 */


require_once(KT_LIB_DIR . '/browse/browseutil.inc.php');
require_once(KT_DIR. '/plugins/rssplugin/rss2array.inc.php');
require_once(KT_DIR. '/plugins/rssplugin/KTrss.inc.php');

class RSSDashlet extends KTBaseDashlet {
	var $oUser;
	
	function RSSDashlet(){
		$this->sTitle = _kt('RSS Feeds');
	}
	
	function is_active($oUser) {
		$this->oUser = $oUser;
		return true;
	}

	function render() {
		global $main;
        $main->requireJSResource("plugins/rssplugin/js/update.js");
		
		$oTemplating =& KTTemplating::getSingleton();
       	$oTemplate = $oTemplating->loadTemplate('RSSPlugin/dashlet');
       	
       	$iUId = $this->oUser->getId();
       	
       	// Get internal Feed
        $aInternalRSS = KTrss::getInternalFeed($iUId);

        // Get count of all items in feed
        $iCountItems = count($aInternalRSS[items]);
        
        // Get listing of external feeds
        $aExternalFeedsList = KTrss::getExternalFeedsList($iUId);
        
        // Create action for external feed management to be linked to inside of dashlet
        $action = array("name" => _kt("Manage External RSS Feeds"), "url" => $this->oPlugin->getPagePath('managerssfeeds'));
        
        // Prepare template data
		$aTemplateData = array(
			'context' => $this,
			'internalrss' => $aInternalRSS,
			'itemcount' => $iCountItems,
			'feedlist' => $aExternalFeedsList,
			'user' => $iUId,
			'action' => $action,
		);
      
        return $oTemplate->render($aTemplateData);
    }
}
?>