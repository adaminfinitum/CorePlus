<!DOCTYPE html>
<html lang="en" dir="ltr" prefix="og: http://ogp.me/ns#">
	<!-- Open Graph is proprietary...and tacitly supports Facebook -->
	<head>
		<meta charset="utf-8">
		<!-- Force latest IE rendering engine or ChromeFrame if installed, omit if included in .htaccess, see h5bp.com -->
		<!--[if IE]><meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"><![endif]-->
		<title>{$title}</title>
		<meta name="viewport" content="width=device-width">
		{css src="css/styles.css"}{/css}
		{css src="css/opt/full-width.css" optional="1" default="0" title="Set the page to be full width"}{/css}
		<!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.6.2/html5shiv.js"></script><![endif]-->
		{script library="fontawesome"}{/script}
		<!-- This will enable the Core Plus context menus new in 2.4.0 -->
		{script library="jquery"}{/script}
		{script src="js/core.context-controls.js"}{/script}
		<!-- the script below helps older versions of IE act like modern browsera, however the linked version isn't served with gzip compression, download and serve locally for best performance -->
		<!--[if lt IE 10]><script src="//ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script><![endif]-->
	</head>

	<body class="{$body_classes}">
		{widget name="AdminMenu"}
		<div id="wrapper" class="column0">
			<header>
				<a href="{$smarty.const.ROOT_URL}" title="Home"><img src="{asset src='images/logo.png'}" alt="Home"/></a>
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
								<h1>{$crumb.title}</h1>
							{/if}

							{if !$smarty.foreach.crumbs.last}
								»
							{/if}
						{/foreach}
					{else}
						<h1>{$title}</h1>
					{/if}

					<menu id="controls" style="float:right; width: 150px;">
						{$controls->fetch()}
					</menu>
				</nav>

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
