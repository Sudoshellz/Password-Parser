<?php
  /***
   * nununununununununununununununununununununununununununu
   *        __                 __
   *       / /_  __  __  ____ / /
   *      / __ )/ / / // ___// /
   *     / // // /_/ // /   / /
   *    /_//_/(_____//_/   /__)
   *
   *    Command-line combos parser
   *
   * nununununununununununununununununununununununununununu
   */


  define('HURL_VER', '0.9');
  define('AUTHOR', 'Mark Burnett (mb@xato.net)');
  define('BUFFER_SIZE', 3172000); // Buffer read size in bytes  1048576 2097152

  define('H_DEBUG', true);
  ini_set('display_errors', H_DEBUG); // For debugging and testing only!
  ini_set('log_errors', H_DEBUG);
  ini_set('error_log', dirname(__FILE__) . '/_errorlog.txt');
  error_reporting(E_ALL);

  ini_set('memory_limit', "-1");
  ini_set('pcre.backtrack_limit', 1000000);
  ini_set('pcre.recursion_limit', 1000000);
  set_time_limit(0);

  if (file_exists('parser.php')) {
    include(__DIR__ . '/parser.php');
  } else {
    include(__DIR__ . '/../parser.php');
  }

  // Set up short options
  $s = 'o::'; // Output file (defaults to <filename>-parsed.txt)
  $s .= 'e::'; // Do entropy check  (default: false)
  $s .= 'b::'; // Do blacklist checking (default: true)
  $s .= 'v'; // Verbose output
  $s .= 'q'; // No output
  $s .= 't:'; // Output template (default: %username%:%password%)
  $s .= 'p:'; // Regex pattern to match against (default: all built-in patterns)
  $s .= 'r'; // If -i is a directory, recurse all subdirectories
  // todo: -a append to output file
  // todo: save rejected combos to file xxxxxx.txt
  // todo: continue on errors
  // todo: sanitize emails (remove domain, leave username only)
  // todo: output to stdout
  // todo: debug

  // Long options
  $l = array();
  $l[] = 'showpatterns';
  $l[] = 'debug';

  $append = 0;

  $options = getopt($s, $l);
  unset($s, $l);

  // Set default values for all options
  $verbose = false;
  $quiet = false;
  $entropyCheck = false;
  $blacklistCheck = true;
  $template = "%username%\t%password%";
  $files = array();

  if ($_SERVER['argc'] == 1) {
    showhelp();
  }

  // Parse out files/directories at end of command line
  for ($i = 1; $i <= $_SERVER['argc'] - count($options) - 1; $i++) {
    $files[] = $_SERVER['argv'][$i];
  }


  // Parse options
  if (array_key_exists('showpatterns', $options)) {
    $parser = new comboParser;
    $parser->showPatterns();
    exit;
  }

  // Verbosity
  $verbose = array_key_exists('v', $options);
  $quiet = array_key_exists('q', $options);

  // Debug
  if (array_key_exists('debug', $options)) {
    $debug = true;
  } else {
    $debug = H_DEBUG;
  }

  // Entropy Check
  if (array_key_exists('e', $options)) {
    $entropyCheck = isTrue($options['e']);
  }

  // Blacklist
  if (array_key_exists('b', $options)) {
    $blacklistCheck = isTrue($options['b']);
  }

  // Template
  if (array_key_exists('t', $options)) {
    $template = $options['t'];
  }

  // Patterns
  if (array_key_exists('p', $options)) {
    $patterns = (array)$options['p'];
  }

  // Output file(s)
  if (array_key_exists('o', $options)) {
    $output = $options['o'];
  } else {
    $output = '*-parsed.txt';
  }

  $recurse = array_key_exists('r', $options);


  if (!$quiet or $debug) {
    echo "\n\n\n---------------------------[ hurl ]----\n\n";
  }


  // Assemble a list of files to parse

  if (!isset($files)) {
    die('Input files not specified.');
  } else {
    foreach ((array)$files as $input) {

      // Check for '.'
      if ($input == '.') {
        $input = '*.*';
      }

      // Check for file mask patterns
      if (strpbrk($input, '*?')) {
        $globbed = glob($input);
        foreach ($globbed as $filename) {
          if (!is_dir($filename)) {
            $files[] = $filename;
          }
        }
      }

      // Single files
      if (is_file($input)) {
        $files[] = $input;
      }

      // Directories
      if (is_dir($input)) {
        if (isset($options['r'])) {
          $globbed = globRecursive($input, '/*.*');
          foreach ($globbed as $filename) {
            if (!is_dir($filename)) {
              $files[] = $filename;
            }
          }

        } else {
          $globbed = glob($input . '/*.*');
          foreach ($globbed as $filename) {
            if (!is_dir($filename)) {
              $files[] = $filename;
            }
          }
        }
      }
    }
  }

  // cleanup and sort
  unset($options, $globbed, $filename, $input, $recurse);
  $files = array_unique($files);
  sort($files);


  if (!$quiet || $debug) {
    echo "Processing " . count($files) . " file(s)...\n\n";
  }

  if (count($files) == 0) {
    die('Input files not specified or not found.');
  }

  // Loop through each file in the list
  foreach ($files as $file) {
    if (!file_exists($file)) {
      die('Input file ' . $file . ' not found.');
    }

    //initialize variables
    $size = filesize($file);
    $buffer = null;
    $combos = array();
    $timer = 0;

    if ($size > BUFFER_SIZE) {
      if ($debug) {
        echo " Splitting file...\n";
      }
      $tmpFiles = splitFile($file, BUFFER_SIZE);
      rsort($tmpFiles);
    } else {
      $tmpFiles = array($file);
    }



    foreach ($tmpFiles as $tmpFile) {
      if (is_file($tmpFile)) {

        // Initialize parser
        $parser = new comboParser;
        $parser->doEntropyCheck = $entropyCheck;
        $parser->useBlacklist = $blacklistCheck;
        $parser->template = $template;
        if (!empty($patterns)) {
          $parser->patterns = $patterns;
        }

        // Read file into the buffer
        $buffer = file_get_contents($tmpFile);
        if ($buffer == false) {
          echo 'Failed opening file ' . $tmpFile;
          break;
        }

        if ($debug) {
          echo "\n Parsing $tmpFile (" . filesize($tmpFile) . " bytes)...\n";
        }

        // Parse out the combos
        $parser->parse($buffer);
        $timer = $parser->timeTaken;
        unset($buffer);

        if ($verbose || $debug) {
          echo "\n   - Found " . count($parser->combos) . " combos in $timer seconds\n";
        }

        // Accumulate totals
        if (count($parser->combos)) {
          //$result['time'] .= $parser->timeTaken;
          // $result['count'] .= count($parser->combos);
          // if (!empty($parser->skipped)) {
          //   $result['skipped'] = $parser->skipped;
          // }
          //$results[] = $result;

          $combos = array_merge((array)$combos, $parser->combos);


          /*        if ($debug) {
                    echo "\nPattern Stats\n----------------\n";
                    if (!empty($parser->stats['patterns'])) {
                      foreach ($parser->stats['patterns'] as $key => $value) {
                        echo "Pattern $key: " . $value['combos'] . " combos  " . number_format($value['time'], 2) . " seconds\n";
                      }
                    }
                  }*/


          // When dealing with files in chunks we have to unique and sort them again
          if (count($tmpFiles) > 1) {
            $combos = array_unique((array)$combos, SORT_STRING);
            sort($combos);
          }


          // Write to file if writing to individual files
          if (strstr($output, '*')) {
            $outFile = str_replace('*', $file, $output);
            if ($debug) {
              echo "\n   Saving to $outFile\n";
            }
            file_put_contents($outFile, $combos, $append);
          } else {
            // We will always append when reading from multiple files
            if ($debug) {
              echo "\n   Saving to $output\n";
            }
            file_put_contents($output, $combos, FILE_APPEND);
          }
        }
        // Remove temporary files
        if (strstr($tmpFile, '~.')) {
          unlink($tmpFile);
        }
      }
    }
  } // foreach($files as $file)


  if ($debug) {
    // echo "\nPattern Stats\n----------------\n";
    //  foreach ($parser->stats['patterns'] as $key => $value) {
    //    echo "Pattern $key: " . $value['combos'] . " combos  " . number_format($value['time']), 2) . " seconds\n";
    //  }
  }


  if (!$quiet) {
    echo "\nProcessing complete.\n";
  }

  /**
   * Checks various conditions for the equivalent to true
   *
   * @param  string $value
   *
   * @return boolean
   */

  function isTrue($value)
  {
    if (is_numeric($value)) {
      return (bool)$value;
    }

    $val = strtolower(trim($value));
    if ($val == "true" || $val == "yes" || $val == "on" || $val == 'enabled' || $val == 'enable' || $val == 'selected' || $val == 'active') {
      return true;
    } else {
      // Anything else is false
      return false;
    }
  }


  /**
   * Recursive version of glob
   *
   * @return array containing all pattern-matched files.
   *
   * @param string $dir     Directory to start with.
   * @param string $pattern Pattern to glob for.
   * @param int $flags      Flags sent to glob.
   */
  function globRecursive($dir, $pattern, $flags = null)
  {
    $dir = escapeshellcmd($dir);

    // Matching files in current directory
    $files = glob("$dir/$pattern", $flags);

    // Gat a list of all subdirectories
    foreach (glob("$dir/*", GLOB_ONLYDIR) as $subDir) {
      $subFiles = globRecursive($subDir, $pattern, $flags);
      $files = array_merge($files, $subFiles);
    }

    // Return array of all files found
    return $files;
  }

  function showHelp()
  {

    echo "Hurl Combos Parser\n\n";
    echo "Usage:\n hurlc.php <file>|<dirctory>]\n hurlc.php [options]";


    $s = 'i:'; // Input file, file pattern, files, or directory (Required)
    $s .= 'o::'; // Output file (defaults to <filename>-combos.txt)
    $s .= 'e::'; // Do entropy check  (default: false)
    $s .= 'b::'; // Do blacklist checking (default: true)
    $s .= 'v'; // Verbose output
    $s .= 'q'; // No output
    $s .= 't:'; // Output template (default: %username%:%password%)
    $s .= 'p:'; // Regex pattern to match against (default: all built-in patterns)
    $s .= 'r'; // If -i is a directory, recurse all subdirectories

    // todo: finish this

    die();
  }


  function splitFile($file, $partSize)
  {
    $filenames = array();
    $prepend = null;
    $hFile = fopen($file, 'rb');
    $fileSize = filesize($file);
    $numParts = ceil($fileSize / $partSize);
    $modulus = $fileSize % $numParts;

    // Load file and break into parts

    for ($i = 0; $i < $numParts; $i++) {
      if ($modulus != 0 & $i == $numParts - 1) {
        // Last part
        $part = fread($hFile, $partSize + $modulus);
      } else {
        $part = fread($hFile, $partSize);

        // Trim off last 2 lines to prepend to next part
        $overlap = strrchr($part, "\n"); // Last line

        $part = "\n" . $prepend . substr($part, 0, strrpos($part, "\n"));
        $overlap = strrchr($part, "\n") . $overlap;  // second to last line
        $prepend = $overlap;
      }

      $filename = $file . '~.' . $i;
      // echo "Temp file: $filename\n";
      $hOut = fopen($filename, 'wb');
      fwrite($hOut, $part);
      fclose($hOut);
      $filenames[] = $filename;
    }

    fclose($hFile);
    return $filenames;
  }

