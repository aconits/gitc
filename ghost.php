<?php

// TODO remove
require 'data.php';
require './class/gamemanager.class.php';
require './class/factory.class.php';
require './class/troop.class.php';
require './class/bomb.class.php';
require './class/tools.class.php';
require './class/permutation.class.php';

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
$TFactId=array();
foreach ($TLink as $s)
{
    list($factory1, $factory2, $distance) = explode(' ', $s);
    $manager->addLink($factory1, $factory2, $distance);
	
	$TFactId[$factory1] = $factory1;
	$TFactId[$factory2] = $factory2;
}

GameManager::initAllCombinations($TFactId); // Pars du principe que toutes les usines sont liés entre elles
unset($TFactId);

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






