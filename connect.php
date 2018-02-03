<?php 

define("REMOTE_IP","YOUR_IP");
define("PASSWORD","YOUR_PASSWORD");

$site = $argv[1];
$configurationFileName = $site.".conf";

$connection = ssh2_connect(REMOTE_IP,"22");


if(!file_exists($configurationFileName)){
	$vhostData = "<VirtualHost *:80>
	    ServerName ".$site."
	    ServerAlias www.".$site."
	    ServerAdmin webmaster@localhost
	    DocumentRoot /var/www/html/".$site."
	    ErrorLog /var/www/html/".$site."/error.log
	    CustomLog /var/www/html/".$site."/access.log combined
	</VirtualHost>
	";

	$remoteFileNameWithPath = "/etc/apache2/sites-available/".$configurationFileName;

	//önce locale alıyoruz,
	file_put_contents($configurationFileName,$vhostData);
	ssh2_auth_password($connection,"root",PASSWORD);
	//öncelike sunucuda dosya varsa silelim, buraya var mı yok mu diye kontrol ettirebilirsiniz.
	ssh2_exec($connection,"rm ".$remoteFileNameWithPath);

	//dosyayı gönderelim
	$stream = ssh2_scp_send($connection,$configurationFileName,$remoteFileNameWithPath);
	if($stream == 1){
		echo "Vhost ekleme başarılı\n";
	}else{
		echo "vhost ekleme hatası";
		die("Uzak sunucuya dosya gönderilemedi");
		//sunucuya ekleyemediysek localdeki hosts dosyasının da düzenlemesine gerek yok.
	}

	/* 
	
	ileride burayı daha da kısaltıyor olacağız. 
	ssh2_exec genellikle tek veya iki komut gönderiminde kullanılıyor.
	çoklu gönderim için normalde ssh2_shell kullanıyoruz ancak çok kafa bulandırmayalım


	*/
	$stream = ssh2_exec($connection,'a2ensite '.$site);
	stream_set_blocking($stream, true);
	$stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
	echo stream_get_contents($stream_out);

	$stream = ssh2_exec($connection,'mkdir /var/www/html/'.$site);
	stream_set_blocking($stream, true);
	$stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
	echo stream_get_contents($stream_out);

	$stream = ssh2_exec($connection,'service apache2 restart');
	stream_set_blocking($stream, true);
	$stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
	echo stream_get_contents($stream_out);

	unlink($configurationFileName);
}


$hostFileLocation = "";
if(strpos(PHP_OS,"WIN") !== false){
	//buralar windows
	$hostFileLocation = "C:\\Windows\\System32\\drivers\\etc\\hosts";
}else if(strpos(PHP_OS,"Unkown") !== false){
	//buralar bilinmiyor,
	die("Ne kullanıyorsun sen kardeş");
}else{
	//mac linux ve unix için hepsi aynı yerde
	$hostFileLocation = "/etc/hosts";
}

$lines =  file_get_contents($hostFileLocation);

if(strpos($lines, $site) === false){
	//içeride biz daha önce bir tanımlama yapmışımyız ona bakıyor.
	//false olmadığı anlamına geliyor.
	//string position'ı bulamadığı için false dönüyor. (site varsa)
	shell_exec('echo "'.REMOTE_IP ."\t\t".$site.'" >> '.$hostFileLocation);
	//buradaki \t tab anlamına geliyor, içerisine iki kere tab'e basılmış gibi yapıyor.
}

//aslında PHP_OS_FAMILY'i kullanabilirdik ama o PHP 7.2+ sürümü için geçerli. Bir daha ona dönmeyelim.
