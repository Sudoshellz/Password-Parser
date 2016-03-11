<?php
/**
 * Regex Patterns
 *
 * @category  PHP
 * @package   comboParser
 * @author    mb@xato.net
 * @copyright (c)2011 Mark Burnett, All Rights Reserved
 * @version   SVN: $Id:$
 */

  /**
   * Define the regex patterns used for parsing
   */

  if (!defined('QUOTES')) {

    // Common pattern_id elements
    define('QUOTES', '(?:[\x27\x22])');
    define('NOT_QUOTES', '(?:[^\x27\x22])');
    define('OPTIONAL_QUOTES', '(?:[\x27\x22]?)');
    define('EMAIL', '(?P<email>(?P<username>[a-z0-9!#$%&\'*+/=?^_{|}~-]+(?:\.[a-z0-9!#$%&\'*+/=?^_{|}~-]+)*)@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)');
    define('URL', '(?P<url>(?:h[a-z]{2}ps?|ftp)://[-A-Z0-9+&@#/%?=~_|$!:,.;]*[A-Z0-9+&@#/%=~_|$])'); // allows for hxxp://
    define('LABEL_DELIMITER', '[:\s=]+(?:\s{0,3}is\s{1,3})?');


    /* Username pattern_id
    * Defines constraints for matching a username
    */
    define('USERNAME', OPTIONAL_QUOTES . '(?P<username>[a-zA-Z0-9~!@#$%^&*-_=\+\.\?]{3,50})' . OPTIONAL_QUOTES);


    /* Password pattern_id
    * Defines constraints for matching a password
    */
    define('PASSWORD', OPTIONAL_QUOTES . '(?P<password>[a-zA-Z0-9~!@#$%^&*\-_=\+;,\.\?]{4,50})' . OPTIONAL_QUOTES);


    /* USERNAME_LABEL pattern_id: account, acct, admin, e-mail, email, id, login,
    * name, uid, user, user id, user-id, user-name, user_id, user_name,
    * userid, username, user_login, user-login, user-email, user_email, :, u
    *
    * Includes the label up to and including any delimiters (i.e, "username: ")
    *
    * TODO: Needs non-english words
    */

    define('USERNAME_LABEL', '(?:a(?:cc(?:oun)?t|dmin)|e\-?mail|id|login|name|u(?:id|ser(?:\-(?:email|id|login|name)|_(?:email|id|login|name)|name)?)|[lu])' . LABEL_DELIMITER);


    /* PASSWORD_LABEL pattern_id: pass, passw, password, pwd, user-pass, user_pass, userpass
    *
    * TODO: Needs non-english words
    */
    define('PASSWORD_LABEL', '(?:(?:p(?:ass(?:w[0o]rd|w)?|wd)|user[\-_]?pass|p))' . LABEL_DELIMITER);
  }


  // Pattern 0 is reserved to indicate using all patterns
  $this->patterns[0] = null;


  /* Pattern 1
  * Basic Combos in various formats.
  *
  * Examples:
  *   user1:password1
  *   user1 : password1
  *   user1 - password1
  *   user1	password1
  *   user@email:password
  *   number - username - password
  *   1. username - password
  *   "username"  "password"
  *
  */

  unset($pattern);
  $pattern = '^(?:[\d]+\.?\s+)?'; // Line begins with a line number
  $pattern .= USERNAME;
  $pattern .= '(?:\s{0,3}[\s-:,\|\;]\s{0,3})'; // Delimiter between username/pass
  $pattern .= PASSWORD;
  $pattern .= '$';

  $this->patterns[1] = '`' . $pattern . '`sim';


  /* Pattern 2
  * Labeled on a single line
  * User: 000000000 Password: 3027688
  */


  unset($pattern);
  $pattern = '^(?:\s*)';
  $pattern .= USERNAME_LABEL;
  $pattern .= USERNAME;
  $pattern .= '[:=\-\s\|]+';
  $pattern .= PASSWORD_LABEL;
  $pattern .= PASSWORD;
  $pattern .= '\s*';

  $this->patterns[2] = '`' . $pattern . '`imS';


  /* Pattern 3
  * Matches a members URL
  *
  * Example:
  * http://user1:password1@www.example.com
  *
  */


  unset($pattern);
  $pattern = '//'; // Matches after http://
  $pattern .= USERNAME . ':' . PASSWORD;
  $pattern .= '@';
  // TODO: Match URL itself
  //$pattern_id = preg_quote($pattern_id, '#');

  $this->patterns[3] = '`' . $pattern . '`S';


  /* Pattern 4
  * URLs and password lists. This works with many common password dumps and keylogger logs
  *
  * Examples:
  * http://www.example.com/members/ L:user1 P:password1
  * http://www.example.com/members login:user1 password:password1
  * http://www.example.com/members user: user1 pass:  password1
  * http://www.example.com/members L: user1 P:  password1
  *
  * Login: user1 passw:password1
  * L:user1 P:password1
  * username:user1 password:password1
  * Name: user1 Password: password1
  *
  * username = user1  password= password1
  * u=user1 p=password1
  * username	user1  password	password1
  * login id: user1 password: password1
  *
  *
  * Multiline examples:
  * Login:		user1
  * Password:	password1
  *
  * Email :iconhyd@gmail.com
  * Password :education
  *
  * name: = "mi5";
  * password: = "mi5r01";
  *
  * Your username is Joe
  * Your password is apples
  *
  */


  unset($pattern);
  $pattern = '[\s*]';
  $pattern .= USERNAME_LABEL . USERNAME;
  $pattern .= '[\?r\n\s\*\-\s\t\|]+(?:your\s)?'; // Stuff that might be at the beginning or end of a line
  $pattern .= PASSWORD_LABEL . PASSWORD;
  $pattern .= '([^\s\r?\n]{0,30})\"?\W';
  //$pattern_id = preg_quote($pattern_id, '#');

  $this->patterns[4] = '`' . $pattern . '`iS';


  /* Pattern 5
  * Password/email address combos by themselves ona line (password appears first)
  *
  * Examples:
  * password	email@address.com
  * password | email@address.com
  */

  unset($pattern);
  $pattern = '^';
  $pattern .= PASSWORD;
  $pattern .= '[:\s\|]{1,8}';
  $pattern .= EMAIL;
  $pattern .= '$';
  //$pattern_id = preg_quote($pattern_id, '#');

  $this->patterns[5] = '`' . $pattern . '`imS';


  /* Pattern 6
  * DSN string
  *
  * Examples:
  * DSN=myDsn;Uid=myUsername;Pwd=;
  * Provider=MSDAORA.1;Password=dbusername;User ID=dbpassword;Data Source=localhost;
  */

  unset($pattern);
  $pattern = '(?:User\sID|uid)(?:name)?\s?=\s?(?<username>[^;]{3,40});'; // User ID=xxxxxxxx
  $pattern .= '.*'; // Ignore the stuff in between
  $pattern .= '(?:p(?:ass(?:word)?|wd))\s?=\s?(?<password>[^;]{3,30})?'; // Password= (can be blank)

  $this->patterns[6] = '`' . $pattern . '`iS';

  /* Pattern 7
  * Reverse order of pattern 6 (password first)
  *
  * Examples:
  * DSN=myDsn;Uid=myUsername;Pwd=;
  * Provider=MSDAORA.1;Password=dbusername;User ID=dbpassword;Data Source=localhost;
  */

  unset($pattern);
  $pattern = '(?:p(?:ass(?:word)?|wd))\s?=\s?(?<password>[^;]{3,30})?'; // Password= (can be blank)
  $pattern .= '.*'; // Ignore the stuff in between
  $pattern .= '(?:User\sID|uid)(?:name)?\s?=\s?(?<username>[^;]{3,40});'; // User ID=xxxxxxxx

  $this->patterns[7] = '`' . $pattern . '`iS';


  /* Pattern 8
  * Database info stored as PHP constants
  *
  * Requires password line to immediately follow username line
  *
  * Examples:
  * DEFINE ('DB_USER', 'username');
  * DEFINE ('DB_PASSWORD', 'password');
  * DEFINE ('DB_HOST', 'localhost');
  * DEFINE ('DB_NAME', 'sitename');
  */

  unset($pattern);
  $pattern = 'define\s*\(\s*';
  $pattern .= QUOTES . '[A-Z0-9_]*(?:user|login|acct|account|admin)[A-Z0-9_]*' . QUOTES;
  $pattern .= '\s{0,4},\s{0,4}'; // The comma and any spaces
  $pattern .= QUOTES . USERNAME . QUOTES;
  $pattern .= '[\s;\)]+'; // Finish off the declare statement
  $pattern .= '(?:\s*[//\#].*\s)?'; // Optional end-of-line comment
  $pattern .= '[\r?\n]+'; // Finish off the line

  $pattern .= '[\/\*\s]*'; // Stuff that could be at the beginning of a line
  $pattern .= 'define\s*\(\s*';
  $pattern .= QUOTES . '[A-Z0-9_]*(?:p(?:ass(?:word)?|wd))[A-Z0-9_]*' . QUOTES;
  $pattern .= '\s{0,4},\s{0,4}';
  $pattern .= QUOTES . PASSWORD . QUOTES;
  //$pattern_id = preg_quote($pattern_id, '#');

  $this->patterns[8] = '`' . $pattern . '`iS';


  /* Pattern 9
  * Hard coded connect info in PHP and other passwords found in code

  * Examples:
  *  $db_user = "dbuser";
  *  $db_pass = "tKe4wKUo";
  *
  *  $this->db_username = 'user';
  *  $this->pass = 'passw0rd';

  */

  unset($pattern);
  $pattern = '\$[a-zA-Z0-9-_>]*[a-zA-Z0-9_]*' . USERNAME_LABEL . '[a-zA-Z0-9_]*';
  $pattern .= QUOTES . USERNAME . QUOTES;
  $pattern .= '\s*;';
  $pattern .= '(?:\s*[//\#].*\s)?'; // Optional end-of-line comment
  $pattern .= '[\r?\n]+'; // Finish off the line
  $pattern .= '[^\$]*'; // Stuff that could be at the beginning of a line
  $pattern .= '\$[a-zA-Z0-9-_>]*[a-zA-Z0-9_]*' . PASSWORD_LABEL;
  $pattern .= QUOTES . PASSWORD . QUOTES;

  $this->patterns[9] = '`' . $pattern . '`simS';


  /* Pattern 10
   *
   *
   */

  $this->patterns[10] = null;


  /* Pattern 11
  * Passwords in mysql connect statements
  *
  * See also:
  *   http://php.net/mysql_connect
  *   http://php.net/manual/en/pdo.construct.php
  *   http://php.net/manual/en/mysqli.construct.php
  *
  * Examples:
  * mysql_connect ("localhost", "admin", "1ABc34cps")
  * mysqli_connect("mysql65","admin","6404bb2c")
  * $link = mysql_connect('127.0.0.1:3307', 'mysql_user', 'mysql_password');
  * $mysqli = new mysqli('localhost', 'my_user', 'my_password', 'my_db');
  * $db = new PDO('mysql:host=myhost;dbname=mydb', 'login', 'password');
  *
  * Covers these functions:
  *   ->connect
  *   ->real_connect
  *   mysql_connect
  *   mysql_pconnect
  *   mysqli_connect
  *   mysqli_real_connect
  *   mysqli
  *   PDO
  *
  */

  unset($pattern);
  $pattern = '(?:mysql(?:i(?:(?:_real)?_connect)?|_connect)?|(?:real_)?connect|PDO)'; // The function
  $pattern .= '\s*\(\s*';
  $pattern .= QUOTES . NOT_QUOTES . '*' . QUOTES; // Skip the host name
  $pattern .= '\s{0,4},\s{0,4}';
  $pattern .= QUOTES . USERNAME . QUOTES;
  $pattern .= '\s{0,4},\s{0,4}';
  $pattern .= QUOTES . PASSWORD . QUOTES;

  $this->patterns[11] = '`' . $pattern . '`imS';


  /* Pattern 12
   *  Hashcat format
   *   9SHANE9:93279e3308bdbbeed946fc965017f67a:121212
   *   9sunchaser9:6d270c0bdf9489e7d214b73e1c83f98d:nightwish
   *   9Suprafly9:233094795459baa9edd32a4a4a7c7791:9landmacht
   */

  unset($pattern);
  $pattern = '^(?P<username>[^:]*):([a-fA-F0-9]{32}):(?P<password>.*)$'; // The function
  $this->patterns[11] = '`' . $pattern . '`mS';
