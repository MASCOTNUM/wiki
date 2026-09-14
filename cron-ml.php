<?php
$mlhost='listes.math.cnrs.fr';
$mlpref='/wws';
$mlnom='rt-uq-news';

$ok=false;
$sess="";
foreach([1,2] as $i) {
	$pr=proc_open(['/bin/httpsget',$mlhost,'443',$mlpref,"1"],[1=>['pipe','w'], 2=>['pipe','w']],$pipes,null,["METHOD=POST", "ADD_HDR=Cookie: $sess", "FORM=action=arc&list=$mlnom&response_action_confirm="]);
	$s="";
	if($i==1) {
		fclose($pipes[1]);
		while($h=fread($pipes[2], 8192)) $s.=$h;
		$sess=substr($s, stripos($s, "set-cookie: ")+strlen("set-cookie: "));
		$sess=substr($sess, 0, stripos($sess, ";"));
	} else {
		fclose($pipes[2]);
		while($h=fread($pipes[1], 8192)) $s.=$h;
		if(stripos($s, "302 moved")!==false) $ok=true;
	}
	proc_close($pr);
}

if(!$ok) {
	print "Anti-spam non passé\n";
	exit;
}

$nLu=0;
$nTry=0;
$nLuMax=5;
$nTryMax=12;
$month=date('m');
$year=date('Y');
$out="<ul>\n";
while($nLu < $nLuMax && $nTry < $nTryMax) {
	$pr=proc_open(['/bin/httpsget',$mlhost,'443',"$mlpref/arc/$mlnom/$year-$month/"],[1=>['pipe','w']],$pipes,null,["ADD_HDR=Cookie: $sess"]);
	$s="";
	while($h=fread($pipes[1], 8192)) $s.=$h;
	proc_close($pr);
	$s=explode("\n", $s);
	$nn=count($s);
	for($i=$nn-1; $i>=0 && $nLu<$nLuMax; $i--) {
		$ss=$s[$i];
		if(stripos($ss, "href=\"msg")!==false && stripos($ss, ".html\">[$mlnom] ")!==false) {
			$lnk=substr($ss, stripos($ss, "href=\"msg")+strlen("href=\""));
			$lnk=substr($lnk, 0, stripos($lnk, "\""));
			$subj=substr($ss, stripos($ss, "[$mlnom] ")+strlen("[$mlnom] "));
			$subj=substr($subj, 0, stripos($subj, "<"));
			$out.="<li><a href=\"https://$mlhost$mlpref/arc/$mlnom/$year-$month/$lnk\">$subj</a>\n";
			$nLu++;
		}
	}
	$nTry++;
	if($month=="01") {
		$month=12;
		$year--;
	} else {
		$month=sprintf("%02d", intval($month)-1);
	}
}
$out .= "</ul>\n";
file_put_contents("ephemeral/mlmsgs.html", $out, LOCK_EX);

