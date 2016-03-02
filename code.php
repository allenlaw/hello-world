
<?php

function cEmailValid( $email )
{
  return preg_match( '/^\w[_\-\.\w]+@\w+\.([_-\w]+\.)*\w{2,4}$/', $email );
}

function cMobiValid( $mobiNum )
{
  return preg_match( '/^\+?\d{11}$/', $mobiNum );
}

class my_http
{ // 封装了 pear 的 HTTP_Request 类。
 public static function g4XYVreq( $url, $boolUnserial = false )
 {
  $req = & new HTTP_Request( $url );
  if (PEAR::isError( $req->sendRequest() )){
    $data = false;
  } else {  
    $data = $req->getResponseBody();
    if ($boolUnserial) {
	  $data = unserialize( $data );
	}	
  }
  return $data;
 }

 public static function p2XYVreq( $url, $arrData, $boolUnserial = false )
 {
  $req = & new HTTP_Request( $url );
  $req->setMethod( HTTP_REQUEST_METHOD_POST );
  foreach ($arrData as $key => $val) {
	$req->addPostData( $key, $val );
  }  
  if (PEAR::isError( $req->sendRequest() )){
    $data = false;
  } else {  
    $data = $req->getResponseBody();
    if ($boolUnserial) {
	  $data = unserialize( $data );
	}	
  }
  return $data;
 }

}

class app_mod_Usr
{
  private $dbh;

  public function __construct()
  {
  	$this->dbh = cfg_DBA::gWap(); // 建立数据库链接
  }

  public static function vInfo( $usr )
  {
    $rs = array();
	
     $slen = mb_strlen( $usr['loginName'], 'UTF-8' );
	 if ($slen == 0) {
	   $rs[] = '帐号必填';
	 } else if ($slen < 5 || $slen > 20) {
       $rs[] = '帐号长度只能为5~20位';
	 } else if (! app_ctrl_u::cUsrValid( $usr['loginName'] )) {
	   $rs[] = '帐号中含有非法字符';
	 }

    $slen = mb_strlen( $usr['password'], 'UTF-8' );
	if ($slen == 0) {
	  $rs[] = '密码必填';
	} else if ($slen >20 || $slen <6) {
	  $rs[] = '密码长度只能为6~20位';
	} else if (! app_ctrl_u::cPwdValid( $usr['password'] )){
	  $rs[] = '密码格式非法（只能是字母数字）';
	}

	if ($usr['password2'] == '') {
	  $rs[] = '确认密码项必填';
	} else if ($usr['password2'] != $usr['password']) {
	  $rs[] = '两次输入的密码不同！';
	}

	if ($usr['realname'] !== '') {
	  $slen = mb_strlen( $usr['realname'], 'UTF-8' );
	  if ($slen > 40) {
		$rs[] = ' 昵称长度不能超过40位（20个汉字）。';
	  } else if (! app_ctrl_u::cUsrValid( $usr['realname'] )) {
		$rs[] = '昵称中含有非法字符';
	  }
	}

    $slen = mb_strlen( $usr['mobiNum'], 'UTF-8' );
	if ($slen == 0) {
	  $rs[] = '手机号码必填';
	} else if ($slen > 12 || $slen < 11) {
      $rs[] = '手机号码长度为11~12位';
	} else if (! cMobiValid( $usr['mobiNum'] )) {
	  $rs[] = '手机号码只能是数字或+号';	
	}

    $slen = mb_strlen( $usr['email'], 'UTF-8' );
	if ($slen == 0) {
	  $rs[] = '电子邮箱必填';
	} else if ($slen > 100) {
      $rs[] = '邮箱地址长度不能超过100个字节';
	} else if (! cEmailValid( $usr['email'] )) {
	  $rs[] = '电子邮箱格式有误';	
	}

    return $rs;
  }

  public function aOnly( $usr )
  {
    $uInfo = array( 'name' => $this->dbh->quote( $usr['loginName'], 'text' ),
					'email' => $this->dbh->quote( $usr['email'], 'text' ),
					'mobiNum' => $this->dbh->quote( $usr['mobiNum'], 'text' ),
				  );
    if (isset( $usr['realname'] ) && $usr['realname'] != '') {
	  $uInfo['nickName'] = $this->dbh->quote( $usr['realname'], 'text' );
	}
	$sql = 'INSERT INTO dy_usr( '.implode( ', ', array_keys( $uInfo ) ).' ) VALUE ('.implode( ',', $uInfo ).');';
	$rs = $this->dbh->exec( $sql );
	if (PEAR::isError( $rs ) || (! is_int( $rs ))) {
	  $rs = -1;
	}
	return $rs;
  }

}


class app_ctrl_u
{
  public static $arrWay2gPwd = array( 'usr'=>'用户名', 'email'=>'电子邮箱' );
  public static $arr2reg = array( 'loginName'=>'帐号',
				  'password'=>'密码',
				  'password2'=>'确认密码',
				  'realname'=>'昵称',
				  'mobiNum'=>'手机号码',
				  'email'=>'电子邮箱',
			   );
  public static $rsOfreg4sso = array( '-1'=>'Xml解密失败或xml格式有误',
				      '-2'=>'该用户名已存在',
				      '-3'=>'未知异常',
				      '-4'=>'发送激活邮件出现异常',/*（SSO后台注册激活开关开启的情况下）*/
				      '-5'=>'该电子邮箱已被人使用',
				 );

 public static function cUsrValid( $usrName )
 {
   return preg_match( '/^([\x{4E00}-\x{9FA5}]|[\x{FE30}-\x{FFA0}]|[\!@\#\$%\\\^&\*\+\|~,\.\?\/\:;\(\)\{\}《》\[\]_\w\s\-])+$/u', $usrName );
 }

 public static function cPwdValid( $pwd )
 {
   return preg_match( '/^([\w\._\*&\^%\$\#@\!]){6,20}$/u', $pwd );
 }

 // 通行证注册接口
 public function regAct()
 {
  if (isset( $_POST['regAct'] ) && $_POST['regAct'] == 'add') {
   $this->rs['respInfo'] = array();

   foreach (app_ctrl_u::$arr2reg as $key => $val) {
     if (isset( $_POST[$key] )) {
	   $usr[$key] = trim( $_POST[$key] );
	 } else {
	   $this->rs['respInfo'][] = '网络传输有误,请重发';
	   break;
	 }
   }
   if ($this->rs['respInfo'] !== array()
	   && (! isset( $_POST['a2term'] ))
   ){
     $this->rs['respInfo'][] = '网络传输有误,请重发';
   }

   if (isset( $_POST['password'] )) {
     unset( $_POST['password'] );
   }
   if (isset( $_POST['password2'] )) {
     unset( $_POST['password2'] );
   }

   if ($this->rs['respInfo'] == array()) {
     $this->rs['respInfo'] = app_mod_Usr::vInfo( $usr );

	 if ((! isset( $_POST['a2term'] ))
		  || $_POST['a2term'] != '1'
	 ){
	   $this->rs['respInfo'][] = '很抱歉，你没有同意网站协议';
	 }

	 if ($this->rs['respInfo'] == array()) {
	 	$usr['creator'] = cfg_Sys::$ssoCreator; // cfg_Sys::$ssoCreator,string,指项目中的域名，用来区分用户从应用注册。
		$rs = $this->reg2sso( $usr );

		if (intval( $rs ) > 0) {
		  $usrInfoDB = new app_mod_Usr();
		  $rs = $usrInfoDB->aOnly( $usr );

		  if ($rs > 0) {
			$this->rs['respInfo'][] = '恭喜你，注册成功';
			$_POST = array();
		  }	else {
			$this->rs['respInfo'][] = '网络访问有误,请重试';
		  }

		} else if ($rs == '-2' || $rs == '-5') {
		  $this->rs['respInfo'][] = app_ctrl_u::$rsOfreg4sso[$rs];
		} else {
		  $this->rs['respInfo'][] = '网络传输有误,请重试';
		}
	 }
   }
 }

 return $this->rs;
 }

 // 方正提供的注册接口，其中 cfg_Sys::$CookieCryptKey 是用来加密的密钥.
 // REMERBER: encryption of sha1 for pwd to sso of dayoo is: base64_encode( sha1( $usr['password'], true ) )
 public function reg2sso( $usr )
 {
  $rs = '<?xml version="1.0" encoding="utf-8"?>
<SSOUSER>
	<loginname>'.$usr['loginName'].'</loginname>
	<username>'.$usr['realname'].'</username>
	<password>{SHA}'.base64_encode( sha1( $usr['password'], true ) ).'</password>
	<email>'.$usr['email'].'</email>
	<creator>'.$usr['creator'].'</creator>
</SSOUSER>';

  /*$crypt = new CookieCrypt( cfg_Sys::$CookieCryptKey );
  $rs = $crypt->encrypt( $rs );*/

  $rs =	my_http::p2XYVreq( cfg_Var::$regURL, array( 'strxml'=>$rs ) ); // cfg_Var::$regURL='';

  if ($rs) {
	$rs = simplexml_load_string( $rs );
	$rs = ((string)$rs->status);
  }
  return $rs;
 }

 // 通行证忘记密码接口
 public function getpassAct()
 {
  //$this->rs['login8'] = 'usr';
  if (isset( $_POST['uORe'] )) {
    $this->rs['respInfo'] = array();
    if (is_string( $_POST['uORe'] )
		&& isset( $_POST['login8'] )
		&& ($_POST['login8'] == 'usr' || $_POST['login8'] == 'email')
	){
	  $this->rs['uORe'] = trim( $_POST['uORe'] );
	  $this->rs['login8'] = $_POST['login8'];
	  if ($this->rs['uORe'] != '') {
	    if ($this->rs['login8'] == 'usr') {
			if (app_ctrl_u::cUsrValid( $this->rs['uORe'] )) {
			  $url = 'loginName='.$this->rs['uORe'];
			} else {
			  $this->rs['respInfo'][] = '用户名格式有误';
			}
		} else {
			// decide if it is email or not
			if (cEmailValid( $this->rs['uORe'] )) {
			  $url = 'email='.$this->rs['uORe'];
			} else {
			  $this->rs['respInfo'][] = '电子邮箱格式有误';
			}
	    }
		if ($this->rs['respInfo'] == array()) {
			$url = cfg_Var::$gPwdURL.'?'.$url;
			$rs = my_http::g4XYVreq( $url );
			if ($rs === 'ok') {
			  $this->rs['respInfo'][] = '请登录您的邮箱,点击激活链接修改密码';
			} else {
			  if ($this->rs['login8'] == 'usr') {
				$this->rs['respInfo'][] = '注册的用户名有误,请重试';
			  } else {
				$this->rs['respInfo'][] = '注册的电子邮箱有误,请重试';
			  }
			}
		}
	  } else {
	    $this->rs['respInfo'][] = '请输入您的注册的电子邮箱或用户名';
	  }
	} else {
	  $this->rs['respInfo'][] = '网络传输有误,请重发';
	}
	if ($this->rs['respInfo'] == array()) {
	  unset( $this->rs['respInfo'] );
	}
  }
  return $this->rs;
 }


 //大洋统一认证登陆: 成功返回true  失败返回false
 private function LDAP_Login( $username, $password )
 {
  //统一认证地址
  define("LDAP_LOGIN_PAGE","http://login.dayoo.com/login");
	
  //$username=iconv("gbk", "UTF-8",$username);
  include_once ("HTTP/Request.php");
	
	$http_request = new HTTP_Request(LDAP_LOGIN_PAGE,array (
		'allowRedirects' => false,
		'maxRedirects' => 0
	));
	
	$http_request->setMethod(HTTP_REQUEST_METHOD_POST);
	
	$http_request->addPostData('username', $username);
	$http_request->addPostData('password', $password);
	$http_request->addPostData('noscode', 'no');
	$http_request->addPostData('daemon', 'yes');
	
	$http_request->sendRequest();
	$content=$http_request->getResponseBody();
	
	preg_match("/loginFlag=(\d?);username=(.*);userid=(.*)/",$content,$matches);
	if((int)$matches[1]===1 && $matches[2]==$username){
		return true;
	}else{
		return false;
	}
}

}

?>