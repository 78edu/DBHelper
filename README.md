# DBHelper
Class for SQL databases and prepared statements generation. Seems to be safe.
Unsafe only [[xxxxxx]] labels, because prepared statements isn't supports sql names (tables, fields).
There is at least PDO::quote method to make [[xxxxxx]] more safe, it said to be theoretically safe in php docs.
I'm using it only for whitelist values, so there is no test for vulnerability of [[xxxxxx]] labels, but {{xxxxxx}} works safe.

WARNING! Code needs to be refactored and also contains var_dumps, only for educational purposes.
Also, there is only russian comments.

For test you can work with commented code in the end of file.
There is database tables creation, inserts and some other basic things.

How to use:

1.Create object of this class.
$db = new DBHelper();

2.Connect to SQL db, there is only SQLite for now.
$pdo=$db->ConnectSQLite('test2.sqlite');


3.[[xxxxxx]] For Names in SQL - sanitize and use whitelists, it built with PDO::quote and can be unsafe.
$pdo and $db used as Reference to previously created objects.
DBHelper::FastSafeQuery handle prepared statements with bindings and types.

$stmt=$db->FastSafeQuery($pdo, $db, "INSERT INTO [[table]] {{insert:VALUES}};",
[ 
	'insert'=>['author'=>['PDO::PARAM_STR','new'],'post_id'=>['PDO::PARAM_STR','new']], 'table'=>['PDO::PARAM_STR','posts'], 
	'test2'=>['PDO::PARAM_STR','posts']
]);

4.To fetch data you can use some default code like this:
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
	
	$out_counter+=1;
}
