<?php
//$sender=$_REQUEST['sender'];
//$message=$_REQUEST['message'];
//$userNumber="+381643947344";
//$input="info";
$userNumber=$_REQUEST['phone'];
$input=$_REQUEST['text'];
$headers= getallheaders();
if(empty($userNumber)){
	$userNumber=$headers['phone'];
}
if(empty($userNumber)){
	$input=$headers['text'];
}
$input=rawurldecode($input);
if($input!=""){
	$array=explode(' ', $input);

	if(strtolower($array[0])=="prijava"){
		if(!autorization($userNumber)){
			if(count($array)==3){
				$username=$array[1];
				$password=$array[2];
				$reg_ex="/^[A-Za-z\!\#\@\s\d]{5,}$/";
				if(preg_match($reg_ex, $password) && preg_match($reg_ex, $username)){
					if(subscribe($userNumber,$username,$password)){
						sendRespone("Uspesno ste sacuvali broj.");
					}
					else{
						sendRespone("Ne postoji korisnik sa datim korisnickim imenom i sifrom.");
					}
				}
				else{
					sendRespone("Uneta email adresa, korisnicko ime ili lozinka nije u ispravnom formatu.");
				}
			}
			else{
				sendRespone("Trazeni parametri nisu prosledjeni.");
			}
		}
		else{
			sendRespone("Ovaj broj je vec registrovan u bazi podataka.");
		}
	}

	elseif(strtolower($array[0])=="upisi"){
		if(autorization($userNumber)){
			if(count($array)>=2){
				$trick="";
				$count=count($array);
				for ($i=1; $i < $count; $i++) { 
					$trick.=$array[$i].=" ";
				}
				if(trim($trick)!=""){
					$result=addTrick($trick,$userNumber);
					if($result=="done"){
						sendRespone("Trik $trick je uspesno sacuvan.");
					}
					elseif ($result=="doneBefore") {
						sendRespone("Trik \"$trick\" je vec upisan.");
					}
					else{
						sendRespone("Trik pod imenom \"$trick\" ne postoji u bazi podataka.");
					}
				}
				else{
					sendRespone("Nije prosledjeno ime trika.");
				}
			}
			else{
				sendRespone("Trazeni parametri nisu prosledjeni.");
			}
		}
		else{
			sendRespone("Ovaj broj nije registrovan u bazi podataka. Molimo registrujte broj.");
		}
	}

	elseif (strtolower($array[0])=="izbrisi") {
		if(autorization($userNumber)){
			$trick="";
			$count=count($array);
			for ($i=1; $i < $count; $i++) { 
				$trick.=$array[$i].=" ";
			}
			if(trim($trick)!=""){
				$result=removeTrick($trick,$userNumber);
				if($result=="removed"){
					sendRespone("Trik $trick je uspesno izbrisan.");
				}
				elseif ($result=="undone") {
					sendRespone("Trik \"$trick\" niste upisali.");
				}
				else{
					sendRespone("Trik pod imenom \"$trick\" ne postoji u bazi podataka.");
				}
			}
			else{
				sendRespone("Ime trika nije uneto.");
			}
		}
		else{
			sendRespone("Ovaj broj nije registrovan u bazi podataka. Molimo registrujte broj.");
		}
	}

	elseif (strtolower($array[0])=="spisak" && strtolower($array[1])=="trikova") {
		sendRespone(trickList());
	}

	elseif (strtolower($array[0])=="spisak" && strtolower($array[1])=="uradjenih" && strtolower($array[2])=="trikova") {
		if(autorization($userNumber)){
			$result=doneTrickList($userNumber);
			if($result!="noTricks"){
					sendRespone("$result");
			}
			else{
				sendRespone("Nemate ni jedan upisan trik.");
			}
		}
		else{
			sendRespone("Ne dostupan servis. Ovaj broj nije registrovan u bazi podataka.");
		}
	}

	elseif (strtolower($array[0])=="spisak" && strtolower($array[1])=="neuradjenih" && strtolower($array[2])=="trikova") {
		if(autorization($userNumber)){
			$result=undoneTrickList($userNumber);
			if($result!="allDone"){
				sendRespone("$result");
			}
			else{
				sendRespone("BRAVO! Upisali ste sve trikove.");
			}
		}
		else{
			sendRespone("Ne dostupan servis. Ovaj broj nije registrovan u bazi podataka.");
		}
	}

	elseif (strtolower($array[0])=="info") {
		sendRespone("Spisak funkcija:\n
			-\"Prijava korisnickoIme lozinka\"(Da se prijavite za servis ili promenite broj telefona)\n
			-\"Upisi ImeTrika\"(Da upisete novouradjeni trik)\n
			-\"Izbrisi ImeTrika\"(Da izbrisete uradjeni trik)\n
			-\"Spisak trikova\"(Da dobijete spisak svih trikova)\n
			-\"Spisak neuradjenih trikova\"(Da dobijete spisak svih neuradjenih trikova)\n
			-\"Spisak uradjenih trikova\"(Da dobijete spisak svih uradjenih trikova)\n");
	}
	
	else{
		sendRespone("Pogresno unet zahtev. Posaljite INFO da vidite spisak funkcija.");
	}
}
else{
	sendRespone("Prosledili ste praznu poruku. Posaljite INFO da vidite spisak funkcija.");
}

function connect(){
	$db_serverName="localhost";	
	$db_username="milosduk";
	$db_password="XRdMmWnwPgWQ";
	$db_database="milosduk_slackline";
	$conn=mysql_connect($db_serverName,$db_username,$db_password);
	$baza=mysql_select_db($db_database,$conn);
	mysql_set_charset('utf8',$conn);
	return $conn;
}

function disconnect($conn){
	mysql_close($conn);
}

function autorization($number){
	$conn=connect();
	$query="SELECT idUser FROM useradd WHERE phoneNumber='$number'";
	$result= mysql_query($query);
	if(mysql_num_rows($result)==1){
		disconnect($conn);
		return true;
	}
	return false;
}

function subscribe($number,$username,$password){
	$conn=connect();
	$query="SELECT idUser FROM user WHERE username='$username' AND password='".md5($password)."';";
	$result= mysql_query($query);
	if(mysql_num_rows($result)==1){
		$result=mysql_fetch_assoc($result);
		$id=$result['idUser'];
		$query="SELECT phoneNumber FROM useradd WHERE idUser=$id;";
		$result= mysql_query($query);
		if(mysql_num_rows($result)==1){
			$query="UPDATE useradd SET phoneNumber='$number' WHERE idUser=$id;";
		}
		else{
			$query="INSERT INTO useradd userId,phoneNumber VALUES ($id,'$number');";
		}
		$result= mysql_query($query);
		disconnect($conn);
		return true;
	}
	else
		return false;
}

function addTrick($trickName,$number){
	$conn=connect();
	$query="SELECT idTrick FROM trick WHERE name='$trickName';";
	$result= mysql_query($query);
	if(mysql_num_rows($result)==1){
		$result=mysql_fetch_assoc($result);
		$idTrick=$result['idTrick'];
		$query="SELECT u.idUser FROM user u INNER JOIN useradd ua ON u.idUser=ua.idUser WHERE ua.phoneNumber='$number';";
		$result= mysql_query($query);
		$result=mysql_fetch_assoc($result);
		$idUser=$result['idUser'];
		$query="SELECT idUser FROM doneTrick WHERE idUser=$idUser AND idTrick=$idTrick;";
		$result= mysql_query($query);
		if(mysql_num_rows($result)==0){
			$query="INSERT INTO `donetrick`( `idUser`, `idTrick`) VALUES ($idUser,$idTrick);";
			$result= mysql_query($query);
			disconnect($conn);
			return 'done';
		}
		else{
			disconnect($conn);
			return 'doneBefore';
		}
	}
	disconnect($conn);
	return false;
}

function removeTrick($trickName,$number){
	$conn=connect();
	$query="SELECT idTrick FROM trick WHERE name='$trickName';";
	$result= mysql_query($query);
	if(mysql_num_rows($result)==1){
		$result=mysql_fetch_assoc($result);
		$idTrick=$result['idTrick'];
		$query="SELECT u.idUser FROM user u INNER JOIN useradd ua ON u.idUser=ua.idUser WHERE ua.phoneNumber='$number';";
		$result= mysql_query($query);
		$result=mysql_fetch_assoc($result);
		$idUser=$result['idUser'];
		$query="SELECT idDoneTrick FROM doneTrick WHERE idUser=$idUser AND idTrick=$idTrick;";
		$result= mysql_query($query);
		if(mysql_num_rows($result)!=0){
			$query="DELETE FROM donetrick WHERE idUser=$idUser AND idTrick=$idTrick;";
			$result= mysql_query($query);
			disconnect($conn);
			return 'removed';
		}
		else{
			disconnect($conn);
			return 'undone';
		}
	}
	disconnect($conn);
	return false;
}

function trickList(){
	$conn=connect();
	$query="SELECT name FROM trick ORDER BY idTrickWeightMark;";
	$string="";
	$result= mysql_query($query);
	disconnect($conn);
	$count=mysql_num_rows($result);
	$curent=1;
	while ($res=mysql_fetch_assoc($result)) {
		if($curent<$count){
			$string.=$res["name"].", ";
		}
		else{
			$string.=$res["name"].".";
		}
		$curent++;
	}
	return $string;
}

function doneTrickList($number){
	$conn=connect();
	$string="";
	$query="SELECT idUser FROM useradd WHERE phoneNumber='$number';";
	$result= mysql_query($query);
	$result=mysql_fetch_assoc($result);
	$idUser=$result['idUser'];
	$query="SELECT t.name FROM trick t INNER JOIN donetrick dt ON t.idTrick=dt.idTrick INNER JOIN user u ON dt.idUser=u.idUser WHERE u.idUser=$idUser ORDER BY t.idTrickWeightMark;";
	$result= mysql_query($query);
	disconnect($conn);
	$count=mysql_num_rows($result);
	$curent=1;
	if(mysql_num_rows($result)>0){
		while ($res=mysql_fetch_assoc($result)) {
			if($curent<$count){
				$string.=$res["name"].", ";
			}
			else{
				$string.=$res["name"].".";
			}
			$curent++;
		}
		return $string;
	}
	else{
		return "noTricks";
	}
}

function undoneTrickList($number){
	$conn=connect();
	$string="";
	$query="SELECT idUser FROM useradd WHERE phoneNumber='$number';";
	$result= mysql_query($query);
	$result=mysql_fetch_assoc($result);
	$idUser=$result['idUser'];
	$query="SELECT name FROM trick WHERE idTrick NOT IN (SELECT idTrick FROM doneTrick WHERE idUser=$idUser);";
	$result= mysql_query($query);
	disconnect($conn);
	$count=mysql_num_rows($result);
	$curent=1;
	if(mysql_num_rows($result)>0){
		while ($res=mysql_fetch_assoc($result)) {
			if($curent<$count){
				$string.=$res["name"].", ";
			}
			else{
				$string.=$res["name"].".";
			}
			$curent++;
			
		}
		return $string;
	}
	else{
		return "allDone";
	}
}

function sendRespone($text){
	#print {GSMSMS}{}{}{$sender}{$text};
	#echo $text;
	$text=rawurlencode($text);
	header('Content-Type:text/html;charset=utf-8');
	header("text:$text");
}