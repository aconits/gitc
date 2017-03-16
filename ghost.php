<?php

//$array = array('Alpha', 'Beta', 'Gamma', 'Sigma');
$array = array('Alpha1', 'Beta1');
$array2 = array('Alpha', 'Beta', 'A', 'B', 'C');

// Get Combination
$t1 = uniqueCombination($array);
$t2 = uniqueCombination($array2);
//Sort
sort($t1);
sort($t2);

//Pretty Print
var_dump( $t1, $t2);

function generate_combinations(array $data, array &$all = array(), array $group = array(), $value = null, $i = 0)
{
    $keys = array_keys($data);
    if (isset($value) === true) {
        array_push($group, $value);
    }

    if ($i >= count($data)) {
        array_push($all, $group);
    } else {
        $currentKey     = $keys[$i];
        $currentElement = $data[$currentKey];
        foreach ($currentElement as $val) {
            generate_combinations($data, $all, $group, $val, $i + 1);
        }
    }

    return $all;
}

$data = array(
    $t1,
    $t2
);

$combos = generate_combinations($data);
//print_r($combos);

var_dump($combos);

exit;

//var_dump($TTry);
function uniqueCombination($in, $minLength = 1, $max = 2000) {
    $count = count($in);
    $members = pow(2, $count);
    $return = array();
    for($i = 0; $i < $members; $i ++) {
        $b = sprintf('%0' . $count . 'b', $i);
        $out = array();
        for($j = 0; $j < $count; $j ++) {
            $b{$j} == '1' and $out[] = $in[$j];
        }

        count($out) >= $minLength && count($out) <= $max and $return[] = $out;
    }
    return $return;
}


exit;

// TODO remove
require 'data.php';
require './class/gamemanager.class.php';
require './class/factory.class.php';
require './class/troop.class.php';
require './class/bomb.class.php';
require './class/tools.class.php';

$manager = new GameManager;

//fscanf(STDIN, "%d", $factoryCount); // the number of factories
//fscanf(STDIN, "%d", $linkCount); // the number of links between factories


//for ($i = 0; $i < $linkCount; $i++)
//{
//    fscanf(STDIN, "%d %d %d",$factory1,$factory2,$distance);
//    $manager->addLink($factory1, $factory2, $distance);
//
//    //error_log(',\''.$factory1.' '.$factory2.' '.$distance.'\'');
//}

foreach ($TLink as $s)
{
    list($factory1, $factory2, $distance) = explode(' ', $s);
    $manager->addLink($factory1, $factory2, $distance);
}

//error_log(var_export($manager->TFactoryLink[14],true));
//exit;
// game loop
while (TRUE)
{
    GameManager::$roundNumber++;
    $manager->reset();
    //fscanf(STDIN, "%d", $entityCount); // the number of entities (e.g. factories and troops)
    $entityCount = count($TEntity); // TODO remove

    for ($i = 0; $i < $entityCount; $i++)
    {
        //fscanf(STDIN, "%d %s %d %d %d %d %d",$entityId,$entityType,$arg1,$arg2,$arg3,$arg4,$arg5);
        //error_log(',\''.$entityId.' '.$entityType.' '.$arg1.' '.$arg2.' '.$arg3.' '.$arg4.' '.$arg5.'\'');
        // TODO remove
        list($entityId,$entityType,$arg1,$arg2,$arg3,$arg4,$arg5) = explode(' ', $TEntity[$i]);

        if ($entityType == 'FACTORY') $manager->saveFactory($entityId,$arg1,$arg2,$arg3,$arg4,$arg5);
        elseif ($entityType == 'TROOP') $manager->saveTroop($entityId,$arg1,$arg2,$arg3,$arg4,$arg5);
        elseif ($entityType == 'BOMB') $manager->saveBomb($entityId,$arg1,$arg2,$arg3,$arg4,$arg5);
    }

    $manager->init();

    $action = $manager->getAction();

    // Write an action using echo(). DON'T FORGET THE TRAILING \n
    // To debug (equivalent to var_dump): error_log(var_export($var, true));

    // Any valid action, such as "WAIT" or "MOVE source destination cyborgs"
    echo("$action\n");

    // TODO remove
    break;
}






