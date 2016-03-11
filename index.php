<?php
   ob_start();
   ini_set('display_errors', 0); // For debugging and testing only!
   ini_set('memory_limit', "256M");
   set_time_limit(0);
   define('COMBOS_DIR', 'combos'); // where you want combos placed, wouldn't hurt to change this


// Determine the type of data we received: a file, url, or raw data
   if (strlen($_FILES["file"]["tmp_name"])) {
      $type = 'file';
      $showForm = false;
   } elseif (strlen($_REQUEST['url'])) {
      $type = 'url';
      $url = trim($_REQUEST['url']);
      $showForm = false;
   } elseif (strlen($_REQUEST['data'])) {
      $type = 'data';
      $data = trim($_REQUEST['data']);
      $showForm = false;
   } else {
      $type = null;
      $showForm = true;
   }

// Output format
   if (strlen($_REQUEST['out'])) {
      $outputFormat = $_REQUEST['out'];
   } else {
      $outputFormat = 'form';
   }

// Output template
   if (strlen($_REQUEST['format'])) {
      $template = trim($_REQUEST['format']);
   } else {
      $template = '%username%:%password%';
   }

// Other uncommon options
//if (isset($_REQUEST['xd'])) $debug = isTrue($_REQUEST['xd']);
   if (isset($_REQUEST['checkentropy'])) {
      $checkEntropy = isTrue($_REQUEST['checkentropy']);
   } else {
      $checkEntropy = false;
   }
   if (isset($_REQUEST['useblacklist'])) $useBlacklist = isTrue($_REQUEST['useblacklist']);
   if (isset($_REQUEST['Save'])) $save = isTrue($_REQUEST['Save']);


   ?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <title>Hurl - Username/Passwords combos parser</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="description" content="">
   <meta name="author" content="">

<?php
   if ($outputFormat <> 'plain') {
      ?>
      <link href="css/bootstrap.css" rel="stylesheet">
      <style>
         body {
            padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
         }
      </style>
      <link href="css/bootstrap-responsive.css" rel="stylesheet">

      <!--[if lt IE 9]>
      <script type="text/javascript" src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
      <![endif]-->
</head>

<body>

<div class="navbar navbar-inverse navbar-fixed-top">
   <div class="navbar-inner">
      <div class="container">
         <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
         </a>
         <a class="brand" href="#">Hurl Combos Parser</a>

         <div class="nav-collapse collapse">
            <ul class="nav">
               <li class="active"><a href="#">Home</a></li>
               <li><a href="help.php">Help</a></li>
            </ul>
         </div>
         <!--/.nav-collapse -->
      </div>
   </div>
</div>

<div class="container">

   <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="showform" value="1"/>
      <br/><br/>

      <h1>Hurl</h1>

      <?php
   }
   if ($showForm) {
      ?>

      <p>&nbsp;</p>

      <p>This tool will extract username/password combos from a wide variety of formats. Using the form
         below, you can upload a file,<br/> point to a URL, or paste in your data and click on Submit.</p>

      <p>&nbsp;</p>

      <fieldset>
         <div class="control-group">
            <label for="file">Upload a file:</label>

            <div class="controls">
               <input name="file" id="file" class="span5" type="file"/>
            </div>
         </div>
         <div class="control-group">
            <label for="url">or enter a URL:</label>

            <div class="controls">
               <input name="url" id="url" class="span5" type="text"/>
            </div>
         </div>
         <div class="control-group">
            <label for="data">or paste any text here:</label>

            <div class="controls">
               <textarea name="data" id="data" class="span8" rows="10"></textarea>
            </div>
         </div>
         <br/>

        <input type="checkbox" name="Save" id="Save" />
        <label for="save">Save these results on server for research purposes</label>

         <input type="submit" class="btn btn-primary" name="Submit" id="Submit" value="Submit"/>
      </fieldset>
    </form>
</div>
<!-- /container -->

      <?php
   } // end if ($showForm)


// Get data depending on input type

   switch ($type) {

      case 'file':
         $rawdata = file_get_contents($_FILES["file"]["tmp_name"]);
         break;

      case 'url':
         $url = htmlspecialchars_decode($url);

         // Very basic URL validity and security checks
         if (!preg_match('#^(http(s)?|ftp)://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$#i', $url)) {
            die('Not a valid URL');
         }

         if (!preg_match('#^(http|ftp)://#i', $url, $matches)) {
            $url = 'http://'.$url;
         }

         // Convert pastebin URLs to rawdata
         $url = preg_replace('#http:\/\/pastebin\.com\/([a-zA-Z0-9]{6,9})#i', 'http://pastebin.com/raw.php?i=$1', $url);


         $ch = curl_init();
         @curl_setopt($ch, CURLOPT_NOBODY, false);
         @curl_setopt($ch, CURLOPT_URL, $url);
         @curl_setopt($ch, CURLOPT_VERBOSE, false);
         @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
         @curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
         @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


         if (!$result = curl_exec($ch)) {
            echo 'Error: '.curl_error($ch);
         }

         curl_close($ch);

         $result = preg_replace("#</p>|<br\s?>#", "\r\n", $result);
         $rawdata = strip_tags($result);

         break;

      case 'data':
         $data = preg_replace("#</p>|<br\s?>#", "\r\n", $data);
         $rawdata = strip_tags($data);
         break;
   }


// Begin parsing
if (strlen($rawdata)) {
   include('parser.php');
   $parser = new comboParser;
   if (isset($checkEntropy)) $parser->doEntropyCheck = $checkEntropy;
   if (isset($useBlacklist)) $parser->useBlacklist = $useBlacklist;
   if (isset($debug)) $parser->debug = $debug;
   //$parser->debug = true;
   if (isset($template)) $parser->template = $template;

   $parser->parse($rawdata);

   if (count($parser->combos)) {
      if ($save) {
         mt_srand();
         $filename = COMBOS_DIR.'/'.substr(base_convert(sha1(mt_rand().md5(mt_rand())), 16, 36), 0, mt_rand(15, 25)).'.txt';
         if (file_put_contents($filename, $parser->combos, LOCK_EX) === false) {
           echo 'Error: Save to file failed.';
         } else {
           echo 'File saved.';
         }
      }
   }


   if ($outputFormat == 'plain') {
      header("Content-Type: text/plain");
      foreach ($parser->combos as $combo) {
         echo $combo;
      }
   }

   //		if ($debug) {
   //		if (isset($parser->skipped)) {
   //			echo '<pre>';
   //			foreach ($parser->skipped as $skip) {
   //				echo $skip;
   //			}
   //			echo '</pre>';
   //		}
   //		}


   if ($outputFormat == 'form') {
      echo '<tr><td>';
      echo '<br /><br /><h3>Results</h3><p>';
      if ($type == 'file') {
         echo "Type: File upload";
      }

      if ($type == 'url') {
         echo 'Original URL: '.htmlentities($url);
      }

      echo '<br />Found '.count($parser->combos).' combos in '.$parser->timeTaken.' ms</p></td></tr>';
      echo '<div style="display:none"><div id="data"><p><pre>'.htmlentities($rawdata).'</pre></p></div></div>';
      echo '<td><textarea cols="80" rows="15">';
      foreach ($parser->combos as $combo) {
         echo $combo;
      }

      echo '</textarea></td></tr>';
      echo '<form action="index.php" method="post"><br /><input  class="btn btn-primary" type="submit" name="Submit" id="Submit" value="Submit Another" /></form>';


   } // end if ($outputFormat = 'form')

} else {
   if (!is_null($type)) echo 'Nothing found.';
}
ob_flush();

if ($outputFormat <> 'plain') {
   ?>


   <!-- Placed at the end of the document so the pages load faster -->
   <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js" type="text/javascript"></script>
   <script type="text/javascript" src="js/bootstrap.min.js"></script>
   <script type="text/javascript" src="js/bootstrap-filestyle.js"></script>

   <script type="text/javascript">
      $("#file").filestyle()
   </script>

   </body>
</html>
<?php
   }

   /**
    * Checks various conditions for the equivalent to true
    *
    * @param  string $value
    *
    * @return boolean
    */
   function isTrue($value) {
      $val = strtolower($value);
      if ((is_numeric($val) && (int)$val <> 0) || $val == "true" || $val == "yes" || $val == "on") {
         return true;
      } else {
         return false;
      }
   }


