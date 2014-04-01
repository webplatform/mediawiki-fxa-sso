<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
	echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "$IP/extensions/WebPlatformAuth/WebPlatformAuth.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'WebPlatformAuth',
	'author'         => '[http://www.hallowelt.biz Hallo Welt! Medienwerkstatt GmbH]; Robert Vogel',
	'url'            => 'http://www.hallowelt.biz',
	'descriptionmsg' => 'webplatformauth-desc',
	'version'        => '1.1.0',
);

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['WebPlatformAuthHooks']   = $dir . 'includes/WebPlatformAuthHooks.php';
$wgAutoloadClasses['ApiWebPlatformAuth']     = $dir . 'includes/api/ApiWebPlatformAuth.php';
$wgAutoloadClasses['WPARenewSession']        = $dir . 'includes/specials/WPA_RenewSession.php';
$wgMessagesDirs['WebPlatformAuth'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['WebPlatformAuth'] = $dir . 'WebPlatformAuth.i18n.php';

$wgSpecialPages['RenewSession'] = 'WPARenewSession';
//$wgWhitelistRead[] = 'Special:RenewSession';

$wgAPIModules['webplatformauth'] = 'ApiWebPlatformAuth';

$wgAjaxExportList[] = 'WebPlatformAuthHooks::ajaxGetUserInfoById';
$wgAjaxExportList[] = 'WebPlatformAuthHooks::ajaxGetUserInfoByName';

$wgHooks['UserLogoutComplete'][] = 'WebPlatformAuthHooks::onUserLogoutComplete';
$wgHooks['UserLoginComplete'][]  = 'WebPlatformAuthHooks::onUserLoginComplete';
$wgHooks['UserSetCookies'][]     = 'WebPlatformAuthHooks::onUserSetCookies';
//$wgHooks['UserLoadFromSession'][]          = 'WebPlatformAuthHooks::onUserLoadFromSession';
$wgHooks['UserLoadAfterLoadFromSession'][] = 'WebPlatformAuthHooks::onUserLoadAfterLoadFromSession';
//$wgHooks['UserLoadFromDatabase'][]         = 'WebPlatformAuthHooks::onUserLoadFromDatabase';

/**
 * @deprecated Replaced by $wgWebPlatformAuthSecret, because in WPD setup IP addresses ain't predictable enough
 */
$wgWebPlatformAuthAllowedClients = array(
	'localhost',
	'127.0.0.1'
);

$wgWebPlatformAuthSecret = 'NqzdqcdCRWd1JZ1DXSI2Eq5BbjYra40nEguT8654C7eNrMldXuMDs4laHQIppAoc';

$wgCookieDomain = '.webplatform.org'; // --> LocalSettings.php
