<!DOCTYPE html>
<html lang="en" dir="ltr" prefix="og: http://ogp.me/ns#">
	<!-- Open Graph is proprietary...and tacitly supports Facebook -->
	<head>
		<meta charset="utf-8">
		<!-- Force latest IE rendering engine or ChromeFrame if installed, omit if included in .htaccess -->
		<!--[if IE]><meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"><![endif]-->
		<title>{$title}</title>
		<meta name="viewport" content="width=device-width">
		<link href="{asset src='css/styles.css'}" type="text/css" rel="stylesheet"/>
		<!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.6.2/html5shiv.js"></script><![endif]-->
		{script library="fontawesome"}{/script}
		<!-- the script below helps older versions of IE act like modern browsera, however the linked version isn't served with gzip compression, download and serve locally for best performance -->
		<!--[if lt IE 10]><script src="//ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script><![endif]-->
	</head>

	<body class="{$body_classes}">

		{$body}
		<footer>
			<p class="legal-notice">
				This software is distributed under the <a href="http://www.gnu.org/licenses/agpl-3.0.html" target="_BLANK">AGPL v3 License</a>, this means that as a user of this
				service, you are allowed to, (and encouraged to), look at the <a href="http://corepl.us" target="
_BLANK">complete unobfuscated source code of Core Plus</a>.
			</p>
		</footer>
	</body>

</html>
