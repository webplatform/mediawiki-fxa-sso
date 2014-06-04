<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

require_once( dirname( __FILE__ ) . '/WebPlatformAuthHooks.php' );
require_once( dirname( __FILE__ ) . '/FirefoxAccountsManager.php' );

// Guzzle Exceptions
use Guzzle\Http\Exception\ClientErrorResponseException;

/**
 * Extending MediaWiki Authentication
 *
 * Based on GodAuth MediaWiki Extension
 *
 * @link https://github.com/iamcal/MediaWiki-SSO/blob/master/GodAuth.php
 */
class WebPlatformAuthPlugin extends AuthPlugin
{
    protected $apiHandler;

    protected function _init()
    {
        try {
            $this->apiHandler = new FirefoxAccountsManager( $GLOBALS['wgWebPlatformAuth'] );
        } catch( Exception $e ) {
            error_log('Problem initiating MediaWiki WebPlatformAuth AuthPlugin handler:' . $e->getMessage() );

            throw $e;
        }
    }
}