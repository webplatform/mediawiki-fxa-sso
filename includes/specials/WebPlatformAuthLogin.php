<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

/**
 * Extend original MediaWiki Special:UserLogin
 *
 * Expected result redirect to FxA appropriate pages:
 * * login
 * * create account
 *
 * Related documentation:
 * * http://www.mediawiki.org/wiki/Manual:Special_pages
 */
class WebPlatformAuthLogin extends LoginForm
{

  public function execute($subPage)
  {
    // Generally the wgArticlePagh ends with .../$1
    $path = str_replace( '$1' , '' , $GLOBALS['wgArticlePath'] );
    $type = $this->getRequest()->getVal( 'type' );

    // Change AccountsHandler for better name later
    $urls['signin'] = $path.'Special:AccountsHandler/start';
    $urls['signup'] = $path.'Special:AccountsHandler/signup';

    $this->setHeaders();

    if ( $subPage === 'signup' ) {
      $selectedUri = $urls['signup'];
    } elseif ( $type === 'signup' ) {
      $selectedUri = $urls['signup'];
    } else {
      $selectedUri = $urls['signin'];
    }

    $this->getOutput()->redirect( $selectedUri );
  }
}
