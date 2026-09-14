<?php
$mlhost='listes.math.cnrs.fr';
$mlpref='/wws';
$mlnom='rt-uq-news';
$pr=proc_open(['/bin/httpsget',$mlhost,'443',$mlpref,"1"],[1=>['pipe','w'], 2=>['pipe','w']],$pipes,null,[]);
$s="";
while($h=fread($pipes[2], 8192)) $s.=$h;
$s=explode("\n", $s);
foreach($s as $ss) {
	if(stripos($ss, "set-cookie: ")===0) {
		$sess=substr($ss, strlen("set-cookie: "), strpos($ss, ";")-strlen("set-cookie: "));
	}
}
/*
$s="";
while($h=fread($pipes[1], 8192)) $s.=$h;
$s=explode("\n", $s);
foreach($s as $ss) {
	if(stripos($ss, "\"csrftoken\"")!==false) {
		$csrf=substr($ss, stripos($ss, "value=\"")+strlen("value=\""));
		$csrf=substr($csrf, 0, stripos($csrf, "\""));
		break;
	}
}
*/
//print "sess: $sess csrf: $csrf\n";
proc_close($pr);

$ok=false;
foreach([1,2] as $i) {
	//$pr=proc_open(['/bin/httpsget',$mlhost,'443',$mlpref,"1"],[1=>['pipe','w'], 2=>['pipe','w']],$pipes2,null,["METHOD=POST","ADD_HDR=Cookie: $sess", "FORM=csrftoken=$csrf&month=&arc_file=&action=arc&list=rt-uq-news&previous_action=&response_action_confirm=Je+ne+suis+pas+un+spameur"]);
	$pr=proc_open(['/bin/httpsget',$mlhost,'443',$mlpref,"1"],[1=>['pipe','w'], 2=>['pipe','w']],$pipes2,null,["METHOD=POST","ADD_HDR=Cookie: $sess", "FORM=action=arc&list=$mlnom&response_action_confirm="]);
	$s="";
	while($h=fread($pipes2[1], 8192)) $s.=$h;
	if(stripos($s, "302 moved")!==false) $ok=true;
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
	print "$month - $year...";
	$pr=proc_open(['/bin/httpsget',$mlhost,'443',"$mlpref/arc/$mlnom/$year-$month/","1"],[1=>['pipe','w'], 2=>['pipe','w']],$pipes,null,["ADD_HDR=Cookie: $sess"]);
	$s="";
	while($h=fread($pipes[1], 8192)) $s.=$h;
	proc_close($pr);
	$s=explode("\n", $s);
	$s=array_reverse($s);
	foreach($s as $ss) {
		if($nLu >= $nLuMax) break;
		if(stripos($ss, "href=\"msg")!==false && stripos($ss, ".html\">[$mlnom] ")!==false) {
			$lnk=substr($ss, stripos($ss, "href=\"msg")+strlen("href=\""));
			$lnk=substr($lnk, 0, stripos($lnk, ">")-1);
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
file_put_contents("ephemeral/mlmsgs.html", $out);

