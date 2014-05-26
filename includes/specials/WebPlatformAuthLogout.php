<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

/**
 * Extend original MediaWiki Special:UserLogout
 *
 * Expected result redirect to FxA appropriate pages:
 * * logout
 *
 * Related documentation:
 * * http://www.mediawiki.org/wiki/Manual:Special_pages
 */
class WebPlatformAuthLogout extends SpecialUserlogout
{
  public function execute($subPage)
  {
    // Generally the wgArticlePagh ends with .../$1
    $path = str_replace( '$1' , '' , $GLOBALS['wgArticlePath'] );
    $selectedUri = $path . 'Special:AccountsHandler/logout';

    // TODO, delete local session!
    // ... iframe with click event on #signout ?
    // https://github.com/mozilla/fxa-content-server/blob/master/app/scripts/views/settings.js#L34

    $this->getOutput()->redirect( $selectedUri );
  }
}
