<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

// FIXME, Loader.. :(
require_once( dirname( dirname( __FILE__ ) ) . '/FirefoxAccountsManager.php' );
require_once( dirname( dirname( __FILE__ ) ) . '/WebPlatformAuthUserFactory.php' );

// Guzzle Exceptions
//use Guzzle\Http\Exception\ClientErrorResponseException;

/**
 * Accounts Handler Special Page
 *
 * A "Controller" (but in MediaWiki) that binds MediaWiki specific
 * to other decoupled moving parts.
 *
 * Related documentation:
 * * http://www.mediawiki.org/wiki/Manual:Special_pages
 */
class AccountsHandlerSpecialPage extends UnlistedSpecialPage
{
  /**
   * FxA API handler
   *
   * @var FirefoxAccountsManager
   */
  private $apiHandler;

  /**
   * Constructor following MW initialization convention
   */
  public function __construct()
  {
/* MAYBE REMOVE */

    if ( !isset( $GLOBALS['wgDBname'] ) ) {
      $this->getOutput()->showErrorPage( 'error' , wfMessage( 'Please set wgDBname in your local config' ) );

      return;
    }

    $other = array();
    $other['cfg']['appname'] = $GLOBALS['wgDBname'];
    $config = array_merge( $GLOBALS['wgWebPlatformAuth'] , $other );

    try {
      $apiHandler = new FirefoxAccountsManager( $config );
    } catch ( Exception $e ) {
      $this->getOutput()->showErrorPage( 'error' , $e->getMessage() );

      return;
    }

    $this->apiHandler    = $apiHandler;

    parent::__construct( 'AccountsHandler' );
  }

  public function execute($par)
  {
    $this->setHeaders();
    $this->getOutput()->setPageTitle( 'webplatformauth-main-specialpage-title' );

    switch ( $par ) {
      case 'start':
        $this->_start();
      break;
      case 'signup':
        $this->_signup();
      break;
      case 'logout':
        $this->_logout();
      break;
      case 'callback': // should we keep that? or have a callback url and a default?
        $this->_callback();
      break;
      default:
        $this->_default();
      break;
    }
  }

  private function _callback()
  {
    //$this->getOutput()->addHtml('Debugging: '.print_r($GLOBALS['poorman_logging'],1));
  }

  private function _default()
  {
    $this->getOutput()->redirect( $this->_getRefererUri() );
  }

  private function _signup()
  {
    $goto = $this->apiHandler->initHandshake( $this->_getRefererUri() , true );
    $this->getOutput()->redirect( $goto );
  }

  private function _start()
  {
    $goto = $this->apiHandler->initHandshake( $this->_getRefererUri() );
    $this->getOutput()->redirect( $goto );
  }

  private function _logout()
  {
    $GLOBALS['wgUser']->logout();
    unset($_COOKIES);
    $this->getOutput()->addWikiText( 'Logout method' );

    $this->getOutput()->redirect( $this->_getRefererUri() );
  }

  private function _getRefererUri()
  {
    // Generally the wgArticlePagh ends with .../$1
    $path = str_replace('$1','', $GLOBALS['wgArticlePath']);
    $h = $this->getRequest()->getAllHeaders();

    return ( isset( $h['REFERER'] ) ) ? $h['REFERER'] : $path;
  }
}
