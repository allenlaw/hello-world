<?php

/*
以字符串的每一位代表一种图片类型以及数量，字符串每一位的累加值代表该模板支持多少篇文章
100000 ：代表有一张16:9 图片的单篇模板
010000 ：代表有一张4:3 图片的单篇模板
001000 ：代表有一张1:1 图片的单篇模板
000100 ：代表有一张3:4 图片的单篇模板
000010 ：代表有一张9:16 图片的单篇模板
000001 ：代表纯文字的单篇模板
*/
$typeNum=6; //图文类型数量，即图片类型数量，加上1（纯文字）；
$maxArticlesPrePage=6;//每页的最大文章数（每种类型不能超过 9篇文章）

$imgType=array(//文章类型配置
	'16-9'=>'100000',
	'4-3'=>'010000',
	'1-1'=>'001000',
	'3-4'=>'000100',
	'9-16'=>'000010',
	'no'=>'0000001',
);

$tempConfig=array(
	1=>array(//每版1篇的模板
		'1',
		'2',
		'3',
		'4',
		'5',
		'6',
	),
	2=>array(//每版2篇的模板
		'100001',
		'010001',
		'001001',
		'000101',
		'000011',
		'200000',
		'020000',
	),
	3=>array(//每版3篇的模板
		'000003'
	),
	4=>array(//每版4篇的模板
		'000004'
	),
	5=>array(//每版5篇的模板
		'000005'
	),
	6=>array(//每版6篇的模板
		'000006'
	),

);


//测试场景
$bdata=array(//某个板块的文章列表
	array('id'=>'content_001','imgType'=>'no'),
	array('id'=>'content_002','imgType'=>'no'),
	array('id'=>'content_003','imgType'=>'no'),
	array('id'=>'content_004','imgType'=>'4-3'),
	array('id'=>'content_005','imgType'=>'no'),
	array('id'=>'content_006','imgType'=>'no'),
	array('id'=>'content_007','imgType'=>'16-9'),
	array('id'=>'content_008','imgType'=>'no'),
	array('id'=>'content_009','imgType'=>'9-16'),
);


//计算合适的分页数,及每页文章数
$pages=pagesNumCount(count($bdata));
//print_r($pages);

//计算每页文章分布
$articlePrePage=setArticlePrePage($bdata,$pages);
//print_r($articlePrePage);

foreach($articlePrePage as $index=>$articlesPage){
	//计算本分页的总体分布
	$pageCode=0;
	foreach ($articlesPage as $article) {
		$pageCode+=(int)$article['imgType'];
	}
	$pageCode=(string)$pageCode;
	$pageCode=str_pad($pageCode,6,"0",STR_PAD_LEFT);

	$pageType=templateSelect($pageCode);

	echo '第'.$index.'页，'.$pageType.'，文章列表：';
	echo '<pre>';
	print_r($articlesPage);
	echo "<br>";

}
//测试场景 end




//输入文章列表，返回具体每页的文章分布
function setArticlePrePage($bdata,$pages=array()){
	//获取配置（demo 用 global 方式，实际使用不建议）
	global $imgType;

	//初始化
	$result=array();
	$picSortArr=array();

	//图片优先排序,并替换imgType 编码
	foreach($bdata as $key=>$value){
		$picSortArr[$key]=$value['imgType'];
		$bdata[$key]['imgType']=isset($imgType[$value['imgType']])?$imgType[$value['imgType']]:$value['imgType'];
	}
	array_multisort($picSortArr,$bdata);

	//分配文章到每个分页
	$pageIndex=0;
	foreach($bdata as $key=>$value){
		if(!isset($result[$pageIndex])) $result[$pageIndex]=array(); //初始化每页结果数组

		if(count($result[$pageIndex])>=$pages[$pageIndex]){//如果当前页已经填满，依次监测下页是否填满
			$tryTimes=0;//尝试跳跃值
			do{
			   if((count($pages)-1)== $pageIndex)//移到最后一个时，返回第一个
					$pageIndex=0;
				else
					$pageIndex+=1;
				$tryTimes+=1;
				if($tryTimes>count($pages)+1) break;//当所有分页均尝试过，跳出循环
			}while(count($result[$pageIndex])>=$pages[$pageIndex]);		
		}
		array_push($result[$pageIndex],$value);
		
		if((count($pages)-1)== $pageIndex)
			$pageIndex=0;
		else
			$pageIndex+=1;
	}
	return $result;

}



//输入文章数量，返回分页数及每页分布
function pagesNumCount($articleNum,$pages=false){
	//获取配置（demo 用 global 方式，实际使用不建议）
	global $maxArticlesPrePage;

	static $result=array();

	if($pages===false){
		$spages=ceil($articleNum/$maxArticlesPrePage);
		if($spages>=3){
			$pages=$spages+rand(0,2);
		}elseif($spages>=2){
			$pages=$spages+rand(0,1);
		}
	}

	if($pages>1){
		$pitems=floor($articleNum/$pages);
		if($pitems<4){
			$pitems=$pitems+rand(0,2);
		}elseif($pitems<6){
			$pitems=$pitems+rand(0,1);
		}
		array_push($result,$pitems);
		$articleNum-=$pitems;
		$pages-=1;
		pagesNumCount($articleNum,$pages);
	}elseif($articleNum>0){
		array_push($result, $articleNum);
	}
	return $result;
	

}


/*
输入一版文章分布情况，返回匹配模板
*/
function templateSelect($pageArticle){
	//获取模板配置（demo 用 global 方式，实际使用不建议）
	global $typeNum;    
	global $tempConfig;

	//获取文章列表的图片分布及文章数量
	$pageArticleArr=preg_split('//', $pageArticle, -1, PREG_SPLIT_NO_EMPTY);
	$pageArticleNum=array_sum($pageArticleArr);

	if(!isset($tempConfig[$pageArticleNum])){
		return '该页文章数量不匹配';
	}else{
		$score=array();
		foreach ($tempConfig[$pageArticleNum] as $value) {
			$tvalue=preg_split('//', $value, -1, PREG_SPLIT_NO_EMPTY);//实际使用中这部分是重复切分，可改进
			$tscore=0;
			for($i=0;$i<$typeNum;$i++){
				if(($tvalue[$i]==$pageArticleArr[$i]) && ($tvalue[$i]!=0) ){ //完全匹配，且不为0的，评分+1
					$tscore+=1;
					continue;
				}
				if($tvalue[$i]*$pageArticleArr[$i]!=0){//不完全匹配，且不为0的，评分+0.5
					$tscore+=0.5;
					continue;
				}
			}
			$score[$value]=$tscore;
		}
		arsort($score,SORT_NUMERIC);
		if(empty($score) || $score[key($score)]==0){
			return '匹配不到任何模板，采用默认模板';
		}else{
			return '最佳模板是'.key($score).',模板评分是'.$score[key($score)].'';
		}
	}

}//end func


?>