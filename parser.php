<?php

  /**
   * Passwords.io combos parser
   *
   */

  define('PARSER_DEBUG', false);
  define('PARSER_VERSION', '0.91');


  if (!class_exists('comboParser')) {

    /**
     * Combo Parser
     *
     *
     * @category     PHP
     * @package      comboParser
     * @author       mb@xato.net
     * @copyright (c)2011 Mark Burnett, All Rights Reserved
     * @version      SVN: $Id:$
     */
    class comboParser
    {

      /**
       * Local list of combos
       *
       * @var array
       */
      public $combos = array();

      /**
       * Flag to perform slow but more accurate entropy checks to discard combos
       * that are not human-generated. See checkEntropy for more details.
       *
       * @var bool
       */
      public $doEntropyCheck = true;

      /**
       * Original raw data to parse
       *
       * @var
       */
      public $rawData;

      /**
       * Timing
       *
       * @var
       */
      public $timeTaken;

      /**
       * Save hashes parsed from data (not implemented)
       *
       * @var bool
       */
      public $saveHashes = false;

      /**
       * List of saved hashes
       *
       * @var array
       */
      public $hashes = array();

      /**
       * Save email addresses parsed from data (not implemented)
       *
       * @var bool
       */
      public $saveEmails = false;

      /**
       * List of saved emails
       *
       * @var array
       */
      public $emails = array();

      /**
       * Remove well-known hacker combos from results
       *
       * @var bool
       */
      public $useBlacklist = true;

      /**
       * Debug mode
       *
       * @var bool
       */
      public $debug = false;

      /**
       * Output format
       *
       * @var string
       */
      public $template = '%username% %password%';

      /**
       * List of skipped combos
       *
       * @var array
       */
      public $skipped = array();

      /**
       * Patterns
       *
       * @var array
       */
      public $patterns = array();

      /**
       * @var array
       */
      public $stats = array();

      /**
       * construct
       */
      public function __construct()
      {
        if (PARSER_DEBUG) {

        }
        date_default_timezone_set('utc');
      }


      /**
       * Main parsing routine
       *
       * @param $rawData  mixed Unformatted data to be parsed
       * @param int $pattern_id
       *
       * @return bool
       */
      public function parse($rawData, $pattern_id = 0)
      {

        //if (PARSER_DEBUG) ob_start();
        if (PARSER_DEBUG) {
          //echo "Entering parse()\n";
        }
        unset($this->hashes, $this->emails, $this->skipped, $this->rawData);

        $t = microtime(true);

        // Basic decoding & pre-processing cleanup that improves parsing and reduces the size
        $this->rawData = urldecode($rawData);
        $this->rawData = htmlspecialchars_decode($this->rawData);
        $this->rawData = preg_replace('#</p>|<br\s?>#', "\n", $this->rawData); // convert <p> and <br> to crlf
        $this->rawData = str_replace('</p>', "\n", $this->rawData);
        $this->rawData = str_replace('<br>', "\n", $this->rawData);
        $this->rawData = preg_replace('#[^\r]\n#', "\n", $this->rawData); // convert <p> and <br> to crlf
        $this->rawData = preg_replace(
          '#[\x20\x09]+[\|\-][\x20\x09]+#',
          "\t",
          $this->rawData
        ); // convert " | " and " - " to tabs
        $this->rawData = preg_replace('#^\|[\x20\x09]+#m', '', $this->rawData); // lines that start with |
        $this->rawData = preg_replace('#\|\s?$#m', "\n", $this->rawData); // lines that end with |
        $this->rawData = preg_replace('#\s+[\r?\n]+?#m', "\n", $this->rawData); // remove spaces right before crlf
        $this->rawData = preg_replace('#<scrip.[^>]*?>.*?</scrip.>#si', ' ', $this->rawData); // remove javascript
        $this->rawData = preg_replace('#<styl.[^>]*?>.*?</style>#siU', ' ', $this->rawData); // remove styles
        $this->rawData = preg_replace('#<![\s\S]*?--[ \t\n\r]*>#', ' ', $this->rawData); // remove multi-line comments

        //$this->rawData = strip_tags($this->rawData);
        //file_put_contents('tmp.txt', $this->rawData);

        $this->rawData = str_replace('Data Found: ', '', $this->rawData); // Specific case
        $this->rawData = stripslashes($this->rawData);

        // Temporary hack: There's a bug in pattern 1 that messes up when a line begins with a ":"
        // This is my fix until I get around to tracking that down.
        $this->rawData = preg_replace('#^:#m', '::', $this->rawData);

        // There seem to be a large number of Russian dumps in the format username@example.com;password
        // This puts them in a more standard format
        $this->rawData = preg_replace('#^(.*@.*\.ru);#m', '\1:', $this->rawData);

        //echo $this->rawData);
        $this->rawData = $this->rawData . "\n "; //append space at the end for all regexes to match properly

        //  Define patterns
        if (PARSER_DEBUG) {
          //echo "Loading patterns...\n";
        }
        $this->loadPatterns();
        $pattern_count = count($this->patterns);
        if (PARSER_DEBUG) {
          //echo "Loaded $pattern_count patterns.\n";
        }

        // Parse passwords based on patterns selected
        if (PARSER_DEBUG) {
          //echo "Parsing specific ID... $pattern_id \n ";

        }


        if ($pattern_id == 0) {
          // Process all patterns
          for ($i = 1; $i < $pattern_count; $i++) {
            $this->parsePattern($i);
          }
        } else {
          // Process one pattern_id only
          $this->parsePattern($pattern_id);
        }

        // Sort and remove duplicates
        // todo: check performance of sort then unique vs unique then sort

        $this->combos = array_unique((array)$this->combos, SORT_STRING);
        sort($this->combos);

        $this->timeTaken = round(microtime(true) - (float)$t, 5);
        return;
      }


      /**
       * Determines as best as it can if the passed data looks like a common hash
       *
       * @param string $data A password that might be a hash
       *
       * @return bool
       */
      public function isHash($data)
      {
        $pattern = '%(?:\b|:|\s)([A-Za-z0-9\\\\.]{13}|[A-Fa-f0-9]{8}|[A-Fa-f0-9]{16}|[A-Fa-f0-9]{32}|[A-Fa-f0-9]{40}|[A-Fa-f0-9]{64}|[a-f0-9]{15}|[a-f0-9]{40}|\$\d\$([^$]*\$)?[/.A-Za-z0-9]{8,64})(?:\b|:|\s)%im';
        if (preg_match($pattern, $data, $matches)) {
          return true;
        } else {
          return false;
        }
      }


      /**
       * Execute a regex pattern_id against the raw data
       *
       * @param $pattern_id
       *
       * @internal param $pattern_id
       * @internal param $user_pos
       * @internal param $pass_pos
       * @internal param string $pattern_name
       */
      private function parsePattern($pattern_id)
      {
        if ($pattern_id) {
          $pass = null;
          $user = null;
          $startCount = count($this->combos);
          $t = microtime(true);

          if (PARSER_DEBUG) {
            echo "\n      Pattern " . $pattern_id . ": ";
          }
          $pattern = $this->patterns[$pattern_id];
          if (!empty($pattern)) {
            $ret = preg_match_all($pattern, $this->rawData, $matches);
            if ($ret === false) {
              die ("Error " . $this->pcreErrorText(preg_last_error()));
            }

            $c = count($matches[1]);

            $pass = $matches['password'];
            if (!empty($matches['username'])) {
              $user = $matches['username'];
            } else {
              if (!empty($matches['email'])) {
                $user = $matches['email'];
              }
            }
            if (PARSER_DEBUG) {
              echo 'Found ' . count($pass) . " combo(s)\n";
            }
            if (count($pass) && count($user)) {
              for ($i = 0; $i <= $c; $i++) {
                if (isset($user[$i]) && isset($pass[$i])) {
                  $this->skipped = array(
                    'Username too long' => 0,
                    'Username too short' => 0,
                    'Password too long' => 0,
                    'Password too short' => 0,
                    'Entropy too high' => 0,
                    'Password is a hash' => 0,
                    'Blacklisted username' => 0,
                    'Blacklisted password' => 0,
                    'Invalid data' => 0
                  );
                  $cleanup = $this->cleanup($user[$i], $pass[$i]);
                  $output = $this->template;
                  $output = str_ireplace('%username%', $cleanup['user'], $output);
                  $output = str_ireplace('%password%', $cleanup['pass'], $output);
                  //$output = str_ireplace('%email%', $email, $output);
                  //$output = str_ireplace('%hash%', $hash, $output);
                  if (!empty($output)) {
                    if (PARSER_DEBUG) {
                      //echo $output . "\n";
                    }
                  }
                  if (strlen($output) > 1) {
                    $this->combos[] = $output . "\n";
                  }
                }
              }
            }
          }
          $this->stats['patterns'][$pattern_id]['combos'] = count($this->combos) - $startCount;
          $this->stats['patterns'][$pattern_id]['time'] = round(microtime(true) - (float)$t, 5);
        }
      }


      /**
       * Cleanup usernames and passwords
       *
       * @param $user
       * @param $pass
       *
       * TODO: Check if both username and password are common dictionary words?
       *
       * @return array|null
       */
      function cleanup($user, $pass)
      {

        //$combo = array($user, $pass);


        /*-+=+-*-+=+-*-+=+-*-+=+-*-+=+-*-+=+-*-+=+-*-+=+-+-*-+=+-+-*-+
         * Validity Checks, entropy check, blacklists
         */


        // Trim
        $user = trim($user);
        $pass = trim($pass);

        // Remove line numbers from usernames
        $pattern = '/^\d{1,4}[\s\.\:\-]{0,2}/';
        $user = preg_replace($pattern, '', $user);

        if (strstr($user,'&')) {
          $user = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $user);
          $user = html_entity_decode($user);
        }

        if (strstr($pass,'&')) {
          $pass = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $pass);
          $pass = html_entity_decode($pass);
        }

        // Min and max lengths
        if ($this->mStrLen($user) > 60) {
          $this->skipped['Username too long']++;
          if (PARSER_DEBUG) {
            echo "     Username too long: $user\n";
          }

          return null;
        }
        if ($this->mStrLen($user) < 3) {
          $this->skipped['Username too short']++;
          if (PARSER_DEBUG) {
            echo "     Username too short:  $user\n";
          }

          return null;
        }
        if ($this->mStrLen($pass) > 40) {
          $this->skipped['Password too long']++;
          if (PARSER_DEBUG) {
            echo "     Password too long: $user\n";
          }

          return null;
        }

        if ($this->mStrLen($pass) < 3) {
          $this->skipped['Password too short']++;
          if (PARSER_DEBUG) {
            echo "      Password too short: $user\n";
          }

          return null;
        }

        // Entropy checks
        if ($this->doEntropyCheck) {
          $x = $this->checkEntropy($user);
          $y = $this->checkEntropy($pass);

          // High entropy or (medium entropy and both username and password are the same length)
          if (($x > .8 && $y > .8) || ($x > .5 && $y > .5 && strlen($user) == strlen($pass))) {
            $this->skipped['Entropy too high']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (High entropy)\n";
            }

            return null;
          }

          // Is hash and medium entropy
          if ($this->isHash($pass) && $y > .5) {
            $this->skipped['Password is a hash']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (Hash)\n";
            }

            return null;
          }

          // Numeric username and high entropy pass
          if (is_numeric($user) && $y > .8) {
            $this->skipped['Entropy too high']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (Numeric user, high entropy pass)\n";
            }

            return null;
          }
        }

        // Discard usernames or passwords that contain linefeeds or carriage returns
        if (strstr($user, "\r") || strstr($pass, "\n") || strstr($pass, "\r") || strstr($pass, "\n")) {
          $this->skipped['Invalid characters']++;
          if (PARSER_DEBUG) {
            echo '      Invalid characters - User: ' . $user . ' Pass: ' . $pass . "\n";
          }

          return null;
        }


        // Remove quotes and commas
        //list($user, $pass) = str_replace(array("'", '"', ','), '', $combo);
        $user = str_replace(array("'", '"', ','), '', $user);
        $pass = str_replace(array("'", '"', ','), '', $pass);


        // Strip out email addresses
        //$data = preg_replace('/\b([A-Z0-9._%+-]+)@[A-Z0-9.-]+\.[A-Z]{2,6}\b/', '$1', $data);

        // Convert e notation to numbers
        if (preg_match('/\d\.\d+E\+\d+/i', $pass)) {
          $pass = (float)$pass;
          $pass = (string)$pass;
        }


        // TEMP HACK Todo: Fix this
        if (strstr($pass, ':')) {
          if (PARSER_DEBUG) {
            echo "      Skipping password $pass (Contains a colon)\n";
          }
          return null;
        }


        // For now just reject combos that looks like &#9679;
        If ((preg_match('/&#\d{3,4};/i', $user) || (preg_match('/&#\d\d\d\d;/i', $pass)))) {
          return null;
        }


        // remove stuff that looks like a url (=)
        if (preg_match('#:\/\/|@[^\.]*\.\.#', $user, $matches) || preg_match('#:\/\/|@[^\.]*\.\.#', $pass, $matches)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping (URI)\n";
          }

          return null;
        }

        // Catch member URLs
        // Is this a duplicate of the previous check?
        if ((preg_match('~h?..p~i', $user) && substr($pass, 0, 2) == '//')
          || (preg_match('~.*(?:/?member|/login|/premium)|/.*/[^\s]*\s|\.[a-z][a-z]+[\/|:]~im', $user . ' ' . $pass))
          ||  (preg_match('~(?:www\.|members\.)[-A-Z0-9+&@#/%=_|$?!:,.]*[A-Z0-9+&@#/%=_|$]~im', $user . ' ' . $pass))
        ) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (URL)\n";
          }

          return null;
        }

        // Data is an IP address
        if (preg_match('#(?:[0-9]{1,3}\.){3}[0-9]{1,3}#', $user) || preg_match(
            '#(?:[0-9]{1,3}\.){3}[0-9]{1,3}#',
            $pass
          )
        ) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (IP Address)\n";
          }

          return null;
        }

        // Password is an email address
        // This will have false positives because the password really could be an email address
        // but it will eliminate tens of thousands of parsing errors.

        if (preg_match("/[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/im", $pass)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping  $pass (Email Address)\n";
          }

          return null;
        }


        // Username is truncated email address
        if (preg_match("/^.?@[^\.]*\./im", $user)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping  $user (Truncated Email Address)\n";
          }

          return null;
        }

        // Username or password starts with "(" or ends with ")"
        // Discard them for now but these may be able to be stripped out

        if ($user[0] == '(' || $pass[0] == '(' ||
          $pass[strlen($pass) - 1] == ')' || $pass[strlen($pass) - 1] == ')'
        ) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Parenthesis)\n";
          }

          return null;
        }

        // Both username and password contain a space
        if (strstr($user, ' ') && strstr($pass, ' ')) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Spaces)\n";
          }

          return null;
        }


        // Kill usernames like "Database" or "Table
        if (preg_match('/^database|table|system|server|hostname|target$/smi', $user)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Reserved word)\n";
          }

          return null;
        }

        // Catch ellipses
        if (strstr($user, '...') || strstr($pass, '...')) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Elipses)\n";
          }

          return null;
        }


        // Catch html tags in username or password
        if (preg_match(
          '~</?\s?(?:s(?:t(?:r(?:ike|ong)|yle)|(?:crip|elec|qr)t|pa(?:cer|n)|u[bp]|mall|ound|amp)?|b(?:l(?:ockquote|ink)|ase(?:font)?|o(?:dy|x)|gsound|utton|do|ig|r)?|t(?:[dr]|ext(?:area)?|(?:ab|it)le|(?:foo)?t|h(?:ead)?|body)|a(?:b(?:ove|br)|r(?:ray|ea)|cronym|ddress|pplet)?|c(?:o(?:l(?:group)?|mment|de)|aption|enter|ite)|n(?:o(?:(?:laye|b)r|frames|script|te)|extid)|f(?:i(?:eldset|g)|rame(?:set)?|o(?:nt|rm))|i(?:n(?:put|s)|sindex|frame|layer|mg)?|l(?:i(?:sting|nk)?|a(?:bel|yer)|egend)|m(?:a(?:rquee|p)|e(?:nu|ta)|ulticol)|o(?:pt(?:group|ion)|bject|l)|d(?:[dt]|i[rv]|e?l|fn)|h(?:[123456r]|ead|tml)|p(?:aram|re)?|r(?:ange|oot)|(?:va|wb)r|em(?:bed)?|q(?:uote)?|kbd|ul?|xmp)|color|clear|font-.*|list-.*|margin-.*|float|vertical-.*|padding-.*~im',
          $pass . ' ' . $user
        )
        ) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (HTML tag)\n";
          }

          return null;
        }

        // More HTML/CSS stuff
        if (preg_match('~.*transition|return|(?:.*background.*)|#\d+|(?:\d+(em|px|pt|%))|(?:left|right|top|bottom|middle|normal|italic|bold|none|both);|-(?:style|size|align|weight|width|height|spacing|color)~im',
          $pass . ' ' . $user
        )
        ) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (HTML/CSS tag)\n";
          }

          return null;
        }

        /* Get rid of stuff that looks like code
        *  define("DB_PREFETCH_ALL",0);
        *  define("FROMEMAILNAME",'RPPC');
        */

        if (preg_match('/\([\'"]|["\',\s]{3,6}|define\(/im', $pass . ' ' . $user)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Source code)\n";
          }

          return null;
        }

        // OS and file system stuff
        if (preg_match('/\.\.\/|;echo|;ls/im', $pass . ' ' . $user)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Source code)\n";
          }

          return null;
        }


        // Kill all usernames that start with 3 or more symbols, may kill a few legit usernames but most are bad
        if (preg_match('/^[^a-zA-Z0-9\s]{3,}/m', $user)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Source code)\n";
          }

          return null;
        }

        // Blacklists
        if ($this->useBlacklist) {

          // Known hackers
          $blacklist = 'x{1,3}.*pass.*|premium|2sexy2hot|4zima|arangarang|azzbite|babemagnet|c0ldsore|candoit0|capthowdy|cr3at0r|dabest|dawggyloves|ddenys|denyde|dreamv|drhacker|forxhq|xxxhq|forzima|fromajaxxx|frompwbb|gigacrap|h4x0r3d|h4x0r3d|hackedit|hackerz|hacksquat|hax411|heka6w2|hunterdivx|hxpduck|iownbuz|ischrome|ishere|jules\d\d|justone|lawina|mp3fanis|myownpass|neohack|niprip|onmeed|opsrule|ownzyou|ownzyou|pass4life|passbots|passfan|pr0t3st|pro@long|probot|prolong|realxxx|reduser\d\d|regradz|ripnip|rulzgz|strosek|surgict|suzeadmin|tama|tawnyluvs|valentijn|vbhacker|verygoodbot|wapbbs|washere|wazhere|webcracker|webcrackers|xcarmex2|xxxcrack|xxxhack|xxxpass|zcoolx|zealots|zolushka|ccbill|4xhq|\$andman|\[hide\]|ranger67|xv16n35h';
          if (preg_match('~' . $blacklist . '~', $user, $matches)) {
            $this->skipped['Blacklisted username']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (Known hackers blacklist)\n";
            }

            return null;
          }

          // Known hacker passwords
          $blacklist = '.*pas.*bot|forx.*|(?:is|w[u|a][z|s]|iz|)_?(?:here|back|numberone|thebest|dabest)|(?:greet[s|z]|l[0|o]ves|[0|o]wn[s|z])you.*';
          if (preg_match('~' . $blacklist . '~', $pass, $matches)) {
            $this->skipped['Blacklisted username']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (Known hackers blacklist)\n";
            }

            return null;
          }


          // Usernames blacklist
          // Discards these: user, username, pass, password, email, login
          if (preg_match('#^(pass(word)?|user(name)?|email|login|from|Computer|Program):?$#i', $user, $matches)) {
            $this->skipped['Blacklisted username']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (Username blacklist)\n";
            }

            return null;
          }

          // Passwords blacklist
          // Discards these: user, username, email, login
          if (preg_match('#^(user(name)?|email|login):?$#i', $pass, $matches)) {
            $this->skipped['Blacklisted password']++;
            if (PARSER_DEBUG) {
              echo "      Skipping combo $user:$pass  (password blacklist)\n";
            }

            return null;
          }



          // Misc stuff
          $blacklist = '\*\.\*|76n74link|-box|Computername|text\/javascript|\(?null\)?|\#NAME|\d\d\d\d-\d\d-\d\d.*\d\d:\d\d:\d\d|&nbsp;|\{float|\{text-align|\{height|\{display|function\(|height=|\(HIT|(em|p[xt]);?\s*\}|this\.|^link|&raquo|^http';

          if (preg_match('~' . $blacklist . '~i', $user, $matches)) {
            $this->skipped['Blacklisted username']++;
            if (PARSER_DEBUG) {
              echo "      Skipping user $user (Blacklisted username)\n";
            }

            return null;
          }
          if (preg_match('~' . $blacklist . '~i', $pass, $matches)) {
            $this->skipped['Blacklisted password']++;
            if (PARSER_DEBUG) {
              echo "      Skipping password $pass (Blacklisted password)\n";
            }

            return null;
          };

          // CSS stuff

          // Start with a quick check on obvious matches to avoid a regex on every combo
          //if (substr($user, 5) == '-moz-' || substr($user,  8) == '-webkit-') {
          if (stristr($user, '-moz-') || stristr($user, '-webkit-') || stristr($user, '-ms-')) {
            $this->skipped['Invalid data']++;
            if (PARSER_DEBUG) {
              echo "      Skipping $user (Username contains CSS)\n";
            }
            return null;
          }

          $blacklist = '(?:t(?:r(?:ee(?:header(?:sortarrow|cell)|twisty(?:open)?|item|view)|a(?:ns(?:ition|form)|d)|im)|o(?:ol(?:b(?:ar(?:button)?|ox)|tip))?|a(?:b(?:panels|le)?|rget|mil)|i(?:gr(?:inya|e)|betan)|e(?:xt(?:field)?|lugu)|hai|b)|s(?:t(?:r(?:i(?:ct|ng)|ength|ong)|a(?:rt[xy]|tusbar)|yle|em)|p(?:ac(?:es?|ing)|eek)|cr(?:oll(?:bar)?|ipt)|i(?:d(?:ama|e)|mp|ze)|e(?:parator|lf|t)|a(?:turation|me)|o(?:mali|urce)|li[cd]e|hifts?|yriac|mall|RGB)|c(?:o(?:n(?:t(?:e[nx]t|ainer)|s(?:onant|ider))|l(?:or(?:imetric)?|lapse|umns?)?|unter|py)|h(?:eck(?:box)?|a(?:nge|r)|inese)|a(?:p(?:tion|s)|mbodian)|e(?:ntral|lls?)|l(?:ear|ip)|ro(?:ss|p)|ircled|ursor|jk)|p(?:r(?:o(?:g(?:ressbar|id)|file)|e(?:se(?:ntation|rve))?)|er(?:s(?:pective|ian)|ceptual)|o(?:s(?:iti(?:on|ve))?|int)|h(?:onemes|ase)|a(?:dding|ge)|unctuation|itch)|d(?:i(?:s(?:(?:(?:reg|c)ar|able)d|pla(?:ce|y)|tribute)|a(?:mond|log)|rection|gits)|o(?:(?:cume|mina)nt|t(?:ted)?|uble|wn)|e(?:vanagari|cimal)|ash|rop)|a(?:l(?:i(?:gn(?:ment)?|as)|l(?:owed)?|phabetic)|(?:ppearanc|beged)e|r(?:menian|abic)|f(?:te|a)r|nimation|sterisks|mharic|djust|head|uto)|b(?:a(?:ck(?:ground|wards|face)|seline)|o(?:okmark|unding|rder|x)|e(?:havior|ngali|fore)|r(?:eaks?|anch)|in(?:ding|ary)|utton|lock|t)|m(?:a(?:(?:thematic|nu)al|r(?:quee|gin|k)|layalam|x)|e(?:n(?:u(?:popup|item|list)?|t)|et)|o(?:de(?:rate|l)?|ngolian|ve|z)|yanmar|in)|i(?:n(?:d(?:e(?:nt|x)|ic)|c(?:rement|lude)|(?:activ|lin)e|(?:form|iti)al|(?:ten|se)t|k)|m(?:ages?|e)|deograph)|f(?:i(?:nish(?:(?:opacit)?y|x)|l(?:l(?:ed)?|ter)|t)|o(?:r(?:ma[lt]|wards)|otnotes|nt)|loat|req)|r(?:e(?:s(?:olution|izer?|e?t)|(?:lativ|plac)e|ndering|duced|ct)|o(?:tation|ws?|le)|adio|uby|l)|e(?:n(?:(?:able)?d|grave)|(?:xclud|dg)e|a(?:rthly|ch)|m(?:boss|pty)|thiopic|pub|w)|l(?:i(?:ghtstrength|n[ek]|teral|st)|a(?:(?:you|s)t|o)|o(?:ose|wer)|e(?:vel|ft)|r)|o(?:r(?:i(?:(?:entatio|gi)n|ya)|omo)|ff(?:[xy]|set)|verflow|pacity|utline|ctal)?|n(?:e(?:w(?:spaper)?|ver|sw)|o(?:rwegian|t)?|a(?:me|v)|umeral|wse|s)|h(?:e(?:xadecimal|avenly|ight|re)|an(?:g(?:ing|ul)|d)|yphen)|v(?:(?:o(?:lum|ic)|alu)e|isibility|ertical)|w(?:r(?:iting|ap)|hite|idth|eak|ord)|g(?:u(?:jarat|rmukh)i|r(?:eek|id))|D(?:XImageTransform|ropShadow)|u(?:p(?:per)?|rdu|se)|k(?:annada|hmer|eep)|M(?:icrosoft|ask)|(?:japanes|Wav)e|(?:Chrom|Alph)a|(?:Shad|Gl)ow|z(?:oom)?|Flip[HV]|quotes|Blur|XRay|xv?)';

          if (preg_match('~' . $blacklist . '~i', $user, $matches)) {
            // only check the password regex if a username matches
            $blacklist = '^(?:above|absolute|absolute-colorimetric|ActiveBorder|ActiveCaption|adjacent|AliceBlue|all|allow-end|alternate|alternate-reverse|always|AntiqueWhite|AppWorkspace|aqua|Aquamarine|armenian|ascent|attr|auto|avoid|Azure|back|background.*|balance|baseline|behind|Beige|below|bidi-override|Bisque|black|BlanchedAlmond|blink|block|block-axis|blue|BlueViolet|bold|bolder|border-box|both|bottom|break-word|Brown|BurlyWood|Button.*|CadetBlue|cap-height|capitalize|CaptionText|center|center-left|center-right|centerline|Chartreuse|child|Chocolate|circle|close-quote|collapse|compact|condensed|contain|content-box|continuous|Coral|CornflowerBlue|Cornsilk|counter|cover|Crimson|crosshair|cubic-bezier|current|cursive|Cyan|DarkBlue|DarkCyan|DarkGoldenrod|DarkGray|DarkGreen|DarkKhaki|DarkMagenta|DarkOliveGreen|DarkOrange|DarkOrchid|DarkRed|DarkSalmon|DarkSeaGreen|DarkSlateBlue|DarkSlateGray|DarkTurquoise|DarkViolet|dashed|decimal|decimal-leading-zero|DeepPink|DeepSkyBlue|default|definition-src|descent|digits|DimGray|disc|distribute|DodgerBlue|dotted|double|e-resize|ease|ease-in|ease-in-out|ease-out|ellipsis|embed|end|expanded|extra-condensed|extra-expanded|fantasy|far-left|far-right|fast|faster|female|field|Firebrick|fixed|flat|FloralWhite|force-end|ForestGreen|front|fuchsia|Gainsboro|georgian|GhostWhite|Gold|Goldenrod|gray|GrayText|green|GreenYellow|groove|hebrew|help|hidden|hide|high|higher|Highlight|HighlightText|hiragana|hiragana-iroha|Honeydew|horizontal|HotPink|icon|InactiveBorder|InactiveCaption|InactiveCaptionText|IndianRed|Indigo|infinite|InfoBackground|InfoText|inherit|inline|inline-axis|inline-block|inline-table|inset|inside|inter-cluster|inter-ideograph|inter-word|invert|italic|Ivory|justify|kashida|katakana|katakana-iroha|Khaki|landscape|large|larger|Lavender|LavenderBlush|LawnGreen|left|left-side|leftwards|LemonChiffon|level|LightBlue|LightCoral|LightCyan|lighter|LightGoldenrodYellow|LightGray|LightGreen|LightPink|LightSalmon|LightSeaGreen|LightSkyBlue|LightSlateGray|LightSteelBlue|LightYellow|Lime|LimeGreen|line-through|linear|Linen|list-item|local|low|lower|lower-alpha|lower-greek|lower-latin|lower-roman|lowercase|ltr|Magenta|male|marker|marker-offset|marks|Maroon|mathline|medium|MediumAquamarine|MediumBlue|MediumOrchid|MediumPurple|MediumSeaGreen|MediumSlateBlue|MediumSpringGreen|MediumTurquoise|MediumVioletRed|menu|MenuText|message-box|middle|MidnightBlue|MintCream|MistyRose|mix|Moccasin|modal|monospace|move|multiple|n-resize|narrower|NavajoWhite|Navy|ne-resize|new|no-close-quote|no-content|no-display|no-open-quote|no-repeat|none|normal|nowrap|nw-resize|oblique|old|OldLace|Olive|OliveDrab|once|open-quote|Orange|OrangeRed|Orchid|outset|outside|overline|padding-box|PaleGoldenrod|PaleGreen|PaleTurquoise|PaleVioletRed|panose-1|PapayaWhip|parent|paused|PeachPuff|Peru|Pink|Plum|pointer|portrait|PowderBlue|pre-line|pre-wrap|preserve-3d|progress|Purple|rect|Red|relative|repeat|repeat-x|repeat-y|reverse|rgb|ridge|right|right-side|rightwards|root|RosyBrown|round|RoyalBlue|rtl|run-in|running|s-resize|SaddleBrown|Salmon|SandyBrown|sans-serif|scroll|Scrollbar|se-resize|SeaGreen|SeaShell|semi-condensed|semi-expanded|separate|serif|show|Sienna|silent|Silver|single|size|SkyBlue|SlateBlue|SlateGray|slope|slow|slower|small-caps|small-caption|smaller|Snow|soft|solid|spell-out|SpringGreen|square|src|start|static|status-bar|SteelBlue|stemh|stemv|stretch|super|suppress|sw-resize|tab|table-caption|table-cell|table-column|table-column-group|table-footer-group|table-header-group|table-row|table-row-group|Tan|Teal|text|text-bottom|text-top|thick|thin|Thistle|ThreeDDarkShadow|ThreeDFace|ThreeDHighlight|ThreeDLightShadow|ThreeDShadow|Tomato|top|topline|transparent|trim|Turquoise|ultra-condensed|ultra-expanded|underline|unicode-range|units-per-em|unrestricted|upper-.*|url|vertical|Violet|visible|w-resize|wait|Wheat|White|WhiteSmoke|wider|Window.*|x-.*|xx-.*|Yellow.*|-webkit.*)$';
            if (preg_match('~' . $blacklist . '~i', $pass, $matches)) {
              $this->skipped['Invalid data']++;
              if (PARSER_DEBUG) {
                echo "      Skipping password $pass (Blacklisted password)\n";
              }
              return null;
            }
          }
        }

        // Below are specific cases found that are false positives or bad matches by the parser

        // Specific case: Code:10846
        if (preg_match('/code|pin^/i', $user) && is_numeric($pass)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Code)\n";
          }

          return null;
        }
        // Specific case: l:drooga:super150
        if (substr($user, 0, 2) == 'l:') {
          $user = substr($user, 2, strlen($user) - 2);
        }

        /* Catch stuff like this:
           Found:password=mikeData
           Found:password=mikeTurning
           password=sunjava:name=Daniel
        */
        if (preg_match('/[a-zA-Z]*=/m', $user) || preg_match('/[a-zA-Z]*=/m', $pass)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (equals sign)\n";
          }

          return null;
        }


        // Specific case: ???:???
        if (preg_match('/\?+/', $user) || preg_match('/\?\?+/', $user)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Question marks)\n";
          }

          return null;
        }

        // Specific case: ______________________ or ------------------------
        if (preg_match('/([^a-zA-Z0-9])\1{7,}/', $user) || preg_match('/([^a-zA-Z0-9])\1{7,}/', $pass)) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Repeated symbol)\n";
          }

          return null;
        }

        // Specific case: ------USERNAME:PASSWORDS
        if (stristr($user, 'username') && stristr($pass, 'password')) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Invalid)\n";
          }

          return null;
        }

        // Specific case: sabre13;mustang	55nude55;8363eddy or sabre13:mustang	55nude55:8363eddy
        if ((strstr($user, ';') && strstr($pass, ';'))
         || (strstr($user, ':') && strstr($pass, ':')))
        {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Invalid)\n";
          }

          return null;
        }


        // Bad parse results
        // username:password password etc.

        if ((strstr($user, ':') || strstr($user, ',')) && strlen($user) > 20) {
          $this->skipped['Invalid data']++;
          if (PARSER_DEBUG) {
            echo "      Skipping combo $user:$pass (Bad parsing)\n";
          }

          return null;
        }




          /////// Saving stuff

        // Save e-mails
        if ($this->saveEmails) {
          if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,6}\b/i', $user, $email)) {
            $this->emails[] = $email;
          }
        }

        $return['user'] = $user;
        $return['pass'] = $pass;

        return $return;
      }


      /**
       * A long, over-thought method to determine if data considered random.
       * Uses a scoring system with a number of tests to make a pretty good guess.
       * Didn't get accurate enough results with standard algorithms.
       * This basically tries to determine if a password was computer-generated
       * or thought up by a human.
       *
       * @param $data
       *
       * @return bool|float|int|string
       */
      function checkEntropy($data)
      {
        $score = 0;

        $len = strlen($data);

        if ($len > 0) {

          // remove spaces
          $data = str_replace(" ", "", $data);


          // Compression test
          $compression = ((double)strlen(gzcompress($data)) - 9) / $len;
          if ($compression >= .80) {
            $score += .25;
          }
          if ($compression >= .85) {
            $score += .75;
          }
          if ($compression >= .90) {
            $score += .5;
          }
          if ($compression >= .95) {
            $score += .5;
          }
          if ($compression <= .50) {
            $score -= 1;
          }


          // Number of character sets
          $lower = preg_match_all('/[a-z]/', $data, $matches);
          $upper = preg_match_all('/[A-Z]/', $data, $matches);
          $num = preg_match_all('/[0-9]/', $data, $matches);
          $special = preg_match_all('/[^a-zA-Z0-9]/', $data, $matches);
          $charsets = ($lower >= 1) + ($upper >= 1) + ($num >= 1) + ($special >= 1);
          $score += ($charsets / 6);


          // Sequences from same charset
          $lower_seq = preg_match_all('/[a-z]{3}/', $data, $matches);
          $upper_seq = preg_match_all('/[A-Z]{3}/', $data, $matches);
          $num_seq = preg_match_all('/[0-9]{3}/', $data, $matches);
          $special_seq = preg_match_all('/[^a-zA-Z0-9]{3}/', $data, $matches);
          $total_seq = $lower_seq + $upper_seq + $num_seq + $special_seq;
          $score -= .25 * (int)$total_seq;
          if ($total_seq == 0) {
            $score += .25;
          }


          // Small bonus for uppercase in the middle of word
          $middleupper = preg_match_all('/[a-z0-9A-Z]{1,}[A-Z]/', $data, $matches);
          if ($middleupper) {
            $score .= .25;
          }


          // Four or more lowercase
          $lower_seq = preg_match_all('/[a-z]{4}/', $data, $matches);
          $score -= .25 * (int)$lower_seq;


          // But all numeric and hex and all uppercase sequences can still be random
          if (is_numeric($data)) {
            $score += .25 + (strlen($data) / 10);
          }
          if (preg_match('/^[abcdef01234567890]+$/i', $data, $matches)) {
            $score += 1;
          }
          if (preg_match('/^[A-Z]+$/i', $data, $matches)) {
            $score += .25;
          }


          // Single repeated characters
          $repeated = preg_match_all('/(.)\1/', $data, $matches);
          $score -= .5 * (int)$repeated;


          // Single character repeated 3 times penalty
          $repeated = preg_match_all('/(.)\1\1/', $data, $matches);
          if ($total_seq <> 0) {
            $score -= 1.5 * $repeated;
          }


          // Trigraphs and common digraphs in the English language
          $trigraphs = preg_match_all(
            '/the|and|tha|ent|ion|tio|for|nde|has|nce|tis|oft|men|wor|row|qwe|123|234|345|456|567|678|789|890|abc|def|rty|asd|zxc|jkl|uio|321/',
            $data,
            $matches
          );
          $score -= ($trigraphs * 1.25);

          $digraphs = preg_match_all(
            '/th|he|an|in|er|on|re|ed|nd|ha|at|en|es|of|nt|ea|ti|to|io|le|is|ou|ar|as|ss|ee|tt|ff|ll|mm|oo|ng|99|00/',
            $data,
            $matches
          );
          $score -= ($digraphs * .25);


          if ($trigraphs + $digraphs + $repeated == 0) {
            $score += .5;
          }

          // Consonant, vowel, consonant
          $cvc = preg_match_all('/[bcdfghjklmnpqrstvwxy][aeiouy][bcdfghjklmnpqrstvwxy]/', $data, $matches);
          $score -= .25 * (int)$cvc;

          // consecutive character sets
          //preg_match_all('/(?:[a-z]){4,}(?:[0-9]){4,}|((?:[0-9]){4,}(?:[a-z]){4,})/', $data, $matches);
          //$score -= 1.5;


          // Vowels vs consonants
          $v = preg_match_all('/[aeiouy]/i', $data, $matches);
          $c = preg_match_all('/[bcdfghjklmnpqrstvwxy]/i', $data, $matches);
          if ($c == 0) {
            $vc_ratio = 1;
          } else {
            $vc_ratio = $v / $c;
          }
          if ((double)$vc_ratio < .5) {
            $score += .75;
          }


          if ($score >= 1.9) {
            $score = 1.9;
          }
          if ($score < 0) {
            $score = 0;
          }

          $score = round($score / 1.9, 2);

          return $score;
        }

        return true;
      }

      public function showPatterns()
      {
        if (empty($this->patterns)) {
          $this->loadPatterns();
        }
        $pattern_count = count($this->patterns);
        for ($i = 1; $i < $pattern_count; $i++) {
          echo 'Pattern ' . $i . ":\n" . $this->patterns[$i] . "\n\n";
        }

      }

      private function loadPatterns()
      {
        if (count($this->patterns)) {
          return 0;
        }

        require('patterns.php');
        return true;
      }


      public static function version()
      {
        return PARSER_VERSION;
      }

      public static function mStrLen($string)
      {
        if (function_exists('mb_strlen')) {
          return mb_strlen($string);
        } else {
          return strlen($string);
        }
      }

      static function pcreErrorText($errorCode)
      {
        static $errorText;

        if (!isset($errtxt)) {
          $errorText = array();
          $constants = get_defined_constants(true);
          foreach ($constants['pcre'] as $c => $n) {
            if (preg_match('/_ERROR$/', $c)) {
              $errorText[$n] = $c;
            }
          }
        }

        return array_key_exists($errorCode, $errorText) ? $errorText[$errorCode] : null;
      }
    }
  }