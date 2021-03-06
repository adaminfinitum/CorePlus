# Core Plus standard .htaccess template
#
# @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
# @author Charlie Powell <charlie@eval.bz>


# I'm not real sure why this is inside an if block, because the site won't work without it anyway
# But I suppose the installer will warn the user of that.
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /

	RewriteCond %{SCRIPT_FILENAME} -f [OR]
	RewriteCond %{SCRIPT_FILENAME} -d
	RewriteRule ^(.+) - [PT,L]
	RewriteRule ^(.*) index.php%{REQUEST_URI}
</IfModule>

# This will disable directory listings.
# This could be a security risk because anonymous users could browse the entirety of your
# files/public and clone your entire contents with nothing more than a simple "wget -r -e robots=off"
Options -Indexes

# Turn on expiry
<IfModule mod_expires.c>
	ExpiresActive On
	ExpiresDefault "access plus 1 week"
</IfModule>


# Turn on mod_gzip if available
<IfModule mod_gzip.c>
	mod_gzip_on yes
	mod_gzip_dechunk yes
	mod_gzip_keep_workfiles No
	mod_gzip_minimum_file_size 1000
	mod_gzip_maximum_file_size 1000000
	mod_gzip_maximum_inmem_size 1000000
	mod_gzip_item_include mime ^text/.*
	mod_gzip_item_include mime ^application/javascript$
	mod_gzip_item_include mime ^application/x-javascript$
	# Exclude old browsers and images since IE has trouble with this
	mod_gzip_item_exclude reqheader "User-Agent: .*Mozilla/4\..*\["
	mod_gzip_item_exclude mime ^image/.*
</IfModule>


## Apache2 deflate support if available
##
## Important note: mod_headers is required for correct functioning across proxies.
##
<IfModule mod_deflate.c>
	AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/x-javascript
	BrowserMatch ^Mozilla/4 gzip-only-text/html
	BrowserMatch ^Mozilla/4\.[0678] no-gzip
	BrowserMatch \bMSIE !no-gzip

<IfModule mod_headers.c>
	Header append Vary User-Agent env=!dont-vary
</IfModule>

	# The following is to disable compression for actions. The reason being is that these
	# may offer direct downloads which (since the initial request comes in as text/html and headers
	# get changed in the script) get double compressed and become unusable when downloaded by IE.
	SetEnvIfNoCase Request_URI action\/* no-gzip dont-vary
	SetEnvIfNoCase Request_URI actions\/* no-gzip dont-vary

</IfModule>


# Configure ETags
<FilesMatch "\.(jpg|jpeg|gif|png|mp3|flv|mov|avi|3pg|html|htm|swf|js|ico)$">
	FileETag MTime Size
</FilesMatch>

#  Add Proper MIME-Type for Favicon to allow expires to work
AddType image/vnd.microsoft.icon .ico