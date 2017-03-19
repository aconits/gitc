<?php

class GameManager
{
    public static $percentMine;

    public static $roundNumber = 0;
    public static $maxDistance = 0;
	public static $TAllKeyFactoryCombination=array();

	public static $TLastTargetedByBomb=array();

    public $TFactory = array(); // Contain all factories
    public $TMyFactory = array(); // only mine
    public $TNeuFactory = array();
    public $TAdvFactory = array();

    public $TTroop = array(); // Contain all troops
    public $TMyTroop = array(); // only mine
    public $TAdvTroop = array();

    public $TBomb = array();
    public $TMyBomb = array();
    public $TAdvBomb = array();

    public $initLinks = false;
    public $nextTroopId; // Me servira pour créer mes propres Troop temporairement

    public $TFactoryLink = array();

    function __construct() {}

    function addLink($id_a, $id_b, $distance)
    {
        $this->TFactoryLink[$id_a][] = array('fk_target' => $id_b, 'distance' => $distance);
        $this->TFactoryLink[$id_b][] = array('fk_target' => $id_a, 'distance' => $distance);
    }

    function reset()
    {
        $this->TTroop = array();
        $this->TBomb = array();
    }

    function init()
    {
        $this->initFactoryLink();

        $this->initPlayerFactory();
        $this->initPlayerTroop();
        $this->initPlayerBomb();
    }

    private function initFactoryLink()
    {
        if ($this->initLinks) return;

        $this->initLinks = true;
        foreach ($this->TFactoryLink as $fk_factory => &$Tab)
        {
            foreach ($Tab as &$TInfo) $this->TFactory[$fk_factory]->addLink($this->TFactory[$TInfo['fk_target']], $TInfo['distance']);
        }
    }

    private function initPlayerFactory()
    {
        $this->TMyFactory = $this->TNeuFactory = $this->TAdvFactory = array();
        foreach ($this->TFactory as $fk_factory => &$factory)
        {
            switch ($factory->player) {
                case 1: // MINE
                    $this->TMyFactory[$fk_factory] = &$factory;
                    break;
                case 0: // NEUTRAL
                    $this->TNeuFactory[$fk_factory] = &$factory;
                    break;
                case -1: // RIVAL
                    $this->TAdvFactory[$fk_factory] = &$factory;
                    break;
            }
        }
    }

    private function initPlayerTroop()
    {
        $this->nextTroopId = 1;
        $this->TMyTroop = $this->TAdvTroop = array();
        foreach ($this->TTroop as $fk_troop => &$troop)
        {
            switch ($troop->player) {
                case 1: // MINE
                    $this->TMyTroop[$fk_troop] = &$troop;
                    break;
                case -1: // RIVAL
                    $this->TAdvTroop[$fk_troop] = &$troop;
                    break;
            }

            if ($troop->id >= $this->nextTroopId) $this->nextTroopId = $troop->id + 1;
        }
    }

    private function initPlayerBomb()
    {
        $this->TMyBomb = $this->TAdvBomb = array();
        foreach ($this->TBomb as $fk_bomb => &$bomb)
        {
            switch ($bomb->player) {
                case 1: // MINE
                    $this->TMyBomb[$fk_bomb] = &$bomb;
                    $this->TFactory[$bomb->fk_factory_target]->bomb_is_coming = $bomb->roundLeft;
                    break;
                case -1: // RIVAL
                    $this->TAdvBomb[$fk_bomb] = &$bomb;
                    break;
            }
        }
    }

	
	public static function initAllCombinations(&$sourceDataSet, $subsetSize=4)
	{
		//$start = microtime(true);
		for($i=1; $i<=$subsetSize; $i++)
		{
			self::$TAllKeyFactoryCombination = array_merge(self::$TAllKeyFactoryCombination, Permutation::get($sourceDataSet, $i));
		}
		//echo  (microtime(true) - $start);
	}

    /**
     * ACTION
     */
    function getAction()
    {
        $TAction = array();

        $TFactoryToMove = $this->determinatePriority();
        krsort($TFactoryToMove); // Le meilleur move est en fin tableau

        foreach ($TFactoryToMove as &$Tab)
        {
            $action = $this->dispatchAttack($Tab['from'],$Tab['to']);
            if (!empty($action)) $TAction[] = $action;
        }

        $action = $this->sendBomb();
        if (!empty($action)) $TAction[] = $action;

        // TODO intégrer la notion INCREASE

        if (empty($TAction)) return 'WAIT';
        else return implode(';', $TAction);
    }

    private function dispatchAttack(&$TMyFactoryId,&$TTargetFactoryId)
    {
        $TAction = array();
//var_dump($TTargetFactoryId);
        foreach ($TTargetFactoryId as &$fk_factory_target)
        {
            if ($this->TFactory[$fk_factory_target]->player == 1) $nbCyborgsToSend = $this->getCyborgsCountToProtect($TMyFactoryId,$this->TFactory[$fk_factory_target]);
            else $nbCyborgsToSend = $this->getCyborgsCountToCapture($TMyFactoryId,$this->TFactory[$fk_factory_target], false, true);
//var_dump($fk_factory_target.' => '.$nbCyborgsToSend);
            foreach ($TMyFactoryId as &$fk_factory)
            {
                $nbSend = $this->TMyFactory[$fk_factory]->cyborgsCount;
                if ($nbSend > $nbCyborgsToSend) $nbSend = $nbCyborgsToSend;

                $action = $this->createTroop($this->TMyFactory[$fk_factory],$this->TFactory[$fk_factory_target],$nbSend);
                if (!empty($action)) $TAction[] = $action;

                $nbCyborgsToSend -= $nbSend;
                if ($nbCyborgsToSend == 0) break;
            }
        }

        return implode(';', $TAction);
    }

    private function getCyborgsCountToCapture(&$TMyFactoryId,&$targetFactory,$useAlreadyUsed=false,$debug=false)
    {
        $nbCyborgsToCapture = $targetFactory->cyborgsCount;
        $prod = $targetFactory->player == 0 ? 0 : $targetFactory->productionCount;
        $TTroop = $this->getAllTroopGoingTo($targetFactory->id, 'all');
//        if ($debug)
//        {
//            var_dump(' check id = '.$targetFactory->id.' nb = '.$nbCyborgsToCapture.' count ttroop = '.count($TTroop));
//
//        }
        $lastRoundLeft=0;
        foreach ($TTroop as $nbRoundLeft => &$Tab)
        {
            $nbCyborgsToCapture += $prod * ($nbRoundLeft - $lastRoundLeft);

            $lastRoundLeft = $nbRoundLeft;

            if (isset($Tab[-1])) foreach ($Tab[-1] as &$troop) $nbCyborgsToCapture += $troop->cyborgsCount;
            if (isset($Tab[1])) foreach ($Tab[1] as &$troop) $nbCyborgsToCapture -= $troop->cyborgsCount;
        }

        if ($nbCyborgsToCapture >= 0)
        {
            if ($prod > 0)
            {
                $totalCyborgsAvailable = 0;
                foreach ($TMyFactoryId as &$fk_factory)
                {
                    if ($this->TMyFactory[$fk_factory]->TDistance[$targetFactory->id] > $lastRoundLeft)
                    {
                        $nbCyborgsToCapture += $prod * ($this->TMyFactory[$fk_factory]->TDistance[$targetFactory->id] - $lastRoundLeft);
                        $lastRoundLeft = $this->TMyFactory[$fk_factory]->TDistance[$targetFactory->id];
                    }

                    $tot = $totalCyborgsAvailable + $this->TMyFactory[$fk_factory]->cyborgsCount - $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed;
                    if ($tot > $nbCyborgsToCapture)
                    {
                        $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed += $nbCyborgsToCapture - $totalCyborgsAvailable + 1;
                        return $nbCyborgsToCapture+1;
                    }
                    else
                    {
                        $totalCyborgsAvailable += $this->TMyFactory[$fk_factory]->cyborgsCount - $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed;
                        if ($useAlreadyUsed) $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed += $this->TMyFactory[$fk_factory]->cyborgsCount - $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed;
                    }
                }
            }

            return $nbCyborgsToCapture+1;
        }

        return 0;
    }

    private function getCyborgsCountToProtect(&$TMyFactoryId,$myFactoryTarget,$useAlreadyUsed=false)
    {
        $nbCyborgsToProtect = $myFactoryTarget->cyborgsCount;
        $prod = $myFactoryTarget->productionCount;
        $TTroop = $this->getAllTroopGoingTo($myFactoryTarget->id, 'all');

        $lastRoundLeft=0;
        foreach ($TTroop as $nbRoundLeft => &$Tab)
        {
            $nbCyborgsToProtect += $prod * ($nbRoundLeft - $lastRoundLeft);

            $lastRoundLeft = $nbRoundLeft;

            if (isset($Tab[-1])) foreach ($Tab[-1] as &$troop) $nbCyborgsToProtect -= $troop->cyborgsCount;
            if (isset($Tab[1])) foreach ($Tab[1] as &$troop) $nbCyborgsToProtect += $troop->cyborgsCount;
        }

        if ($nbCyborgsToProtect > 0) return 0;
        else
        {
            $nbCyborgsToProtect = abs($nbCyborgsToProtect);
            $totalCyborgsAvailable = 0;
            foreach ($TMyFactoryId as &$fk_factory)
            {
                if ($this->TMyFactory[$fk_factory]->TDistance[$myFactoryTarget->id] > $lastRoundLeft)
                {
                    $nbCyborgsToProtect += $prod * ($this->TMyFactory[$fk_factory]->TDistance[$myFactoryTarget->id] - $lastRoundLeft);
                    $lastRoundLeft = $this->TMyFactory[$fk_factory]->TDistance[$myFactoryTarget->id];
                }

                $tot = $totalCyborgsAvailable + $this->TMyFactory[$fk_factory]->cyborgsCount - $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed;
                if ($tot > $nbCyborgsToProtect)
                {
                    $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed += $nbCyborgsToProtect - $totalCyborgsAvailable + 1;
                    return $nbCyborgsToProtect+1;
                }
                else
                {
                    $totalCyborgsAvailable += $this->TMyFactory[$fk_factory]->cyborgsCount - $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed;
                    if ($useAlreadyUsed) $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed += $this->TMyFactory[$fk_factory]->cyborgsCount - $this->TMyFactory[$fk_factory]->cyborgsCountAlreadyUsed;
                }
            }
        }

        if ($nbCyborgsToProtect > 0) return $nbCyborgsToProtect;
        return 0;
    }

	private function generateAllCombinations(&$sourceDataSet, $subsetSize=null)
	{
        return Permutation::get($sourceDataSet, $subsetSize);
	}

    private function determinatePriority()
    {
        $TMyKey = array_keys($this->TMyFactory);
        $TMyKeyFactoryCombination = array();

		$max_iteration = min(count($this->TMyFactory), 3);
        for ($i=1; $i<=$max_iteration; $i++)
        {
            $TMyKeyFactoryCombination = array_merge($TMyKeyFactoryCombination, $this->generateAllCombinations($TMyKey, $i));
        }

        $i=0;

		$TBestMove = array();
		foreach ($TMyKeyFactoryCombination as &$TMyFactoryId)
		{
            $bestTotal = 0;
            $bestTargetId = array();
			foreach (GameManager::$TAllKeyFactoryCombination as &$TTargetId)
            {
                if (GameManager::$roundNumber <= 10 && in_array(1, $TTargetId)) continue;

                reset($TTargetId);
                $k = key($TTargetId);

                if ($TMyFactoryId[0] == $TTargetId[$k]) continue;

                $total = $this->calculGain($TMyFactoryId, $TTargetId);

                if ($total > $bestTotal)
                {
                    $bestTotal = $total;
                    $bestTargetId = array('from' => $TMyFactoryId, 'to' => $TTargetId);
                }
            }

            $TBestMove[] = $bestTargetId;
            if ($i > 10) array_shift($TBestMove); // Je ne garde que les 10 derniers mouvement
            $i++;
		}

		return $TBestMove;
    }

    private function calculGain(&$TMyFactoryId, &$TTargetId)
    {
        $total = 0;
        $total_nbCyborgsToSend = 0;
// TODO count le nombre de cyborgs à disposition et le nécessaire, si j'en ai pas assé alors je stop le traitement
        foreach ($TTargetId as &$fk_factory_target)
        {
            if ($this->TFactory[$fk_factory_target]->player == 1) $nbCyborgsToSend = $this->getCyborgsCountToProtect($TMyFactoryId, $this->TFactory[$fk_factory_target], true);
            else $nbCyborgsToSend = $this->getCyborgsCountToCapture($TMyFactoryId, $this->TFactory[$fk_factory_target], true);

            $max_distance_factory = 0;
            foreach ($TMyFactoryId as &$fk_factory)
            {
                // TODO à surveiller
                if ($this->TMyFactory[$fk_factory]->TDistance[$fk_factory_target] > $max_distance_factory) $max_distance_factory = $this->TMyFactory[$fk_factory]->TDistance[$fk_factory_target];
            }

            $total += $this->TFactory[$fk_factory_target]->productionCount * (GameManager::$maxDistance - $max_distance_factory);
            $total_nbCyborgsToSend += $nbCyborgsToSend;
        }
//TODO à surveiller
        return $total;
    }

    function getCyborgsMake($player)
    {
        $nb = 0;

        foreach ($this->TFactory as &$factory)
        {
            if ($factory->player == $player && $factory->roundLeftToProduct == 0) $nb += $factory->cyborgsCount;
        }

        return $nb;
    }

    private function sendBomb()
    {
        $bestFactory = null;
        if (Bomb::$bombCount == 0) return '';

        $maxProduction = 0;
        foreach ($this->TFactory as &$f)
        {
            if ($f->productionCount > $maxProduction) $maxProduction = $f->productionCount;
        }

// TODO déterminer la production la plus élevé et avec mon usine la plus proche :: uniquement si aucune bomb est en cours de route
        foreach ($this->TAdvFactory as &$factory)
        {
            if (!$factory->bomb_is_coming && !isset(self::$TLastTargetedByBomb[$factory->id]) && $factory->productionCount > $maxProduction-1)
            {
                if ($bestFactory === null) $bestFactory = &$factory;
                elseif ($bestFactory->productionCount < $factory->productionCount) $bestFactory = &$factory;
                elseif ($bestFactory->productionCount == $factory->productionCount)
                {
                    if ($bestFactory->cyborgsCount < $factory->cyborgsCount) $bestFactory = &$factory;
                }
            }
        }

        if ($bestFactory !== null)
        {
            $myFactory = $bestFactory->getMyFactoryNearest();
            if (!empty($myFactory))
            {
                self::$TLastTargetedByBomb[$factory->id] = true;

                Bomb::$bombCount--;
                return 'BOMB '.$myFactory->id.' '.$bestFactory->id;
            }
        }

        return '';
    }

    private function createTroop(&$myFactory, &$targetFactory, $nbCyborgsToSend)
    {
        $cyborgs = $myFactory->sendCyborgs($nbCyborgsToSend, true);
        if ($cyborgs > 0)
        {
            $troop = new Troop($this->nextTroopId, 1, $myFactory->id, $targetFactory->id, $cyborgs, $myFactory->getDistance($targetFactory));
            $this->TTroop[$troop->id] = &$troop;
            $this->TMyTroop[$troop->id] = &$troop;
            $this->nextTroopId++;

            return 'MOVE '.$myFactory->id.' '.$targetFactory->id.' '.$cyborgs;
        }

        return '';
    }


    /**
     * FACTORY
     */
    function saveFactory($id,$player,$cyborgsCount,$production,$roundLeftToProduct,$arg5)
    {
        if (!isset($this->TFactory[$id])) $this->addFactory($id,$player,$cyborgsCount,$production,$roundLeftToProduct,$arg5);
        else $this->updateFactory($id,$player,$cyborgsCount,$production,$roundLeftToProduct,$arg5);
    }

    function addFactory($id,$player,$cyborgsCount,$production,$roundLeftToProduct,$arg5)
    {
        $factory = new Factory($id,$player,$cyborgsCount,$production,$roundLeftToProduct,$arg5);
        $this->TFactory[$id] = $factory;
    }

    function updateFactory($id,$player,$cyborgsCount,$production,$roundLeftToProduct,$arg5)
    {
        $this->TFactory[$id]->update($player,$cyborgsCount,$production,$roundLeftToProduct,$arg5);
    }


    /**
     * TROOP
     */
    function saveTroop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft)
    {
        if (!isset($this->TTroop[$id])) $this->addTroop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft);
        //else $this->updateTroop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft);
    }

    function addTroop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft)
    {
        $troop = new Troop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft);
        $this->TTroop[$id] = $troop;
    }

    function updateTroop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft)
    {
        $this->TTroop[$id]->update($player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft);
    }


    function getCyborgsIsComing(&$targetFactory)
    {
        $cyborgsCount = 0;
        $TTroop = $this->getAllTroopGoingTo($targetFactory->id, 1);
        foreach ($TTroop as &$troop) $cyborgsCount += $troop->cyborgsCount;

        return $cyborgsCount;
    }

    function getAllTroopGoingTo($fk_factory_target, $player)
    {
        $TTroop = array();

        foreach ($this->TTroop as &$troop)
        {
            if ($player == 'all')
            {
                if ($troop->fk_factory_target == $fk_factory_target) $TTroop[$troop->roundLeft][$troop->player][] = $troop;
            }
            else
            {
                if ($troop->fk_factory_target == $fk_factory_target && $troop->player == $player) $TTroop[$troop->roundLeft][] = $troop;
            }
        }

        //usort($TTroop, array('Troop', 'cmp_troop'));
        sort($TTroop);

        return $TTroop;
    }


    /**
     * BOMB
     */
    function saveBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5)
    {
        if (!isset($this->TBomb[$id])) $this->addBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
        //else $this->updateBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
    }

    function addBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5)
    {
        $bomb = new Bomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
        $this->TBomb[$id] = &$bomb;
    }

    function updateBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5)
    {
        $this->TBomb[$id]->update($player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
    }


}
