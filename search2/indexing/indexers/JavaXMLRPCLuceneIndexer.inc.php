<?php

/**
 * $Id:$
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

require_once('indexing/lib/XmlRpcLucene.inc.php');

class JavaXMLRPCLuceneIndexer extends Indexer
{
	/**
	 * @var XmlRpcLucene
	 */
	private $lucene;

	/**
	 * The constructor for PHP Lucene
	 *
	 * @param boolean $create Optional. If true, the lucene index will be recreated.
	 */
	public function __construct()
	{
		parent::__construct();

		$config =& KTConfig::getSingleton();
		$javaServerUrl = $config->get('indexer/javaLuceneURL');
		$this->lucene = new XmlRpcLucene($javaServerUrl);
	}

	/**
	 * Creates an index to be used.
	 *
	 */
	public static function createIndex()
	{
		// do nothing. The java lucene indexer will create the indexes if required
	}

	/**
	 * Indexes a document based on a text file.
	 *
	 * @param int $docid
	 * @param string $textfile
	 * @return boolean
	 */
    protected function indexDocument($docid, $textfile, $title, $version)
    {
    	try
    	{
	    	return $this->lucene->addDocument($docid, $textfile, '', $title, $version);
    	}
    	catch(Exception $e)
    	{
    		return false;
    	}
    }

    /**
     * Indexes the content and discussions on a document.
     *
     * @param int $docid
     * @param string $textfile
     * @return boolean
     */
    protected function indexDocumentAndDiscussion($docid, $textfile, $title, $version)
    {
    	try
    	{
	    	$discussion = Indexer::getDiscussionText($docid);
    		return $this->lucene->addDocument($docid, $textfile, $discussion, $title, $version);
    	}
    	catch(Exception $e)
    	{
    		return false;
    	}
    }

    /**
     * Indexes a discussion on a document..
     *
     * @param int $docid
     * @return boolean
     */
    protected function indexDiscussion($docid)
    {
    	try
    	{
    		$discussion = Indexer::getDiscussionText($docid);
    		return $this->lucene->updateDiscussion($docid, $discussion);
    	}
    	catch(Exception $e)
    	{
    		return false;
    	}

		return true;
    }

    /**
     * Optimise the lucene index.
     * This can be called periodically to optimise performance and size of the lucene index.
     *
     */
    public function optimise()
    {
    	parent::optimise();
    	$this->lucene->optimise();
    }

    /**
     * Removes a document from the index.
     *
     * @param int $docid
     * @return array containing (content, discussion, title)
     */
    public function deleteDocument($docid)
    {
    	return $this->lucene->deleteDocument($docid);
    }

    /**
     * Shut down the java server
     *
     */
    public function shutdown()
    {
    	$this->lucene->shutdown();
    }


    /**
     * Enter description here...
     *
     * @param string $query
     * @return array
     */
    public function query($query)
    {
    	$results = array();
    	$hits = $this->lucene->query($query);
    	if (is_array($hits))
    	{
    		foreach ($hits as $hit)
    		{
    			$document_id 	= $hit->DocumentID;

    			// avoid adding duplicates. If it is in already, it has higher priority.
    			if (!array_key_exists($document_id, $results) || $score > $results[$document_id]->Score)
    			{
    				$item = new QueryResultItem($document_id);
    				$item->Title = $hit->Title;
    				$item->Text = $hit->Content;
    				$item->Rank = $hit->Rank;

    				if ($item->CanBeReadByUser)
    				{
    					$results[$document_id] = $item;
    				}
    			}
    		}
    	}
    	else
    	{
			 $_SESSION['KTErrorMessage'][] = _kt('The XMLRPC Server did not respond correctly. Please notify the system administrator to investigate.');
    	}
        return $results;
    }

    /**
     * Diagnose the indexer. e.g. Check that the indexing server is running.
     *
     */
    public function diagnose()
    {
		$config =& KTConfig::getSingleton();

		$javaLuceneURL = $config->get('indexer/javaLuceneURL');

		list($protocol, $host, $port) = explode(':', $javaLuceneURL);
		if (empty($port)) $port == 8875;
		if (substr($host, 0, 2) == '//') $host = substr($host, 2);

		$connection = @fsockopen($host, $port, $errno, $errstr, 2);
		if (false === $connection)
		{
			$indexer = $this->getDisplayName();
			return sprintf(_kt("Cannot connect to the %s on '%s'.\nPlease consult the Administrator Guide for more information on configuring the %s."), $indexer, $javaLuceneURL, $indexer);
		}
		fclose($connection);

		return null;

    }

    /**
     * Returns the name of the indexer.
     *
     * @return string
     */
	public function getDisplayName()
	{
		return _kt('Document Indexer Service');
	}


    /**
     * Returns the number of non-deleted documents in the index.
     *
     * @return int
     */
    public function getDocumentsInIndex()
    {
    	$stats = $this->lucene->getStatistics();
    	if ($stats === false || !is_object($stats))
    	{
    		return _kt('Not Available');
    	}
    	return $stats->countDocuments;
    }

}
?>