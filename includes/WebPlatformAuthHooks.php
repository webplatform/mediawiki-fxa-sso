<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

// FIXME, Loader.. :(
require_once( dirname( __FILE__ ) . '/WebPlatformAuthUserFactory.php' );
require_once( dirname( __FILE__ ) . '/FirefoxAccountsManager.php' );

// Guzzle Exceptions
use Guzzle\Http\Exception\ClientErrorResponseException;

class WebPlatformAuthHooks
{
  /**
   * Disable redundant Special pages
   *
   * Some pages aren’t needed while using an external authentication
   * source.
   *
   * Explictly disabling local pages:
   * * Password change,
   * * e-mail confirmation disabled when autoconfirm is disabled.
   *
   * They will be handled by our external provider anyway
   *
   * Blantly copied from SimpleSamlAuth::hookInitSpecialPages()
   * @link https://github.com/yorn/mwSimpleSamlAuth
   *
   * @link http://www.mediawiki.org/wiki/Manual:Hooks/SpecialPage_initList
   *
   * @param $pages string[] List of special pages in MediaWiki
   *
   * @return boolean|string true on success, false on silent error, string on verbose error
   */
  public static function hookInitSpecialPages( &$pages ) {
    unset( $pages['PasswordReset'] );
    unset( $pages['ConfirmEmail'] );
    unset( $pages['ChangeEmail'] );

    // Those are overriden
    //unset( $pages['ChangePassword'] );
    //unset( $pages['Userlogout'] );
    //unset( $pages['Userlogin'] );

    return true;
  }

  /**
   * Disable redundant preferences
   *
   * Since an external system is taking care of those, lets
   * remove them from the special pages.
   *
   * Blantly copied from SimpleSamlAuth::hookLimitPreferences()
   * @link https://github.com/yorn/mwSimpleSamlAuth
   *
   * @link http://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
   *
   * @param $user User User whose preferences are being modified.
   *                   ignored by this method because it checks the SAML assertion instead.
   * @param &$preferences Preferences description array, to be fed to an HTMLForm object.
   *
   * @return boolean|string true on success, false on silent error, string on verbose error
   */
  public static function hookLimitPreferences( $user, &$preferences ) {
    unset( $preferences['password'] );
    unset( $preferences['rememberpassword'] );
    unset( $preferences['emailaddress'] );

    // Should disable realname here and have
    // FxA do the handling for us
    //unset( $preferences['realname'] );

    return true;
  }

  /**
   * Load session from user
   *
   * At this time here, we can be in two situations. Either we are an
   * anonymous user (user object here has most likely no name set we assume)
   * but we also might happen to just be back from our trip to the OAuth
   * Resource server.
   *
   * Documentation says we should read cookies and just pop in that user object
   * the name coming from the cookies. I expect that just breaks any security
   * steps we’ve taken so far.
   *
   * Since we came from the OAuth Resource server and the user had a successful
   * authentication exchange, the Request URI should have TWO properties
   *
   * - code
   * - state
   *
   * The Code will be used right after to get a bearer token, so, its safe
   * to assume that we can start that validation here instead than later in the
   * execution flow.
   *
   * If that step was successful, we trust that we already saved
   * a state object in the Memcache server. Lets use that as a way to check
   * **before any html has been given to the browser** to validate that user.
   *
   * We already might got cookies:
   *
   * - (.*)Token,
   * - (.*)UserID
   * - (.*)UserName
   *
   * Since we already can know expectable data from the resource server,
   * use this hook as an event handler to actually do the validation.
   * from our OAuth Resource server, and nothing else should be done
   *
   * @param  [type] $user   [description]
   * @param  [type] $result [description]
   * @return [type]         [description]
   */
  public static function onUserLoadFromSession( $user, &$result )
  {
    $GLOBALS['poorman_logging'] = array();

    $diagnosis['session']  = RequestContext::getMain()->getRequest()->getCookie('_session');
    $diagnosis['username'] = RequestContext::getMain()->getRequest()->getCookie('UserName');
    $diagnosis['user_id']  = RequestContext::getMain()->getRequest()->getCookie('UserID');
    if(isset($_COOKIE['wpdSsoUsername'])) $diagnosis['wpdSsoUsername'] = $_COOKIE['wpdSsoUsername'];
    if(isset($_POST['recoveryPayload'])) $diagnosis['recoveryPayload'] = $_POST['recoveryPayload'];

    //header("X-WebPlatform-Debug: ".substr(str_replace('"','', json_encode($diagnosis,true)),1,-1));
    //header("X-WebPlatform-Debug-Cookie: ".substr(str_replace('"','', json_encode($_COOKIE,true)),1,-1));

    //if(isset($_POST['recoveryPayload'])){
    //  header("X-WebPlatform-Debug-Edgecase2: recoveryPayload is present");
    //}

    // Use Native PHP way to check REQUEST params
    $state_key = (isset($_GET['state']))?$_GET['state']:null;
    $code = (isset($_GET['code']))?$_GET['code']:null;
    $bearer_token = null;
    $profile = null;
    $site_root = str_replace('$1','', $GLOBALS['wgArticlePath']);
    //$registeredCookieWithUsername = RequestContext::getMain()->getRequest()->getCookie( 'UserName' );
    //$GLOBALS['poorman_logging'][] = 'Registered cookie username: '.print_r($registeredCookieWithUsername, 1);

    $sessionToken = ( isset( $_POST['recoveryPayload'] ) ) ? $_POST['recoveryPayload'] : null;
    if ( is_string( $sessionToken ) ) {
      header('Cache-Control: no-store, no-cache, must-revalidate');

      // TODO: Do some more validation with this, see
      //   notes at http://docs.webplatform.org/wiki/WPD:Projects/SSO/Improvements_roadmap#Recovering_session_data
      try {
        $uri = 'https://profile.accounts.webplatform.org/v1/session/recover';
        $client = new Guzzle\Http\Client();
        $client->setDefaultOption( 'timeout', 22 );
        $subreq = $client->get($uri);
        $subreq->setHeader( 'Content-type', 'application/json' );
        $subreq->setHeader( 'Authorization', 'Session ' . $sessionToken );

        $r = $client->send( $subreq );
      } catch ( Guzzle\Http\Exception\ClientErrorResponseException $e ) {
        $clientErrorStatusCode = $e->getResponse()->getStatusCode();
        $clientErrorReason = $e->getResponse()->getReasonPhrase();

        // We are considering that wgUser id 0 means anonymous
        if($clientErrorStatusCode === 401 && $GLOBALS['wgUser']->getId() !== 0) {
          $GLOBALS['wgUser']->logout();
          header("HTTP/1.1 401 Unauthorized");
          header("X-WebPlatform-Outcome-1: Session closed, closing local too");

        // 401 AND uid 0 means we have nothing to do
        } elseif($clientErrorStatusCode === 401 && $GLOBALS['wgUser']->getId() === 0) {
          header("HTTP/1.1 204 No Content");
          header("X-WebPlatform-Outcome-2: Session closed both local and accounts");
        }

        return true;

      } catch ( Guzzle\Http\Exception\CurlException $e ) {
        header("X-WebPlatform-Outcome-3: CurlException");
        header("HTTP/1.1 400 Bad Request");
        echo($e->getMessage());

        return true;
      }

      try {
        $data = $r->json();
      } catch(Exception $e) {
        header("HTTP/1.1 400 Bad Request");
        header("X-WebPlatform-Outcome-4: Profile refused communication");
        echo "Profile server refused communication";

        return true;
      }

      # 20140807
      $wgUserName = (is_object($GLOBALS['wgUser']))?$GLOBALS['wgUser']->getName():null;
      if(isset($data['username']) && strtolower($wgUserName) === strtolower($data['username'])) {
        header("X-WebPlatform-Outcome-5: " . strtolower($wgUserName) . " is " . strtolower($data['username']));
        header("HTTP/1.1 204 No Content");

        return true; // All is good
      }

      $tempUser = WebPlatformAuthUserFactory::prepareUser( $data );
      wfSetupSession();
      if( $tempUser->getId() === 0 ){
        // No user exists whatsoever, create and make current user
        $tempUser->ConfirmEmail();
        $tempUser->setEmailAuthenticationTimestamp( time() );
        $tempUser->setPassword( User::randomPassword() );
        $tempUser->setToken();
        $tempUser->setOption( "rememberpassword" , 0 );
        $tempUser->addToDatabase();
        $GLOBALS['poorman_logging'][] = sprintf( 'User %s created' , $tempUser->getName() ) ;
      } else {
        // User exist in database, load it
        $tempUser->loadFromDatabase();
        $GLOBALS['poorman_logging'][] = sprintf( 'Session for %s started' , $tempUser->getName() ) ;
      }
      $GLOBALS['poorman_logging'][] = $tempUser->getId();
      $GLOBALS['wgUser'] = $tempUser;
      $tempUser->saveSettings();
      $tempUser->setCookies();

      // Ideally, the first false below should be true! But we need SSL at the top level domain
      setcookie('wpdSsoUsername', $data['username'], time()+60*60*7, '/', '.webplatform.org', false, true);

      if (isset($_GET['username'])) {
        header("X-WebPlatform-Username: ".$_GET['username']);
      }
      #header("X-WebPlatform-Recovery: " . urlencode(json_encode($data)) );

      header("HTTP/1.0 201 Created");

      return true;
    }
    if ( is_string( $state_key ) && is_string( $code ) ) { // START IF HAS STATE AND CODE
      // WE HAVE STATE AND CODE, NOW ARE THEY JUNK?

      //$GLOBALS['poorman_logging'][] = 'About to retrieve data: '.(($user->isLoggedIn())?'logged in':'not logged in'); // DEBUG

      // Since we DO have what we need to get
      // to our validation server, please do not cache.
      // ... and since WE DIDN’t send any HTML, yet (good boy)
      // we can actually do that.
      header('Cache-Control: no-store, no-cache, must-revalidate');

      try {
        $apiHandler = new FirefoxAccountsManager( $GLOBALS['wgWebPlatformAuth'] );
        // $code can be used ONLY ONCE!
        //$GLOBALS['poorman_logging'][] = 'Code: '.print_r($code,1); // DEBUG
        $bearer_token = $apiHandler->getBearerToken( $code );

      } catch ( ClientErrorResponseException $e ) {

        $msg  = 'Could not get authorization token';

        $GLOBALS['poorman_logging'][] = $msg;

        $msg .= ', returned after FirefoxAccountsManager::getBearerToken(), it said:' . $e->getMessage();
        $obj = json_decode($e->getResponse()->getBody(true), true);
        $msg .= (isset($obj['reason'])) ? ', reason: ' . $obj['reason'] : null;
        $msg .= (isset($obj['message'])) ? ', message: '.$obj['message'] : null;

        error_log($msg);

        //header('Location: '.$site_root);

        return true;
      } catch ( Exception $e ) {
        // Other error: e.g. config, or other Guzzle call not expected.
        $msg = 'Unknown error, Could not get authorization token';
        $GLOBALS['poorman_logging'][] = $msg;

        $msg .= ', returned a "' . get_class($e);
        $msg .= '" after FirefoxAccountsManager::getBearerToken(), it said: '.$e->getMessage();

        error_log($msg);

        //header('Location: '.$site_root);

        return true;
      }

      //$GLOBALS['poorman_logging'][] = 'Bearer token: '.print_r($bearer_token,1); // DEBUG

      // FirefoxAccountsManager::getBearerToken()
      // returns an array.
      if ( is_array( $bearer_token ) ) {
        try {
          $profile = $apiHandler->getProfile( $bearer_token );

          //$GLOBALS['poorman_logging'][] = 'Profile: '.print_r($profile,1); // DEBUG

          $tempUser = WebPlatformAuthUserFactory::prepareUser( $profile );
        } catch ( ClientErrorResponseException $e ) {

          $msg = 'Could not retrieve profile data';
          $GLOBALS['poorman_logging'][] = $msg;

          $msg .= ', returned a "' . get_class($e);
          $msg .= '" after calling getProfile(), it said: '.$e->getMessage();

          $obj = json_decode($e->getResponse()->getBody(true), true);
          $msg .= (isset($obj['reason'])) ? ', with reason: ' . $obj['reason'] : null;
          $msg .= (isset($obj['message'])) ? ', message: '.$obj['message'] : null;

          error_log($msg);

          return true;
        } catch ( Exception $e ) {
          $msg = 'Unknown error, Could not get profile data or create new user';

          $GLOBALS['poorman_logging'][] = $msg;

          $msg .= ', returned a "' . get_class($e);
          $msg .= '" after FirefoxAccountsManager::getProfile(), it said: '.$e->getMessage();

          error_log($msg);

          return true;
        }

        // Note that, HERE, whether we use $GLOBALS['wgUser']
        // or $user (passed in this function call from the hook)
        // or EVEN the one passed to WebPlatformAuthUserFactory::prepareUser()
        // it should be the same. It is assumed that in prepareUser() it the call
        // to MW User::loadDefaults($username) makes that binding.
        // #DOUBLECHECKLATER

        // Let’s be EXPLICIT
        //
        // Note that MW User::isLoggedIn() is not **only** checking
        // whether the user is logged in per se. But rather do both;
        // checking if the user exists in the database. Doesn’t mean
        // the session is bound, yet.
        wfSetupSession();
        if( $tempUser->getId() === 0 ){
          // No user exists whatsoever, create and make current user
          $tempUser->ConfirmEmail();
          $tempUser->setEmailAuthenticationTimestamp( time() );
          $tempUser->setPassword( User::randomPassword() );
          $tempUser->setToken();
          $tempUser->setOption( "rememberpassword" , 0 );
          $tempUser->addToDatabase();
          $GLOBALS['poorman_logging'][] = sprintf( 'User %s created' , $tempUser->getName() ) ;
        } else {
          // User exist in database, load it
          $tempUser->loadFromDatabase();
          $GLOBALS['poorman_logging'][] = sprintf( 'Session for %s started' , $tempUser->getName() ) ;
        }
        $GLOBALS['poorman_logging'][] = $tempUser->getId();
        $GLOBALS['wgUser'] = $tempUser;
        $tempUser->saveSettings();
        $tempUser->setCookies();

        //$GLOBALS['poorman_logging'][] = ($GLOBALS['wgUser']->isLoggedIn())?'logged in':'not logged in'; // DEBUG
        //$GLOBALS['poorman_logging'][] = $tempUser->getId(); // DEBUG
        $state_data = $apiHandler->stateRetrieve( $state_key );

        if ( is_array($state_data) && isset( $state_data['return_to'] )) {
          $apiHandler->stateDeleteKey( $state_key );
          $GLOBALS['poorman_logging'][] = 'State data: '.print_r($state_data, 1);

          header('Location: ' . $state_data['return_to'] );

          return true; // Even though it might just be sent elsewhere, making sure.
        }
      } else {
        $GLOBALS['poorman_logging'][] = 'No bearer tokens';

        header('Location: '.$site_root);
      }
    }

    //$GLOBALS['poorman_logging'][] = ($GLOBALS['wgUser']->isLoggedIn())?'logged in':'not logged in';

    /**
     * I can put true or false because we wont be using local authentication
     * whatsoever. Hopefully that’s the way to do.
     *
     * Quoting the doc
     *
     *   "When the authentication should continue undisturbed
     *    after the hook was executed, do not touch $result. When
     *    the normal authentication should not happen
     *    (e.g., because $user is completely initialized),
     *    set $result to any boolean value."
     *
     *    -- 2014-05-22 http://www.mediawiki.org/wiki/Manual:Hooks/UserLoadFromSession
     *
     * But, if I set $result to either true or false, it doesn’t make the UI to
     * act as if you are logged in, AT ALL. Even though I created
     * the user and set it to the global object. I’d like to investigate on why we cannot
     * set either true or false here because it is unclear what it means undisturbed... we are
     * creating local users, based on remote data, but authentication implies password, we arent using
     * local ones, what gives? #DOUBLECHECKLATER
     */
    //$result = false; // Doesn’t matter true or false, and its passed here by-reference.

    return true; // Hook MUST return true if it was as intended, was it? (hopefully so far)
  }
}
