<!DOCTYPE html>
<html prefix="og: http://ogp.me/ns#">
	<head>
		<!-- Force latest IE rendering engine or ChromeFrame if installed -->
		<!--[if IE]><meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"><![endif]-->
		<title>{$seotitle}</title>
		<meta name="viewport" content="width=device-width">
		<link href="{asset src='css/reset.css'}" type="text/css" rel="stylesheet"/>
		<link href="{asset src='css/styles.css'}" type="text/css" rel="stylesheet"/>
		<!--[if lt IE 9]><script type="text/javascript" src="{asset src='js/html5.js'}"></script><![endif]-->
		{script library="fontawesome"}{/script}
		<!-- This will enable the Core Plus context menus new in 2.4.0 -->
		{script library="jquery"}{/script}
		{script src="js/core.context-controls.js"}{/script}
		<!-- Spruce up the messages with a bit of flair. -->
		{script src="js/theme.messages.js" location="foot"}{/script}
		<!-- the script below helps older versions of IE act like modern browsera, however the linked version isn't served with gzip compression, download and serve locally for best performance -->
		<!--[if lt IE 10]><script src="//ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script><![endif]-->
	</head>

	<body>
		{widget name="AdminMenu"}
		<div id="wrapper" class="column1">
			<header>
				<a href="{$smarty.const.ROOT_URL}" title="Home"><img src="{asset src='logo.png'}" alt="Home"/></a>
			</header>
			<nav id="primary-nav">
				{widgetarea name="Primary Navigation"}
			</nav>
			<div style="clear:both;"></div>
			<div id="innerwrapper">
				
				<nav id="breadcrumbs">
					{if isset($breadcrumbs)}
						{foreach from=$breadcrumbs item=crumb name=crumbs}
							{if $crumb.link && !$smarty.foreach.crumbs.last}
								<a href="{$crumb.link}">{$crumb.title}</a>
							{else}
								{$crumb.title}
							{/if}

							{if !$smarty.foreach.crumbs.last}
								&raquo;
							{/if}
						{/foreach}
					{else}
						{$title}
					{/if}

					<menu id="controls" style="float:right; width: 150px;">
						{$controls->fetch()}
					</menu>
				</nav>
				
				<aside id="leftcol" class="pagecolumn">

					{*widget name="/Content/View/5"*}
					{widgetarea name="Left Column"}
				</aside>
				<section class="pagecontent">
					{if !empty($messages)}
						{foreach from=$messages item="m"}
							<p class="message-{$m.mtype} rounded">
								{$m.mtext}
							</p>
						{/foreach}
					{/if}
					
					{widgetarea name="Above Body"}
					
					{$body}
					
					{widgetarea name="After Body"}
				</section>
				<div style="clear:both;"></div>
			</div>
			<footer>
				{widgetarea name="Footer"}
				<p class="legal-notice">
					This software is distributed under the <a href="http://www.gnu.org/licenses/agpl-3.0.html" target="_BLANK">AGPL v3 License</a>, this means that as a user of this
					service, you are allowed to, (and encouraged to), look at the <a href="http://corepl.us" target="
_BLANK">complete unobfuscated source code of Core Plus</a>.
				</p>
			</footer>
		</div>
	</body>

</html>
