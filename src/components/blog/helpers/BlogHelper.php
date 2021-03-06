<?php
/**
 * Created by JetBrains PhpStorm.
 * User: powellc
 * Date: 7/29/12
 * Time: 10:13 PM
 * To change this template use File | Settings | File Templates.
 */
abstract class BlogHelper {

	/**
	 * Get the form for article creation and updating.
	 *
	 * @param BlogArticleModel $article
	 *
	 * @return Form
	 */
	public static function GetArticleForm(BlogArticleModel $article){
		$page = $article->getLink('Page');
		$blog = $article->getLink('Blog');
		$page->set('parenturl', $blog->get('baseurl'));

		$form = new Form();
		$form->set('callsmethod', 'BlogHelper::BlogArticleFormHandler');

		$form->addModel($page, 'page');
		$form->addModel($article, 'model');


		if(Core::IsComponentAvailable('facebook') && Core::IsLibraryAvailable('jquery')){
			// Is this article already posted?
			if($article->get('fb_post_id')){
				$form->addElement(
					'select',
					[
						'disabled' => true,
						'title' => 'Post to Facebook',
						'options' => ['' => 'Posted!'],
						'group'    => 'Publish Settings',
					]
				);
			}
			else{
				$form->addElement(
					'select',
					[
						'class'    => 'facebook-post-to-select',
						'title'    => 'Post to Facebook',
						'name'     => 'facebook_post',
						//'options'  => ['' => '-- Do not post --', '__self__' => 'My Wall Feed'],
						'options' => ['' => '-- Please enable javascript --'],
						'group'    => 'Publish Settings',
					]
				);
			}
		}


		// Lock in some elements for this blog article page.
		$form->getElement('page[parenturl]')->setFromArray(
			array(
				'value' => $blog->get('baseurl'),
				'readonly' => 'readonly'
			)
		);

		// And remove a few other elements.
		$form->removeElement('model[title]');

		return $form;
	}
	/**
	 * Helper function to save blog pages, both new and existing.
	 *
	 * @static
	 *
	 * @param Form $form
	 * @return string Redirect URL
	 */
	public static function BlogFormHandler(Form $form) {
		$model = $form->getModel();
		$page = $form->getModel('page');
		$page->set('fuzzy', '1'); // Needs to be fuzzy since it supports children

		$model->save();
		$page->save();

		return $model->get('baseurl');
	}

	/**
	 * Helper function to save a blog article, both new and existing.
	 *
	 * @static
	 *
	 * @param Form $form
	 * @return string Redirect URL
	 */
	public static function BlogArticleFormHandler(Form $form) {
		try{
			/** @var $page PageModel */
			$page = $form->getModel('page');
			/** @var $article BlogArticleModel */
			$article = $form->getModel('model');

			// I need to update some of the article information from the page info.
			$article->set('title', $page->get('title'));

			/** @var $pageauthor PageMetaModel|null */
			$pageauthor = $page->getMeta('author');

			// Allow the user to override who is posting this article, if set.
			if($pageauthor && $pageauthor->get('meta_value_title') && $pageauthor->get('meta_value')){
				$article->set('authorid', $pageauthor->get('meta_value'));
			}
			// Otherwise Set the article author to the current user.
			else{
				$article->set('authorid', \Core\user()->get('id'));
				$page->setMeta('author', \Core\user()->getDisplayName());
				$page->setMeta('authorid', \Core\user()->get('id'));
			}

			$isnew = !$article->exists();

			// Blog pages are not selectable.  Otherwise there would just be WAY too many of them!
			// This addresses bug #321
			$page->set('selectable', 0);

			if($article->get('status') == 'published' && !$article->get('published')){
				// If it's new and is published... set the published date to right now!
				$article->set('published', Time::GetCurrentGMT());
			}

			$article->save();

			if($isnew){
				// Set the baseurls too!
				$page->set('baseurl', $article->get('baseurl'));
			}
			$page->save();

			// if it's new, allow the user to post it to facebook.
			if(isset($_POST['facebook_post']) && $_POST['facebook_post']){
				// facebook_post

				$token = substr($_POST['facebook_post'], strpos($_POST['facebook_post'], ':')+1);
				$fbid  = substr($_POST['facebook_post'], 0, strpos($_POST['facebook_post'], ':'));
				$from  = \Core\user()->get('facebook_id');

				// yay....
				$args = array(
					'access_token' => $token,
					'from' => $from,
					'link' => Core::ResolveLink($article->get('rewriteurl')),
					'name' => $article->get('title'),
					'caption' => '',
					'description' => $article->getTeaser(),
					'message' => '',
				);

				// Some optional arguments
				if($article->getImage()){
					$args['picture'] = $article->getImage()->getPreviewURL('300x300');
				}
				$args['ref'] = 'coreplus';

				$facebook = new Facebook(array(
					'appId'  => FACEBOOK_APP_ID,
					'secret' => FACEBOOK_APP_SECRET,
				));
				$publish_result = $facebook->api('/' . $fbid . '/feed', 'POST', $args);
				$article->set('fb_account_id', $fbid);
				$article->set('fb_post_id', $publish_result['id']);
				$article->save();
			}

			Core::SetMessage(($isnew ? 'Created' : 'Updated') . ' blog article successfully!', 'success');
			return Core::GetHistory();
			//return $article->get('baseurl');
		}
		catch(ModelValidationException $e){
			Core::SetMessage($e->getMessage(), 'error');
			return false;
		}
		catch(FacebookApiException $e){
			// Facebook errors are not critical, as the post is still created.
			Core::SetMessage($e->getMessage(), 'error');
			return $article->get('rewriteurl');
		}
		catch(Exception $e){
			error_log($e->getMessage());
			Core::SetMessage($e->getMessage(), 'error');
			return false;
		}
	}

	/**
	 * Save handler for the index edit form.
	 *
	 * This form just manages the page data for the /blog listing.
	 * @param Form $form
	 *
	 * @return bool|mixed|null
	 */
	public static function BlogIndexFormHandler(Form $form){
		try{
			$page = $form->getModel('page');
			$page->save();
			return $page->get('baseurl');
		}
		catch(Exception $e){
			error_log($e->getMessage());
			Core::SetMessage($e->getMessage(), 'error');
			return false;
		}
	}

	/**
	 * Helper method to be called on cron events to pull in the latest feeds for all the remote articles.
	 */
	public static function CronRetrieveRemoteFeeds(){
		$blogs = BlogModel::Find(['type = remote']);
		foreach($blogs as $blog){
			/** @var $blog BlogModel */
			echo 'Retrieving remote feed for blog #' . $blog->get('id') . "...\n";

			try{
				$results = $blog->importFeed();
			}
			catch(Exception $e){
				echo $e->getMessage();
				return false;
			}

			echo 'Added: ' . $results['added'] . "\n" .
				'Updated: ' . $results['updated'] . "\n" .
				'Skipped: ' . $results['skipped'] . "\n" .
				'Deleted: ' . $results['deleted'] . "\n" .
				"\n" .
				$results['changelog'];
		}

		return true;
	}
}
