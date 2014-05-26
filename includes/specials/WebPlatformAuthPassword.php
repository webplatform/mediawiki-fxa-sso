<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

/**
 * Extend original MediaWiki Special:ChangePassword
 *
 * Related documentation:
 * * http://www.mediawiki.org/wiki/Manual:Special_pages
 */
class WebPlatformAuthPassword extends SpecialChangePassword
{

  public function execute($subPage)
  {
    // Make Non Hardcoded! #IMPROVEMENT
    $this->getOutput()->redirect( 'https://accounts.webplatform.org/settings' );
  }
}
