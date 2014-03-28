<?php
/*
 *@description   图片在线管理
 *@author widuu  http://www.widuu.com
 *@mktime 08/01/2014
 *
 */

	header("Content-Type: text/html; charset=utf-8");
    error_reporting( E_ERROR | E_WARNING );
	require_once("conf.php");
	require_once("imageManger.php");
	$client = new Qiniu_MacHttpClient(null);
	$result = array();
    $data = Qiniu_RSF_ListPrefix($client,$BUCKET,'','',5);
	
	foreach ($data[0] as $key => $value) {
			array_push($result,array($value["key"],$value["putTime"]));
	}
	
	do{
		if($data[1]==""){
			$marker = '';
		}else{
			$marker = $data[1];
		}
		
		$data = Qiniu_RSF_ListPrefix($client,$BUCKET,'',$marker,5);
		foreach ($data[0] as $key => $value) {
			array_push($result,array($value["key"],$value["putTime"]));
		}
	}while($data[1]!="");
	
	$len = count($result);
	
	//冒泡按时间排序
	for($i=1;$i<$len;$i++)
	{
		for($j=$len-1;$j>=$i;$j--)
			if($result[$j][1]<$result[$j-1][1])
			{
			 $x=$result[$j];
			 $result[$j]=$result[$j-1];
			 $result[$j-1]=$x;
			}
	}
	$str="";

	foreach($result as $k => $v){
		if ( preg_match( "/\.(gif|jpeg|jpg|png|bmp)$/i" , $v[0] ) ) {
                      $str.=str_replace(chr(32),"%20",$v[0])."ue_separate_ue";
          }
	}
	echo $str;