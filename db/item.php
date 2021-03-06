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

namespace OCA\News\Db;

/**
 * @method integer getId()
 * @method void setId(integer $value)
 * @method string getGuidHash()
 * @method void setGuidHash(string $value)
 * @method string getGuid()
 * @method void setGuid(string $value)
 * @method string getUrl()
 * @method void setUrl(string $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getAuthor()
 * @method void setAuthor(string $value)
 * @method integer getPubDate()
 * @method void setPubDate(integer $value)
 * @method string getBody()
 * @method void setBody(string $value)
 * @method string getEnclosureMime()
 * @method void setEnclosureMime(string $value)
 * @method string getEnclosureLink()
 * @method void setEnclosureLink(string $value)
 * @method integer getFeedId()
 * @method void setFeedId(integer $value)
 * @method integer getStatus()
 * @method void setStatus(integer $value)
 * @method integer getLastModified()
 * @method void setLastModified(integer $value)
 */
class Item extends Entity implements IAPI {

	public $guidHash;
	public $guid;
	public $url;
	public $title;
	public $author;
	public $pubDate;
	public $body;
	public $enclosureMime;
	public $enclosureLink;
	public $feedId;
	public $status = 0;
	public $lastModified;


	public function __construct(){
		$this->addType('pubDate', 'integer');
		$this->addType('feedId', 'integer');
		$this->addType('status', 'integer');
		$this->addType('lastModified', 'integer');
	}


	public function setRead() {
		$this->markFieldUpdated('status');
		$this->status &= ~StatusFlag::UNREAD;
	}

	public function isRead() {
		return !(($this->status & StatusFlag::UNREAD) === StatusFlag::UNREAD);
	}

	public function setUnread() {
		$this->markFieldUpdated('status');
		$this->status |= StatusFlag::UNREAD;
	}

	public function isUnread() {
		return !$this->isRead();
	}

	public function setStarred() {
		$this->markFieldUpdated('status');
		$this->status |= StatusFlag::STARRED;
	}

	public function isStarred() {
		return ($this->status & StatusFlag::STARRED) === StatusFlag::STARRED;
	}

	public function setUnstarred() {
		$this->markFieldUpdated('status');
		$this->status &= ~StatusFlag::STARRED;
	}

	public function isUnstarred() {
		return !$this->isStarred();
	}


	public function toAPI() {
		return array(
			'id' => $this->getId(),
			'guid' => $this->getGuid(),
			'guidHash' => $this->getGuidHash(),
			'url' => $this->getUrl(),
			'title' => $this->getTitle(),
			'author' => $this->getAuthor(),
			'pubDate' => $this->getPubDate(),
			'body' => $this->getBody(),
			'enclosureMime' => $this->getEnclosureMime(),
			'enclosureLink' => $this->getEnclosureLink(),
			'feedId' => $this->getFeedId(),
			'unread' => $this->isUnread(),
			'starred' => $this->isStarred(),
			'lastModified' => $this->getLastModified()
		);
	}


	public function toExport($feeds) {
		return array(
			'guid' => $this->getGuid(),
			'url' => $this->getUrl(),
			'title' => $this->getTitle(),
			'author' => $this->getAuthor(),
			'pubDate' => $this->getPubDate(),
			'body' => $this->getBody(),
			'enclosureMime' => $this->getEnclosureMime(),
			'enclosureLink' => $this->getEnclosureLink(),
			'unread' => $this->isUnread(),
			'starred' => $this->isStarred(),
			'feedLink' => $feeds['feed'. $this->getFeedId()]->getLink()
		);
	}


	public static function fromImport($import) {
		$item = new static();
		$item->setGuid($import['guid']);
		$item->setUrl($import['url']);
		$item->setTitle($import['title']);
		$item->setAuthor($import['author']);
		$item->setPubDate($import['pubDate']);
		$item->setBody($import['body']);
		$item->setEnclosureMime($import['enclosureMime']);
		$item->setEnclosureLink($import['enclosureLink']);
		if($import['unread']) {
			$item->setUnread();
		} else {
			$item->setRead();
		}
		if($import['starred']) {
			$item->setStarred();
		} else {
			$item->setUnstarred();
		}
		
		$item->setFeedId(null);
		return $item;
	}


	public function setAuthor($name) {
		parent::setAuthor(strip_tags($name));
	}


	public function setTitle($title) {
		parent::setTitle(strip_tags($title));
	}


	public function setUrl($url) {
		$url = trim($url);
		if(strpos($url, 'http') === 0 || strpos($url, 'magnet') === 0) {
			parent::setUrl($url);
		}
	}


	public function setGuid($guid) {
		parent::setGuid($guid);
		$this->setGuidHash(md5($guid));
	}


	public function setBody($body) {
		// FIXME: this should not happen if the target="_blank" is already on the link
		parent::setBody(str_replace('<a', '<a target="_blank"',	$body));
	}

}

