<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

// Guzzle classes
use Guzzle\Http\Client;

// Guzzle Exceptions
use Guzzle\Http\Exception\ClientErrorResponseException;

class FirefoxAccountsManager
{

  const NO_RESULT_YET     = 0;

  const ALREADY_CONNECTED = 2;

  const SUCCESSFUL        = 4;

  const USER_CREATED      = 8;

  const TTL = 3600;

  const MAX_CLIENT_TIMEOUT_SECONDS = 3;

  private $config = null;

  private $profile_data;

  public function __construct($config)
  {
    if ( !$this->hasAllRequiredConfiguration( $config ) ) {
      throw new Exception( 'Required configuration is missing' );
    }

    $this->config   = $config;

    // THIS SHOULD MOVE SOON
    $this->memcache = $GLOBALS['wgMemc'];

    return $this;
  }

  /** THIS CLASS SHOULD BE HANDLING ONLY WITH OAUTH, NOT MEMCACHE
      BELOW THIS LINE WILL HAVE TO MOVE OUTSIDE OF THIS CLASS **/

  /**
   * Serialize and save data to memcache
   *
   * Note that it also sets a time to live for the
   * cached version set to self::TTL
   *
   * @param string $cacheKey Cache key
   * @param mixed  $data     Data to send to memcached, will use serialize();
   *
   * @return null
   */
  private function memcacheSave($data)
  {
    $url_friendly_key = MWCryptRand::generateHex( 16 );

    $key = wfMemcKey( $url_friendly_key , 'wpdsso' );

    $this->memcache->set( $key , json_encode( $data ) , self::TTL );

    return $url_friendly_key;
  }

  /**
   * Delete entry from memcache from given cache key
   *
   * @param  string $cacheKey Cache key
   *
   * @return null
   */
  private function memcacheRemove($cacheKey)
  {
    $regen_key = wfMemcKey( $cacheKey , 'wpdsso' );

    $this->memcache->delete( $regen_key );
  }

  /**
   * Read entry from memcache from given cache key
   *
   * @param  string $cacheKey Cache key
   */
  private function memcacheRead($cacheKey)
  {
    $regen_key = wfMemcKey( $cacheKey , 'wpdsso' );

    return $this->memcache->get( $regen_key );
  }

  /**
   * Give a key, get associated state data
   *
   * @param  string $state_key
   *
   * @return array  Data in the same state as when sent to stateStash
   */
  public function stateRetrieve($state_key)
  {
    $data = $this->memcacheRead( $state_key );

    return json_decode( $data , 1);
  }

  public function stateDeleteKey($state_key)
  {
    $this->memcacheRemove( $state_key );
  }

  public function stateStash($data_array)
  {
    $key = $this->memcacheSave( $data_array );

    return $key;
  }

  /* END   -    THIS CLASS SHOULD BE...  */

  /**
   * Ask FxA to get a Bearer Token
   *
   * Typical return array is:
   * <code>
   *   array(
   *       "access_token" => '...'
   *       "token_type" => 'bearer'
   *       "scope" => 'profile'
   *   )
   * </code>
   *
   * @param  string $code received from POST on FxA OAuth authorize endpiont
   *
   * @return array  Recieved JSON and converted to PHP array
   */
  public function getBearerToken($code)
  {
    $m = $this->config['methods'];
    $e = $this->config['endpoints'];

    $uri = $e['fxa_oauth'].$m['token'];
    $packageData['client_id'] = $this->config['client']['id'];
    $packageData['client_secret'] = $this->config['client']['secret'];
    $packageData['code'] = $code;
    $postBody = json_encode( $packageData );

    try {
      $client = new Client();
      $client->setDefaultOption('timeout', self::MAX_CLIENT_TIMEOUT_SECONDS);
      $subreq = $client->createRequest( 'POST' , $uri , null , $postBody );
      $subreq->setHeader( 'Content-type' , 'application/json' );

      $r = $client->send( $subreq );
    } catch ( ClientErrorResponseException $e ) {
      throw $e;
    }

    $state_key = $this->stateStash( $stateData ); // Returns uuid

    return $r->json();
  }

  /**
   * Build OAuth provider URI
   *
   * @return string URI to the OAuth signin action on the FxA server
   */
  public function initHandshake($return_to=null, $signup=false)
  {
    $m = $this->config['methods'];
    $e = $this->config['endpoints'];

    if( $return_to !== null ) {
      $stateData['return_to'] = $return_to;
    }

    $stateData['scopes'] = array( 'profile' );

    $state_key = $this->stateStash( $stateData ); // Returns uuid

    $query_params['client_id'] = $this->config['client']['id'];
    $query_params['state'] = $state_key;
    // Space separated list of scopes keys
    $query_params['scope'] = implode( '+' , $stateData['scopes'] );

    if ( $signup === true ) {
      $query_params['action'] = 'signup';
    }

    return $e['fxa_oauth']
            . $m['authorize']
            . '?'
            . http_build_query( $query_params );
  }

  /**
   * Retrieve profile data from FxA profile server
   *
   * @param  array $token Token array recieved from OAuth handshake
   *
   * @return array profile data as an array
   */
  public function getProfile($token)
  {
    $m = $this->config['methods'];
    $e = $this->config['endpoints'];

    /**
     * $token has the following keys:
     * array(
     *  "access_token" => "..."
     *  "token_type" => "bearer"
     *  "scope" => "profile"
     * )
     *
     * At the moment, note that token_type is ONLY of type bearer.
     *
     * Make $token a strong typed Token class, and enforce at method setter #IMPROVEMENT
     */
    $token_value = $token['access_token'];
    $uri = $e['fxa_profile'] . 'profile';

    //$GLOBALS['poorman_logging'][] = 'Bearer token read : '.$token_value; // DEBUG

    try {
      $client = new Client();
      $client->setDefaultOption('timeout', self::MAX_CLIENT_TIMEOUT_SECONDS);
      $subreq = $client->createRequest( 'GET' , $uri );
      $subreq->setHeader( 'Authorization' , 'Bearer ' .  $token_value );
      $r = $client->send( $subreq );
    } catch ( Exception $e ) {
      throw $e;
    }

    return $r->json();
  }

  /**
   * Internal check to see if we have all required configuration
   *
   * @param  array  $config Constructor injected configuration to validate
   *
   * @return boolean        Whether it has what we need or not
   */
  private function hasAllRequiredConfiguration($config)
  {
    if ( !is_array( $config ) ) {
      return false;
    }

    return isset(
      $config['client']['id'],
      $config['client']['secret'],
      $config['endpoints']['fxa_oauth'],
      $config['endpoints']['fxa_profile'],
      $config['methods']['authorize'],
      $config['methods']['token']
    );
  }
}