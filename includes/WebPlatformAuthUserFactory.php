<?php

/**
 * MediaWiki SSO using Firefox Accounts
 *
 * Project details are available on the WebPlatform wiki
 * https://docs.webplatform.org/wiki/WPD:Projects/SSO/MediaWikiExtension
 **/

class WebPlatformAuthUserFactory
{
  /**
   * Return a user object
   *
   * Doesn’t add to the database, to keep an instance
   * you have to call addToDatabase(); to your new user.
   *
   *
   * <code>
   * // Expected entered array format
   * $user_array = array(
   *                 'fullName'=>'John Doe',
   *                 'username'=>'jdoe',
   *                 'email'=>'j@doe.name');
   *
   * // Check if the user can be created (i.e. has no entry in DB).
   *
   * // Then...
   * $user = self::prepareUser($GLOBALS['wgUser'], $user_array);
   *
   * // (poor man) persist —current author badly miss Doctrine2—
   * $user->addToDatabase();
   *
   * // Flag confirmation
   * $user->ConfirmEmail();
   *
   * // When making changes
   * $user->saveSettings();
   *
   * // Replace global scope object with our new one
   * // .. start session ...
   * $GLOBALS['wgUser'] = $user;        // Yep, like that :/
   * $GLOBALS['wgUser']->setCookies();  // ^
   * </code>
   *
   * @param User  &$user      MediaWiki User instance passed as reference
   * @param array $user_array Array of provided by our profile server data to use inside our local user
   *
   * @return void
   */
  public static function prepareUser( $user_array )
  {
    $desired_keys = array( 'fullName' , 'email' , 'username' );
    $input_keys = array_keys( $user_array );
    $diff = array_diff( $desired_keys , $input_keys );

    if ( count( $diff ) >= 1 ) {
      throw new Exception( sprintf( 'Recieved data has required keys that are missing:  %s' , implode( ', ' , $diff ) ) );
    }

    $username = ucfirst( $user_array['username'] );

    if ( !User::isUsableName( $username ) ) {
      throw new Exception( sprintf( 'Username %s has invalid characters' , $username ) );
    }

    /*
     * Based off of UserLoadFromSession Talk page documentation
     * http://www.mediawiki.org/wiki/Manual_talk:Hooks/UserLoadFromSession
     */
    $user = User::newFromName( $username );
    $user->setRealName( $user_array['fullName'] );
    $user->setEmail( $user_array['email'] );

    return $user;
  }
}