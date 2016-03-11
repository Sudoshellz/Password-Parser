<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <title>Hurl - Username/Passwords combos parser</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="description" content="">
   <meta name="author" content="">

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
         <a class="brand" href="index.php">Hurl</a>

         <div class="nav-collapse collapse">
            <ul class="nav">
               <li class="active"><a href="index.php">Home</a></li>
               <li><a href="#">About</a></li>
            </ul>
         </div>
         <!--/.nav-collapse -->
      </div>
   </div>
</div>

<div class="container">

   <p>
      This tool extracts unique username/password combos from raw data that you provide via uploading a text file,
      providing a URL, or pasting into the text box.
      This tool will identify, extract, clean, and then sort any combos found in the data.


   <h3>Combo Parsing</h3>

   <p>This


      tool will identify usernames and passwords in many different formats,
      including the following:

   <p><code style="font-size:.8em;">username:password<br/>
         username : password<br/>
         username - password<br/>
         http://username:password@www.example.com<br/>
         http://www.example.com/members/ L:username P:password<br/>
         http://www.example.com/members login:username password:password<br/>
         http://www.example.com/members user: username pass: password<br/>
         Login: username passw:password<br/>
         L:username P:password<br/>
         username:username password:password<br/>
         Name: username Password: password<br/>
         http://www.example.com/members L: username P: password<br/>
         username = username password= password<br/>
         u=username p=password<br/>
         Username username Password password<br/>
         login id: username password: password<br/><br/>

         Login: username<br/>
         Password: password<br/><br/>

         Email :username@gmail.com<br/>
         Password :password<br/><br/>

         name: = &quot;username&quot;;<br/>
         password: = &quot;password&quot;;<br/><br/>

         email@address.com password<br/>
         email@address.com | password<br/>
         email@address.com - password<br/><br/>

         Domain.name user password<br/>
         domain.name:user:password<br/><br/>

         DSN=myDsn;Uid=myUsername;Pwd=;<br/>
         Provider=MSDAORA.1;Password=password;User ID=username;Data Source=data;<br/><br/>

         DEFINE ('DB_USER', 'username');<br/>
         DEFINE ('DB_PASSWORD', 'password');<br/></code>
   </p>


   <h3>Limitations</h3>

   <p>Although this tool is able to recognize data in many different formats, it relies on certain clues to identify
      usernames and passwords. For that reason, this tool will not work well on raw tables with multiple columns of
      data, SQL dumps, CSV files, and other formats where the username and password data are ambigious. In the future,
      this tool will allow you to specify the formatting with multi-columnar data. The best workaround for this type of
      data is to import it into a spreadsheet and then extract the two columns that contain the usernames and
      passwords. </p>

   <p>Normally this tool will expect the username to appear before the password for any particular pattern. If the raw
      data is not in this format, use a spreadsheet or a text editor to change the formatting. </p>

   <h3>Cleanup</h3>

   <p>By default the parser will perfrom various cleanup steps to insure the validity of the data and to compensate for
      parsing issues. These cleanup steps are:</p>

   <p> * Remove usernames less than 3 or longer than 60 characters/<br/>
      * Remove passwords less than 3 and longer than 40 characters/<br/>
      * Remove the combos of well-known hackers (such as zima, passfan, pr0t3st, etc.)<br/>
      * Remove combos that look like parsing errors.<br/>
      * Remove combos where both username and password are high entropy and therefore not likely human generated. <br/>
      * Remove passwords that are encrypted hashes</p>

   <h3>Parameters</h3>
   <p>Hurl accepts the following parameters on the query string to allow for customization and automation:</p>
   <p><strong>format=plain</strong>  Outputs plain text data instead of formatted HTML.<br />
      <strong>url=&lt;url&gt;</strong>  Specifies the URL to parse<br />
      <strong>data=&lt;data&gt;</strong>                                        Specifies the raw data to parse (must be urlencoded)<br />
      <strong>useblacklist=0</strong>  Instructs the parser to not perform blacklist checks for faster parsing<br />

   <h3>Bookmarklets </h3>
   <p>To create a bookmarklet to use on any web page, drag the following bookarklet
      to your browser's toolbar:

   <p> <a href="javascript:location.href='http://passwords.io/hurl/?url='+encodeURIComponent(location.href)"
          style="border: 1px dotted #666;padding: 3px;color: #306;">Parse This Page</a>
   <p>In some cases you may want to parse data that is not located on a public page (such as in a forum that requires login). In this case, you can use the advanced parser bookmarklet. When you click on this bookmarklet a red box will appear on the screen that you can use to select the data to parse. When you click on the selected area, this data will be sent to the parser.
   <p><a href="javascript:(function(e,a,g,h,f,c,b,d){if(!(f=e.jQuery)||g>f.fn.jquery||h(f)){c=a.createElement("script");c.type="text/javascript";c.src="http://ajax.googleapis.com/ajax/libs/jquery/"+g+"/jquery.min.js";c.onload=c.onreadystatechange=function(){if(!b&&(!(d=this.readyState)||d=="loaded"||d=="complete")){h((f=e.jQuery).noConflict(1),b=1);f(c).remove()}};a.documentElement.childNodes[0].appendChild(c)}})(window,document,"1.4.2",function($,L){$(document).ready(function(){$("iframe,object,embed,input[type=image],ins").hide();$("div,table").live("mouseover%20mouseout%20click",function(a){a.type=="mouseover"?$(this).css({border:"1px%20solid%20red"}):$("div,table").css({border:"none"});if(a.type=="click"){fm=document.createElement("form");fm.style.display="none";fm.method="post";fm.action="http://passwords.io/hurl/";myInput=document.createElement("input");myInput.setAttribute("name","data");myInput.setAttribute("value",this.innerHTML);fm.appendChild(myInput);document.body.appendChild(fm);fm.submit();document.body.removeChild(fm)}return%20false})})});"
      style="border: 1px dotted #666;padding: 3px;color: #306;">Advanced Parser</a></p>
   <p>
   <p>&nbsp;</p>
   <p>If you have any questions or run into any problems, please contact me at mb@xato.net</p>
   <p><a href="index.php">Return to Parser</a></p>
</div>
<!-- /container -->


</body>
</html>
