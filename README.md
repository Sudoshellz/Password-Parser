Hurl is a tool that extracts unique username/password combos from raw data in a variety of formats. This tool will identify, extract, clean, and then sort any combos found in the data. 

This repository includes both a command-line parser (hurlc.php) and a web page version. You definitely should not upload the command-line version to your web site.

This code is mostly optimized for readability and accuracy at the expense of speed. Works well with small to medium-sized files, but may be a bit slow on very large files.


Combo Parsing
This tool will identify usernames and passwords in many different formats, including the following:

username:password
username : password
username - password
http://username:password@www.example.com
http://www.example.com/members/ L:username P:password
http://www.example.com/members login:username password:password
http://www.example.com/members user: username pass: password
Login: username passw:password
L:username P:password
username:username password:password
Name: username Password: password
http://www.example.com/members L: username P: password
username = username password= password
u=username p=password
Username username Password password
login id: username password: password

Login: username
Password: password

Email :username@gmail.com
Password :password

name: = "username";
password: = "password";

email@address.com password
email@address.com | password
email@address.com - password

Domain.name user password
domain.name:user:password

DSN=myDsn;Uid=myUsername;Pwd=;
Provider=MSDAORA.1;Password=password;User ID=username;Data Source=data;

DEFINE ('DB_USER', 'username');
DEFINE ('DB_PASSWORD', 'password');

Limitations
Although this tool is able to recognize data in many different formats, it relies on certain clues to identify usernames and passwords. For that reason, this tool will not work well on raw tables with multiple columns of data, SQL dumps, CSV files, and other formats where the username and password data are ambigious. In the future, this tool will allow you to specify the formatting with multi-columnar data. The best workaround for this type of data is to import it into a spreadsheet and then extract the two columns that contain the usernames and passwords.

Normally this tool will expect the username to appear before the password for any particular pattern. If the raw data is not in this format, use a spreadsheet or a text editor to change the formatting.

Cleanup
By default the parser will perfrom various cleanup steps to insure the validity of the data and to compensate for parsing issues. These cleanup steps are:

* Remove usernames less than 3 or longer than 60 characters/
* Remove passwords less than 3 and longer than 40 characters/
* Remove the combos of well-known hackers (such as zima, passfan, pr0t3st, etc.)
* Remove combos that look like parsing errors.
* Remove passwords that most likely are encrypted hashes





This file was modified by JetBrains PhpStorm 6.0.3 for binding GitHub repository