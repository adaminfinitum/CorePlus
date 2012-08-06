<?php
/**
 * Created by JetBrains PhpStorm.
 * User: powellc
 * Date: 7/29/12
 * Time: 9:52 PM
 * To change this template use File | Settings | File Templates.
 */
class BlogController extends Controller_2_1 {

	/**
	 * Display all blogs in an administrative interface.
	 *
	 * Requires the p:blog_manage permission.
	 */
	public function admin() {
		if (!$this->setAccess('p:blog_manage')) {
			return View::ERROR_ACCESSDENIED;
		}

		$view  = $this->getView();
		$blogs = BlogModel::Find(null, null, null);

		$view->title = 'Blog Administration';
		$view->assignVariable('blogs', $blogs);
		$view->addControl('Add Blog', '/blog/create', 'add');
	}

	/**
	 * This is the main function responsible for displaying nearly all public content.
	 *
	 * This is because the entries will be sub URLs of this one, thus preserving URL structures.
	 */
	public function view() {
		$request = $this->getPageRequest();

		$blog = new BlogModel($request->getParameter(0));
		if (!$blog->exists()) {
			return View::ERROR_NOTFOUND;
		}
		$manager = \Core\user()->checkAccess('p:blog_manage');
		$editor  = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;
		$viewer  = \Core\user()->checkAccess($blog->get('access')) || $editor;

		if (!$viewer) {
			return View::ERROR_ACCESSDENIED;
		}

		// Only 1 parameter; the blog page itself was requested.
		if ($request->getParameter(1) === null) {
			return $this->_viewBlog($blog);
		} // Or the user requested an article!
		else {
			$articleid = $request->getParameter(1);
			// Trim everything after the first dash.
			if (strpos($articleid, '-') !== false) $articleid = substr($articleid, 0, strpos($articleid, '-'));
			$article = new BlogArticleModel($articleid);
			if ($article->get('blogid') != $blog->get('id')) {
				return View::ERROR_NOTFOUND;
			}
			// If the article is still in the draft stage and the user does not have view permissions, (public),
			// then it's the same as a 404.
			if ($article->get('status') != 'published' && !$viewer) {
				return View::ERROR_NOTFOUND;
			}

			return $this->_viewBlogEntry($blog, $article);
		}
	}

	/**
	 * Create a new blog page
	 */
	public function create() {
		if (!$this->setAccess('p:blog_manage')) {
			return View::ERROR_ACCESSDENIED;
		}

		$view = $this->getView();
		$blog = new BlogModel();
		$form = Form::BuildFromModel($blog);
		$form->set('callsmethod', 'BlogHelper::BlogFormHandler');
		// Merge in the page attributes
		foreach (Form::BuildFromModel($blog->getLink('Page'))->getElements() as $el) {
			$el->set('name', str_replace('model[', 'page[', $el->get('name')));
			$form->addElement($el);
		}
		$form->addElement('submit', array('value' => 'Create'));

		$view->title = 'Create Blog';
		$view->assignVariable('form', $form);
	}

	/**
	 * Update an existing blog page
	 */
	public function update() {
		if (!$this->setAccess('p:blog_manage')) {
			return View::ERROR_ACCESSDENIED;
		}

		$view    = $this->getView();
		$request = $this->getPageRequest();
		$blog    = new BlogModel($request->getParameter(0));
		if (!$blog->exists()) {
			return View::ERROR_NOTFOUND;
		}
		$form = Form::BuildFromModel($blog);
		$form->set('callsmethod', 'BlogHelper::BlogFormHandler');
		// Merge in the page attributes
		foreach (Form::BuildFromModel($blog->getLink('Page'))->getElements() as $el) {
			$el->set('name', str_replace('model[', 'page[', $el->get('name')));
			$form->addElement($el);
		}
		$form->addElement('submit', array('value' => 'Update'));

		$view->addBreadcrumb($blog->get('title'), $blog->get('rewriteurl'));
		$view->title = 'Update Blog';
		$view->assignVariable('form', $form);
	}

	/**
	 * Delete a blog
	 */
	public function delete() {
		$view    = $this->getView();
		$request = $this->getPageRequest();

		$blog = new BlogModel($request->getParameter(0));
		if (!$blog->exists()) {
			return View::ERROR_NOTFOUND;
		}
		$manager = \Core\user()->checkAccess('p:blog_manage');
		$editor  = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;

		if (!$manager) {
			return View::ERROR_ACCESSDENIED;
		}

		if (!$request->isPost()) {
			return View::ERROR_BADREQUEST;
		}

		$blog->delete();
		Core::Redirect('/blog/admin');
	}

	/**
	 * Create a new blog article
	 */
	public function article_create() {
		$view    = $this->getView();
		$request = $this->getPageRequest();

		$blog = new BlogModel($request->getParameter(0));
		if (!$blog->exists()) {
			return View::ERROR_NOTFOUND;
		}
		$manager = \Core\user()->checkAccess('p:blog_manage');
		$editor  = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;

		if (!$editor) {
			return View::ERROR_ACCESSDENIED;
		}

		$article = new BlogArticleModel();
		$article->set('blogid', $blog->get('id'));
		$form = Form::BuildFromModel($article);
		$form->set('callsmethod', 'BlogHelper::BlogArticleFormHandler');
		$form->addElement('submit', array('value' => 'Create Article'));

		$view->addBreadcrumb($blog->get('title'), $blog->get('rewriteurl'));
		$view->title = 'Create Blog Article';
		$view->assignVariable('form', $form);
	}

	/**
	 * Update an existing blog article
	 */
	public function article_update() {
		$view    = $this->getView();
		$request = $this->getPageRequest();

		$blog = new BlogModel($request->getParameter(0));
		if (!$blog->exists()) {
			return View::ERROR_NOTFOUND;
		}
		$manager = \Core\user()->checkAccess('p:blog_manage');
		$editor  = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;

		if (!$editor) {
			return View::ERROR_ACCESSDENIED;
		}

		$article = new BlogArticleModel($request->getParameter(1));
		if (!$article->exists()) {
			return View::ERROR_NOTFOUND;
		}
		if ($article->get('blogid') != $blog->get('id')) {
			return View::ERROR_NOTFOUND;
		}

		$form = Form::BuildFromModel($article);
		$form->set('callsmethod', 'BlogHelper::BlogArticleFormHandler');
		$form->addElement('submit', array('value' => 'Update Article'));

		$view->addBreadcrumb($blog->get('title'), $blog->get('rewriteurl'));
		$view->addBreadcrumb($article->get('title'), $article->get('rewriteurl'));
		$view->title = 'Update Blog Article';
		$view->assignVariable('form', $form);
	}

	/**
	 * Delete a blog article
	 */
	public function article_delete() {
		$view    = $this->getView();
		$request = $this->getPageRequest();

		$blog = new BlogModel($request->getParameter(0));
		if (!$blog->exists()) {
			return View::ERROR_NOTFOUND;
		}
		$manager = \Core\user()->checkAccess('p:blog_manage');
		$editor  = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;

		if (!$editor) {
			return View::ERROR_ACCESSDENIED;
		}

		$article = new BlogArticleModel($request->getParameter(1));
		if (!$article->exists()) {
			return View::ERROR_NOTFOUND;
		}
		if ($article->get('blogid') != $blog->get('id')) {
			return View::ERROR_NOTFOUND;
		}

		if (!$request->isPost()) {
			return View::ERROR_BADREQUEST;
		}

		$article->delete();
		Core::Redirect($blog->get('rewriteurl'));
	}

	private function _viewBlog(BlogModel $blog) {
		$view     = $this->getView();
		$page     = $blog->getLink('Page');
		$articles = $blog->getLink('BlogArticle');
		$manager  = \Core\user()->checkAccess('p:blog_manage');
		$editor   = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;

		$view->templatename = '/pages/blog/view-blog.tpl';
		$view->assign('articles', $articles);
		if ($editor) {
			$view->addControl('Add Blog Article', '/blog/article/create/' . $blog->get('id'), 'add');
		}
		if ($manager) {
			$view->addControl('Edit Blog', '/blog/update/' . $blog->get('id'), 'edit');
		}
	}

	private function _viewBlogEntry(BlogModel $blog, BlogArticleModel $article) {
		$view = $this->getView();
		$page = $blog->getLink('Page');
		//$articles = $blog->getLink('BlogArticle');
		$manager = \Core\user()->checkAccess('p:blog_manage');
		$editor  = \Core\user()->checkAccess($blog->get('manage_articles_permission ')) || $manager;

		$view->templatename = '/pages/blog/view-blog-article.tpl';
		//$view->addBreadcrumb($blog->get('title'), $blog->get('rewriteurl'));
		$view->title        = $article->get('title');
		$view->updated      = $article->get('updated');
		$view->canonicalurl = Core::ResolveLink($article->get('rewriteurl'));
		if ($article->get('image')) {
			$image                  = Core::File('public/blog/' . $article->get('image'));
			$view->meta['og:image'] = $image->getPreviewURL('200x200');
		}
		$view->assign('article', $article);
		if ($editor) {
			$view->addControl('Edit Blog Article', '/blog/article/update/' . $blog->get('id') . '/' . $article->get('id'), 'edit');
			$view->addControl(
				array(
					'title'   => 'Delete Blog Article',
					'link'    => '/blog/article/delete/' . $blog->get('id') . '/' . $article->get('id'),
					'icon'    => 'remove',
					'confirm' => 'Remove blog article?'
				)
			);
		}
	}
}