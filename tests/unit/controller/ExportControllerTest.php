<?php
/**
 * ownCloud - News
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 */

namespace OCA\News\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\Http;

use \OCA\News\Http\TextDownloadResponse;
use \OCA\News\Utility\ControllerTestUtility;
use \OCA\News\Utility\OPMLExporter;
use \OCA\News\Db\Item;
use \OCA\News\Db\Feed;

require_once(__DIR__ . "/../../classloader.php");


class ExportControllerTest extends ControllerTestUtility {

	private $appName;
	private $request;
	private $controller;
	private $user;
	private $feedBusinessLayer;
	private $folderBusinessLayer;
	private $itemBusinessLayer;
	private $opmlExporter;

	/**
	 * Gets run before each test
	 */
	public function setUp(){
		$this->appName = 'news';
		$this->user = 'john';
		$this->itemBusinessLayer = $this->getMockBuilder('\OCA\News\BusinessLayer\ItemBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->feedBusinessLayer = $this->getMockBuilder('\OCA\News\BusinessLayer\FeedBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->folderBusinessLayer = $this->getMockBuilder('\OCA\News\BusinessLayer\FolderBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->request = $this->getRequest();
		$this->opmlExporter = new OPMLExporter();
		$this->controller = new ExportController($this->appName, $this->request,
			$this->feedBusinessLayer, $this->folderBusinessLayer, 
			$this->itemBusinessLayer, $this->opmlExporter, $this->user);
	}


	public function testOpmlAnnotations(){
		$annotations = array('NoAdminRequired', 'NoCSRFRequired');
		$this->assertAnnotations($this->controller, 'opml', $annotations);
	}


	public function testArticlesAnnotations(){
		$annotations = array('NoAdminRequired', 'NoCSRFRequired');
		$this->assertAnnotations($this->controller, 'articles', $annotations);
	}


	public function testOpmlExportNoFeeds(){
		$opml = 
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
		"<opml version=\"2.0\">\n" .
		"  <head>\n" .
		"    <title>Subscriptions</title>\n" .
		"  </head>\n" .
		"  <body/>\n" .
		"</opml>\n";

		$this->feedBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array()));
		$this->folderBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array()));

		$return = $this->controller->opml();
		$this->assertTrue($return instanceof TextDownloadResponse);
		$this->assertEquals($opml, $return->render());
	}


	public function testGetAllArticles(){
		$item1 = new Item();
		$item1->setFeedId(3);
		$item2 = new Item();
		$item2->setFeedId(5);

		$feed1 = new Feed();
		$feed1->setId(3);
		$feed1->setLink('http://goo');
		$feed2 = new Feed();
		$feed2->setId(5);
		$feed2->setLink('http://gee');
		$feeds = array($feed1, $feed2);

		$articles = array(
			$item1, $item2
		);

		$this->feedBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue($feeds));
		$this->itemBusinessLayer->expects($this->once())
			->method('getUnreadOrStarred')
			->with($this->equalTo($this->user))
			->will($this->returnValue($articles));


		$return = $this->controller->articles();
		$headers = $return->getHeaders();
		$this->assertEquals('attachment; filename="articles.json"', $headers ['Content-Disposition']);

		$this->assertEquals('[{"guid":null,"url":null,"title":null,' . 
			'"author":null,"pubDate":null,"body":null,"enclosureMime":null,' . 
			'"enclosureLink":null,"unread":false,"starred":false,' . 
			'"feedLink":"http:\/\/goo"},{"guid":null,"url":null,"title":null,' . 
			'"author":null,"pubDate":null,"body":null,"enclosureMime":null,' . 
			'"enclosureLink":null,"unread":false,"starred":false,' . 
			'"feedLink":"http:\/\/gee"}]', $return->render());
	}

}