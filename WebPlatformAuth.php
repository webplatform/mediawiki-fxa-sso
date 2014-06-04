<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 *
 * @ingroup Extensions
 *
 * @version 2.0-dev
 */

if ( !defined( 'MEDIAWIKI' ) ) {
  echo( "Not an entry point." );
  die( -1 );
}

//if ( version_compare( $GLOBALS['wgVersion'], '1.22', '<' ) ) {
//   die( '<b>Error:</b> This extension requires MediaWiki 1.22 or above' );
//}

$dir = dirname(__FILE__) . '/';

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
  $loader = require( __DIR__ . '/vendor/autoload.php' );
  $loader->add( 'Guzzle\\', $dir . '/vendor/guzzlehttp/guzzle/src/Guzzle/' );
} else {
  die('You MUST install Composer dependencies');
}

$wgExtensionCredits['other'][] = array(
  'path'           => __FILE__,
  'version'        => '2.0-dev',
  'name'           => 'MediaWiki SSO using Firefox Accounts',
  'author'         => array('Renoir Boulanger'),
  'url'            => 'http://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension',
  'descriptionmsg' => 'webplatformauth-desc',
);

$wgAutoloadClasses['WebPlatformAuthHooks']       = $dir . 'includes/WebPlatformAuthHooks.php';

$wgAutoloadClasses['AccountsHandlerSpecialPage'] = $dir . 'includes/specials/AccountsHandlerSpecialPage.php';
$wgAutoloadClasses['WebPlatformAuthLogin']       = $dir . 'includes/specials/WebPlatformAuthLogin.php';
$wgAutoloadClasses['WebPlatformAuthLogout']      = $dir . 'includes/specials/WebPlatformAuthLogout.php';
$wgAutoloadClasses['WebPlatformAuthPassword']    = $dir . 'includes/specials/WebPlatformAuthPassword.php';

$wgMessagesDirs['WebPlatformAuth']           = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['WebPlatformAuth'] = $dir . 'WebPlatformAuth.i18n.php';

// Change AccountsHandler for better name later #TODO
$wgSpecialPages['AccountsHandler'] = 'AccountsHandlerSpecialPage';
$wgSpecialPages['Userlogin']       = 'WebPlatformAuthLogin';
$wgSpecialPages['Userlogout']      = 'WebPlatformAuthLogout';
$wgSpecialPages['ChangePassword']  = 'WebPlatformAuthPassword';

$wgHooks['UserLoadFromSession'][]  = 'WebPlatformAuthHooks::onUserLoadFromSession';
$wgHooks['GetPreferences'][]       = 'WebPlatformAuthHooks::hookLimitPreferences';
$wgHooks['SpecialPage_initList'][] = 'WebPlatformAuthHooks::hookInitSpecialPages';