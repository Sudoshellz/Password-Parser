<?php
/**
 * Tests
 */


require_once('simpletest/autorun.php');
require_once('../parser.php');



//$t = new parseTester;
//$t->testPattern1();

class AllTests extends TestSuite {
   function AllTests() {
      $this->TestSuite('All tests');
   }
}


class parseTester extends UnitTestCase {

   function testPattern1() {
      $this->assertEqual('user01-01:pass01-01', getCombo('user01-01:pass01-01', 1));
      $this->assertEqual('user01-02@user1.com:password01-02', getCombo('user01-02@user1.com:password01-02', 1));
      $this->assertEqual('user01-03:pass01-03', getCombo('user01-03 : pass01-03', 1));
      $this->assertEqual('user01-05:pass01-05', getCombo('user01-05	:	pass01-05', 1));
      $this->assertEqual('user01-07:pass01-07', getCombo('1. user01-07 - pass01-07', 1));
      $this->assertEqual('user01-08:pass01-08', getCombo('"user01-08" "pass01-08"', 1));
   }


   function testPattern2() {
      $this->assertEqual('user02-01:pass02-01', getCombo('user02-01               pass02-01', 2));
      $this->assertEqual('user02-02@user2.com:password02-02', getCombo('user02-02@user2.com                   password02-02', 2));
      $this->assertEqual('user02-03:pass02-03', getCombo('   user02-03        pass02-03', 2));
      // 02-04
      $this->assertEqual('user02-05:pass02-05', getCombo("\tuser02-05\tpass02-05", 2));
   }


   function testPattern3() {
      $this->assertEqual('user03-01:pass03-01', getCombo('http://user03-01:pass03-01@www.example.com', 3));
      $this->assertEqual('user03-02:pass03-02', getCombo('http://user03-02:pass03-02@www.example.com/members', 3));
      $this->assertEqual('user03-03:pass03-03', getCombo('http://user03-03:pass03-03@www.example.com/member.php', 3));
   }

   function testPattern4() {
      $this->assertEqual('user04-01:pass04-01', getCombo('http://www.example.com L:user04-01 P:pass04-01', 4));
      $this->assertEqual('user04-02:pass04-02', getCombo('http://www.example.com Login:user04-02 Password:pass04-02', 4));
      $this->assertEqual('user04-03:pass04-03', getCombo('http://www.example.com user:  user04-03 pass:  pass04-03', 4));
      $this->assertEqual('user04-04:pass04-04', getCombo('http://www.example.com/ L: user04-04 P:    pass04-04', 4));

      $this->assertEqual('user04-05:pass04-05', getCombo('Login: user04-05 Passw: pass04-05', 4));
      $this->assertEqual('user04-06:pass04-06', getCombo('  L: user04-06     P: pass04-06', 4));
      $this->assertEqual('user04-07:pass04-07', getCombo('Username user04-07 Password pass04-07', 4));
      $this->assertEqual('user04-08:pass04-08', getCombo('Name= user04-08 Pass = pass04-08', 4));
      $this->assertEqual('user04-09:pass04-09', getCombo('u=user04-09 p=pass04-09', 4));
      $this->assertEqual('user04-10:pass04-10', getCombo('Login id: user04-10 Password: pass04-10', 4));
      $this->assertEqual('user04-11:pass04-11', getCombo("Email: user04-11 \r\nPassword: pass04-11", 4));
      $this->assertEqual('user04-12:pass04-12', getCombo(" Name: \"user04-12\" \r\n Password: \"pass04-12\"", 4));
      $this->assertEqual('user04-13:pass04-13', getCombo("   U: user04-13 \r\n    P: pass04-13", 4));
      $this->assertEqual('user04-14:pass04-14', getCombo("Your username is user04-14 \r\nYour password is pass04-14", 4));
   }


   function testPattern5() {
      $this->assertEqual('user05-01:pass05-01', getCombo('pass05-01 user05-01@example.com', 5));
      $this->assertEqual('user05-02:pass05-02', getCombo("pass05-02\t|\tuser05-02@example.com", 5));
   }


   function testPattern6() {
      $this->assertEqual('user06-01:pass06-01', getCombo('DSN=myDsn;Uid=user06-01;Pwd=pass06-01;', 6));
      $this->assertEqual('user06-02:pass06-02', getCombo('Provider=MSDAORA.1;User ID=user06-02;Password=pass06-02;Data Source=localhost;', 6));
   }


   function testPattern7() {
      $this->assertEqual('user07-01:pass07-01', getCombo('DSN=myDsn;Pwd=pass07-01;Uid=user07-01;', 7));
      $this->assertEqual('user07-02:pass07-02', getCombo('Provider=MSDAORA.1;Password=pass07-02;User ID=user07-02;Data Source=localhost;', 7));
   }


   function testPattern8() {
      $this->assertEqual('user08-01:pass08-01', getCombo("DEFINE ('DB_USER', 'user08-01'); \r\nDEFINE ('DB_PASSWORD', 'pass08-01');", 8));
   }

   
   function testPattern9() {
      $this->assertEqual('user09-01:pass09-01', getCombo("\$db_user = \"user09-01\";\r\n\$db_pass = \"pass09-01\";", 9));
      $this->assertEqual('user09-02:pass09-02', getCombo("\$this->db_username='user09-02';\r\n\$this->pass = 'pass09-02';", 9));
   }

   function testPattern11() {
      $this->assertEqual('user11-01:pass11-01', getCombo('mysql_connect ("localhost", "user11-01", "pass11-01")', 11));
      $this->assertEqual('user11-02:pass11-02', getCombo("mysqli_connect ('localhost', 'user11-02', 'pass11-02')", 11));
      $this->assertEqual('user11-03:pass11-03', getCombo('$link = mysqli_real_connect("localhost","user11-03","pass11-03")', 11));
      $this->assertEqual('user11-04:pass11-04', getCombo("\$db = new PDO('mysql:host=myhost;dbname=mydb', 'user11-04', 'pass11-04');", 11));
      $this->assertEqual('user11-05:pass11-05', getCombo("\$db->connect('localhost', 'user11-05', 'pass11-05')", 11));
   }

}

function getCombo($data, $pattern_id) {
//static $parser;
   //if (!isset($parser)) $parser = new comboParser;

   $parser = new comboParser;
   $parser->parse($data, $pattern_id);
   return trim($parser->combos[0]);
}

