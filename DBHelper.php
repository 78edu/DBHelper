<head>

<style>
table, td, th {
  border: 1px solid black;
}

td {
	
padding: 0 0;	
}

table {
  width: 100%;
  border-collapse: collapse;
  </style>

</head>
<?php

/*DBHelper - безопасные запросы к базе без инъекций


$sql_query="SELECT * from [[table]];";
или
$sql_query="INSERT INTO [[table]] {{insert:VALUES}};";

//Модификаторы для массивов :VALUES делает ($insert_fields) VALUES ($insert_values) (перечисляет через запятую)
:= делает :insert_field = :insert_value (перечисляет через запятую)

$parameters = [ 
	table=['PDO::PARAM_STR','posts'],
	insert=[ user_name=['PDO::PARAM_STR','posts'], user_birthdate=['PDO::PARAM_STR','posts'] ...];
--если в $parameters[param name][0] есть 'PDO::PARAM...', значит параметр это готовое значение для подстановки.
если нет, а вместо этого массивы - проходим по ним и строим список на подстановку в pdo::prepare 

$options = [ 'LIMIT'=1, ... ]
--LIMIT и OFFSET, пока только пары ключ-значение


$stmt = $pdo->SafeQuery(string $sql_query, array $parameters, array $options);


while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
{как обычно}


*/
class DBHelper{

	 /**
     * PDO instance
     * @var type 
     */
    public $pdo;



public function SafeQuery( object $pdo, string $sql_query, array $parameters, array $options, $nofields_in_values=false , $bind_only_labels=true)
{	$replacements=[];
	$only_arrays=[];
	$only_names=[];
	$only_simple=[];
	$labels_no_modifier=[];
	$final_bind=[];
//$sql_query="INSERT INTO {{table}} {{insert:VALUES}};";	
//Пропускаем SQL_OPTIONS чтобы потом отдельно дописать опции
$skip_labels=['SQL_OPTIONS'];

//1.Получим полный массив всех меток в запросе:
$labels_preg='#{{(.*?)}}#si';
preg_match_all($labels_preg,$sql_query,$labels);
$labels=$labels[1];
echo ('<hr>Метки:');var_dump($labels);
$labels_stage2=[];

//1.1 Получим список меток для quote (имена таблиц, оффсет и т.п.)
//1.Получим полный массив всех меток в запросе:
$labels_preg_names='#\[\[(.*?)\]\]#si';
preg_match_all($labels_preg_names,$sql_query,$labels_names);
echo "Имена:";
var_dump($labels_names[1]);

foreach ($parameters as $k=>$v)
{
if ( (isset($v[0])) AND ( (is_string($v[1])) OR (is_integer($v[1])) ) )	
{
	//Если есть 0 и 1 индексы (т.е. ну почти правильное значение), соединяем замену и параметры.
if (in_array($k,$labels_names[1])===true)
{
	echo "$k есть в именах<br>";
$replacements['[['.$k.']]']=$pdo->quote($v[1]);	
$only_names[$k]=$v;
}
	
}	
	
	
}
echo "Только имена, для игнорирования в bind";
var_dump($only_names);



//2.Отделим метки от :МОДИФИКАТОРА  метка в [0] модификатор в [1]
foreach($labels as $k=>$v)
{

$a=explode(':',$v);
if (isset($a[1])===true)
{$labels_stage2[$a[0]]=$a[1];}
/*else
{$labels_stage2[$a[0]]=null;}*/
}
echo ('<hr>Массив меток=>модификаторов (null если нет):');
var_dump($labels_stage2);

//3.Выйдем если есть метка, которой нет в массиве
foreach($labels_stage2 as $k=>$v)
{
	if ( (in_array($k,$skip_labels)) )
{continue;}
	if (array_key_exists($k,$parameters)===false)
	{echo "ОШИБКА: $k нет в массиве!"; return(false);}
}

echo '<br>$labels_stage2';
var_dump($labels_stage2);
//4.Рассортируем метки: массивы к массивам, простые к простым и удалим метки из skip_labels

foreach ($labels as $k=>$v)
{$b=explode(':',$v);$labels_no_modifier[$k]=$b[0]; }

echo 'Labels no modifier:';
var_dump($labels_no_modifier);

foreach ($parameters as $k=>$v)
{
if (in_array($k,$skip_labels))
{continue;}
if (in_array($k,array_keys($only_names)))
{continue;}
if ((in_array($k,$labels_no_modifier)===false) AND ($bind_only_labels===true) )
{continue;}
if ( (isset($v[0])) AND ( (is_string($v[1])) OR (is_integer($v[1])) ) )	
{
	
	
	echo "$k не массив"; $only_simple[$k]=$v;
	
	}
else
{echo "$k массив"; $only_arrays[$k]=$v;}	
	
}
echo "только простые";
var_dump($only_simple);
//5.Добавим к замене все простые значения в строке sql
foreach ($only_simple as $k=>$v)
{
//


$replacements['{{'.$k.'}}']=":$k ";	
$final_bind[$k]=$v;
}	
//Костыль, ничего не ломает, раньше был другой код
$temp_sql=$sql_query;



//6.Развернем массивы в строки VALUES в (:столбец,) VALUES (:значение,)  
//= в :столбец = :значение ,
$array_modifiers=$labels_stage2;
echo "<br>Только массивы:";
var_dump($only_arrays);
foreach ($only_arrays as $k=>$v)
{
	echo '<br>Разворачиваем '.$k;
if (array_key_exists($k,$array_modifiers)===true)
{
if ($array_modifiers[$k]==='VALUES')
{
// (столбцы) VALUES (значения)
if ($nofields_in_values===true)
{$temp_string[$k]='  VALUES ({{__VALUES}})';}
else
{$temp_string[$k]=' ({{__FIELDS}}) VALUES ({{__VALUES}})';}

$temp_fields='';
$temp_values='';
echo '<hr>';
var_dump($v);

$counter=count($v);
foreach ($v as $x=>$y)
{$counter-=1;$temp_fields.=$pdo->quote($x)." ";$temp_values.=":$x".'_value ';
if ($counter>0)
{$temp_fields.=", ";$temp_values.=', ';}

$final_bind[$x.'_value']=$y;
//$final_bind[$x]=['PDO::PARAM_STR',$x];

}


$temp_string[$k]=str_replace(['{{__FIELDS}}','{{__VALUES}}'],[$temp_fields,$temp_values],$temp_string[$k]);
}	
if ($array_modifiers[$k]==='=')
{
// столбцы = значения
//$temp_string[$k]=' {{__FIELDS}}) = {{__VALUES}}';
$temp_string[$k]='';
$temp_fields='';
$temp_values='';
echo '<hr>';
var_dump($v);

$counter=count($v);
foreach ($v as $x=>$y)
{$counter-=1;
//ВНИМАНИЕ, ВСТАВЛЯЕТСЯ КАК ЕСТЬ, ЧТО_ТО ПРИДУМАТЬ, ПРОВЕРЯТЬ ИЗ БЕЛОГО СПИСКА!!!
$temp_string[$k].=$x. '='.":$x".'_value ';


if ($counter>0)
{$temp_string[$k].=", ";}

$final_bind[$x.'_value']=$y;
//$final_bind[$x]=['PDO::PARAM_STR',$x];
}


//$temp_string[$k]=str_replace(['{{__FIELDS}}','{{__VALUES}}'],[$temp_fields,$temp_values],$temp_string[$k]);
}	
	
}
else
{
	//Если ключа метки из строки нет в массиве, значит ошибка. Пусть будет так для однозначности и точности.
	echo "ОШИБКА Параметра нет в строке!"; return false;
}
	
$replacements['{{'.$k.':'.$array_modifiers[$k].'}}']=$temp_string[$k];	
$replacements['{{SQL_OPTIONS}}']='';
}


echo "замены:";
var_dump($replacements);

$final_sql=str_replace(array_keys($replacements),array_values($replacements),$temp_sql);
echo '<br>параметры';
var_dump($parameters);
var_dump($final_sql);
var_dump($sql_query);

var_dump($final_bind);



return [$final_sql,$final_bind];
}
//end func 

public function BindAll (object &$pdo_statement, array $bind )
{
$stmt=$pdo_statement;


foreach ($bind as $k=>$v)
{

$type=$v[0];
echo "<br>$k=".substr($v[0],0,10).' '.($type).' =';
if (strpos($type,'PDO::PARAM_STR')!==false)
{$param=PDO::PARAM_STR; echo "STR";}

if (strpos($type,'PDO::PARAM_BOOL')!==false)
{$param=PDO::PARAM_BOOL; echo "BOOL";}

if (strpos($type,'PDO::PARAM_INT')!==false)
{$param=PDO::PARAM_INT;echo "INT";}

echo "<br>Binding $k as $v[1]";
var_dump($stmt->bindValue($k, $v[1], $param));	
	
	
}

}
//end func

public function ConnectSQLite( string $database_name)
{

if ($this->pdo == null) {
			
$options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
$this->pdo = new PDO("sqlite:" . $database_name,'','',$options);
}	
	

return $this->pdo;
}


public function FastSafeQuery (object &$pdo, object &$db, string $sql_query, array $parameters)
{

$safe_query=$db->SafeQuery( $pdo, $sql_query, $parameters , []);
$stmt=$pdo->prepare($safe_query[0]);
var_dump($safe_query);
$db->BindAll($stmt,$safe_query[1]);
echo 'Выполнен запрос:';
var_dump($stmt->execute());
var_dump($stmt);
return $stmt;	

}

}
//end class


//Тесты
//1.Соединение
$db = new DBHelper();
$pdo=$db->ConnectSQLite('test2.sqlite');

if ($pdo != null)
    echo 'Connected to the SQLite database successfully!';
else
    echo 'could not connect to the SQLite database!';

//2.Запросы

/*

$sql_query="SELECT * from [[table]] WHERE author = {{author}};";
$parameters = [ 
	//'table'=>['PDO::PARAM_STR','posts'],
	
	'author'=>['PDO::PARAM_STR','admin'], 'table'=>['PDO::PARAM_STR','posts'],  'test2'=>['PDO::PARAM_STR','posts']
	
		];


$safe_query=$db->SafeQuery( $pdo, $sql_query, $parameters , []);
var_dump($safe_query[0]);
$stmt=$pdo->prepare($safe_query[0]);
var_dump($stmt);
var_dump($safe_query[1]);
$db->BindAll($stmt,$safe_query[1]);
$stmt->execute();*/
//


//FastSafeQuery test


//INSERT работает
/*$stmt=$db->FastSafeQuery($pdo, $db, "INSERT INTO [[table]] {{insert:VALUES}};",
[ 
	'insert'=>['author'=>['PDO::PARAM_STR','new'],'post_id'=>['PDO::PARAM_STR','new']], 'table'=>['PDO::PARAM_STR','posts'], 
	'test2'=>['PDO::PARAM_STR','posts']
]);*/

//DELETE работает
/*$stmt=$db->FastSafeQuery($pdo, $db, "DELETE from [[table]] WHERE {{insert:=}};",
[ 
	'insert'=>[
	
	'post_id'=>['PDO::PARAM_STR','new']
			  ], 
	'table'=>['PDO::PARAM_STR','posts'], 
	'test2'=>['PDO::PARAM_STR','posts']
]);*/

    
//UPDATE работает	
/*	
$stmt=$db->FastSafeQuery($pdo, $db, "UPDATE [[table]] SET author = {{author}} WHERE category = {{category}};",
[ 
	'author'=>['PDO::PARAM_STR','bullshit'],
    'category'=>['PDO::PARAM_STR','nat'],
 	'table'=>['PDO::PARAM_STR','posts'],  
	'test2'=>['PDO::PARAM_STR','posts']
]);	*/


//Работает
//Пересоздает таблицу, можно и через pdo->exec, но это тоже тест
$stmt=$db->FastSafeQuery($pdo, $db, "DROP TABLE IF EXISTS [[table]];",
[ 
	'table'=>['PDO::PARAM_STR','posts']  
]);

$stmt=$db->FastSafeQuery($pdo, $db, "CREATE TABLE IF NOT EXISTS [posts] ( 
	[post_id] TEXT PRIMARY KEY NOT NULL , 
	[category] TEXT , 
	[title] TEXT  , 
	[postdate] TEXT  , 
	[author] TEXT , 
	[likes] INTEGER  , 
	[dislikes] INTEGER   , 
	[nocomment] BOOL   , 
	[content] TEXT   , 
	[images] TEXT   , 
	[links] TEXT   , 
	[uploads] TEXT  
);",
[ 
	
]);


$rand=mt_rand(4,20);

for ($i=0;$i<$rand;$i++)
{
$stmt=$db->FastSafeQuery($pdo, $db, "INSERT INTO posts {{values:VALUES}};",
[ 
	'values'=> [
	'post_id'=>     ['PDO::PARAM_STR'  ,  mt_rand(0,1000)] , 
	'category'=>    ['PDO::PARAM_STR' ,  mt_rand(0,200)] ,
	'title'=>       ['PDO::PARAM_STR'  , mt_rand(0,3)],
	'postdate'=>    ['PDO::PARAM_STR'  , mt_rand(0,10)],
	'author'=>      ['PDO::PARAM_STR' ,  mt_rand(0,10)],
	'likes'=>       ['PDO::PARAM_INT'  , mt_rand(0,10)],
	'dislikes'=>    ['PDO::PARAM_INT'   ,mt_rand(0,10)], 
	'nocomment'=>   ['PDO::PARAM_BOOL'  ,mt_rand(0,1)], 
	'content'=>     ['PDO::PARAM_STR'   ,mt_rand(0,10)], 
	'images'=>      ['PDO::PARAM_STR'   ,mt_rand(0,10)], 
	'links'=>       ['PDO::PARAM_STR'   ,mt_rand(0,10)], 
	'uploads'=>     ['PDO::PARAM_STR'   ,mt_rand(0,10)]
]]);

	
	
}










//SELECT работает, LIMIT работает
$stmt=$db->FastSafeQuery($pdo, $db, "SELECT * from [[table]] LIMIT {{LIMIT}};",
[ 
	'author'=>['PDO::PARAM_STR','admin'],
	'LIMIT'=>['PDO::PARAM_STR','1000'], 
	'table'=>['PDO::PARAM_STR','posts'],  
	'test2'=>['PDO::PARAM_STR','posts']
]);



$table='';
$table.='<table>';
 $out_counter=0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
{
	$table.='<tr>';
	$row_size=count($row);
	
	foreach ($row as $k=>$v)
	{
	$table.='<td >';	
	$table.=$v;
	$table.='</td>';	
		
	}
	$table.='</tr>';
	/*echo "<h2>".$row['post_id']."  ---- category: $a</h2>"."<h3>".$row['title']."  </h3>";
	

	$json=($row['content']);
	var_dump($json);
//	var_dump($row['content']);
	
 
	echo '<hr>';*/
	
	
	
	$out_counter+=1;
}
$table.='</table>';

echo '<h1>Total records:'.$out_counter.'</h1>';
echo $table;	
	





?>