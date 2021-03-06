<?php

class BlogModel extends Model {
	public static $Schema = array(
		'id'                         => array(
			'type' => Model::ATT_TYPE_UUID,
		),
		'site'                       => array(
			'type'     => Model::ATT_TYPE_SITE,
			'formtype' => 'system',
		),
		'type' => array(
			'type' => Model::ATT_TYPE_ENUM,
			'options' => ['local', 'remote'],
			'default' => 'local',
			'form' => array(
				'title' => 'Type of Blog',
				'description' => 'If this is a remote feed, change to remote here, otherwise local is sufficient.',
				'group' => 'Basic',
			)
		),
		'manage_articles_permission' => array(
			'type'    => Model::ATT_TYPE_STRING,
			'default' => '!*',
			'form'    => array(
				'type' => 'access',
				'title' => 'Article Management Permission',
				'description' => 'Which groups can add, edit, and remove blog articles in this blog.',
				'group' => 'Access & Advanced',
			),
		),
		'remote_url' => array(
			'type' => Model::ATT_TYPE_STRING,
			'form' => array(
				'title' => 'Remote URL',
				'description' => 'For remote feeds, this must be the URL of the remote RSS or Atom feed.',
				'group' => 'Basic',
			)
		)
	);

	public static $Indexes = array(
		'primary' => array('id'),
	);

	public function __construct($key = null) {
		$this->_linked = array(
			'Page'        => array(
				'link' => Model::LINK_HASONE,
				'on'   => 'baseurl',
			),
			'BlogArticle' => array(
				'link' => Model::LINK_HASMANY,
				'on'   => array('blogid' => 'id'),
			),
		);

		parent::__construct($key);
	}

	public function get($k) {
		$k = strtolower($k);
		switch ($k) {
			case 'baseurl':
				return '/blog/view/' . $this->_data['id'];
			case 'access':
			case 'created':
			case 'title':
			case 'rewriteurl':
			case 'updated':
				return $this->getLink('Page')->get($k);
			default:
				return parent::get($k);
		}
	}

	/**
	 * Helper utility to import a given remote blog.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function importFeed(){
		$blogid  = $this->get('id');
		if (!$this->exists()) {
			throw new Exception('Unable to import a blog that does not exist!');
		}

		// Make sure this is a remote blog.
		if($this->get('type') != 'remote'){
			throw new Exception('Cannot import a blog that is not remote!');
		}

		$file = \Core\Filestore\Factory::File($this->get('remote_url'));
		if(!$file->exists()){
			throw new Exception($this->get('remote_url') . ' does not appear to exist');
		}

		$changes = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];
		$changelog = '';

		// I need a list of current articles in this feed.  This is because remote deletions won't be coming in on the feed.
		$map = array();
		$articles = BlogArticleModel::FindRaw(['blogid = ' . $blogid]);
		foreach($articles as $a){
			$map[ $a['guid'] ] = $a['id'];
		}

		// I can't trust that remote files list what they actually are because many frameworks,
		// (WP in specific), do not correctly use content-types :/
		$contents = $file->getContents();

		// WTF Apple, WHAT.... THE.... FUCK?....
		// 00004000\n\n<?xml version="1.0" encoding="utf-8"
		// REALLY?????
		// When loading the feed "http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/ws/RSS/topsongs/limit=25/xml",
		// I got that as the body!
		if($contents{0} != '<'){
			$x=0;
			do{
				$x++;
			} while ($x < 1000 && $contents{$x} != '<');
			$contents = substr($contents, $x);
		}


		// Which feed type is this?
		$header = substr($contents, 0, 400);

		// All the standardized records
		$records = array();

		if(strpos($header, '<rss ') !== false){
			$xml = new XMLLoader();
			$xml->setRootName('rss');
			$xml->loadFromString($contents);
			foreach($xml->getElements('channel/item') as $item){
				$dat = [
					'guid' => '',
					'link' => '',
					'thumbnail' => '',
					'published' => '',
					'updated' => '',
					'description' => '',
				];
				foreach($item->childNodes as $child){
					if($child->nodeName == '#text') continue;

					switch($child->nodeName){
						case 'media:thumbnail':
							$dat['thumbnail'] = $child->getAttribute('url');
							break;
						case 'pubDate':
							$dat['published'] = $child->nodeValue;
							break;
						default:
							$dat[$child->nodeName] = $child->nodeValue;
					}
				}

				$records[] = $dat;
			}
		}
		elseif(strpos($header, 'http://www.w3.org/2005/Atom') !== false){
			$xml = new XMLLoader();
			$xml->setRootName('feed');
			$xml->loadFromString($contents);
			foreach($xml->getRootDOM()->childNodes as $item){
				if($item->nodeName != 'entry') continue;

				$dat = [
					'guid' => '',
					'link' => '',
					'thumbnail' => '',
					'published' => '',
					'updated' => '',
					'description' => '',
				];
				$imgheight = 0;
				foreach($item->childNodes as $child){
					if($child->nodeName == '#text') continue;

					switch($child->nodeName){
						case 'id':
							$dat['guid'] = $child->nodeValue;
							break;
						case 'link':
							if($child->getAttribute('rel') == 'alternate' && $child->getAttribute('type') == 'text/html'){
								if($child->nodeValue) $dat['link'] = $child->nodeValue;
								else $dat['link'] = $child->getAttribute('href');
							}
							break;
						case 'im:image':
							if($child->getAttribute('height') > $imgheight){
								$dat['thumbnail'] = $child->nodeValue;
								$imgheight = $child->getAttribute('height');
							}
							break;
						case 'updated':
							$dat['updated'] = strtotime($child->nodeValue);
							break;
						case 'summary':
							if($dat['description'] != '') $dat['description'] = $child->nodeValue;
							break;
						case 'content':
							$dat['description'] = $child->nodeValue;
							break;
						default:
							$dat[$child->nodeName] = $child->nodeValue;
					}
				}

				if(!$dat['published'] && $dat['updated']){
					// make sure that there's a published date.
					$dat['published'] = $dat['updated'];
				}

				$records[] = $dat;
			}
		}
		else{
			throw new Exception('Invalid remote file found, please ensure it is either an RSS or Atom feed!');
		}


		// Now that they're standardized...
		foreach($records as $dat){
			$article = BlogArticleModel::Find(['blogid = ' . $blogid, 'guid = ' . $dat['guid']], 1);
			if($article){
				$thischange = 'updated';
			}
			else{
				$article = new BlogArticleModel();
				$article->setFromArray([
					'blogid' => $blogid,
					'guid' => $dat['guid'],
					'status' => 'published',
					'published' => $dat['published']
				]);
				$thischange = 'added';
			}

			$article->setFromArray([
				'title' => $dat['title'],
				'link' => $dat['link'],
				'body' => $dat['description'],
			]);

			if($dat['thumbnail']){
				$remote = \Core\Filestore\Factory::File($dat['thumbnail']);
				$new = $remote->copyTo('public/blog/');
				$article->set('image', $new->getFilename(false));
			}

			if($dat['updated']){
				$article->set('updated', $dat['updated']);
			}

			if($article->save()){
				$changes[$thischange]++;
				$changelog .= $thischange . ' ' . $dat['title'] . "<br/>\n";
			}
			else{
				$changes['skipped']++;
			}

			// Uncheck this GUID from the map so I know that it's still a valid one.
			if(isset($map[ $dat['guid'] ])) unset($map[ $dat['guid'] ]);
		}


		foreach($map as $k => $v){
			// Delete these now.
			$article = BlogArticleModel::Construct($v);
			$article->set('status', 'draft');
			$article->save();
			$changes['deleted']++;
			$changelog .= 'deleted ' . $article->get('title') . "<br/>\n";
		}

		return [
			'status'    => 1,
			'message'   => 'Import feed successfully!',
			'added'     => $changes['added'],
			'updated'   => $changes['updated'],
			'deleted'   => $changes['deleted'],
			'skipped'   => $changes['skipped'],
			'changelog' => $changelog,
		];
	}
}