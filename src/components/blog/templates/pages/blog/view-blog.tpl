{css src="assets/css/blog.css"}{/css}

{if count($articles) == 0}
	<p class="message-info">This blog has no articles created yet, please try back later.</p>
{/if}

{$filters->pagination()}
<div itemscope="itemscope" itemtype="http://schema.org/Blog" class="single-blog-listing blog-listing">
	<h1>{$page.title}</h1>

	{if $page.description}
		<div itemprop="description" style="display:none;">{$page.description}</div>
	{/if}

	{foreach $articles as $article}
		<div class="blog-article blog-article-status-{$article.status}" itemscope="itemscope" itemtype="http://schema.org/BlogPosting">
			<link itemprop="url" href="{$article->getResolvedLink()}"/>
			<a class="blog-article-title" href="{$article->getResolvedLink()}" itemprop="name">
				{$article.title}
			</a>

			{if $article->isPublished()}
				<meta itemprop="dateCreated" content="{date format='c' date="`$article.published`"}"/>
				<div class="blog-article-date">Posted {date date="`$article.published`"}</div>
			{else}
				<div class="blog-article-date">Not Published</div>
			{/if}


			{if $article.image}
				<div class="blog-article-image">
					{img placeholder="blog" src="`$article.image`" width='75' height='75' itemprop="thumbnailUrl"}
				</div>
			{/if}


			<p class="blog-article-excerpt" itemprop="articleBody">
				{$article->getTeaser()}
				... <a class="blog-article-read-more" href="{$article->getResolvedLink()}">Read More</a>
			</p>

			<div class="clear"></div>
		</div>
	{/foreach}
</div>

<!-- This is just the waypoint trigger to know when to load the next set of results! -->
<div id="bottomofthelisting"></div>

<a href="http://feedvalidator.org/check.cgi?url={$canonical_url}.atom" target="_blank">{img src="assets/images/valid-atom.png" alt="[Valid Atom 1.0]" title="Validate my Atom 1.0 feed"}</a>

<a href="http://www.rssboard.org/rss-validator/check.cgi?url={$canonical_url}.rss" target="_blank">{img src="assets/images/valid-rss.png" alt="[Valid RSS]" title="Validate my RSS feed"}</a>

{script library="jquery.waypoints"}{/script}
{script location="foot" src="assets/js/blog-waypoint-scroll.js"}{/script}