<?php

class GameManager
{
    public static $distMatrix=array();
    public static $roundNumber = 0;

    public $initLinks = false;
    public $nextTroopId; // Me servira pour créer mes propres Troop temporairement
    public $distanceMax;

    public $mineProduction;
    public $hisProduction;
    public $mineCyborgsAvailable;
    public $hisCyborgsAvailable;

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



    function __construct() {}

    function reset()
    {
        $this->TTroop = array();
        $this->TBomb = array();
    }

    function init()
    {
        $this->initFactoryMatrix();

        $this->initPlayerFactory();
        $this->initPlayerTroop();
        $this->initPlayerBomb();
    }

    private function initFactoryMatrix()
    {
        if ($this->initLinks) return;

        $this->initLinks = true;
        foreach ($this->TFactory as &$factory) {
            $factory->distMatrix = &self::$distMatrix;
        }
    }

    private function initPlayerFactory()
    {
        $this->TMyFactory = $this->TNeuFactory = $this->TAdvFactory = array();
        foreach ($this->TFactory as $fk_factory => &$factory) {
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

        foreach ($this->TMyFactory as &$myFactory) {
            $this->mineCyborgsAvailable += $myFactory->cyborgsCount;
            $this->mineProduction += $myFactory->productionCount - $myFactory->roundLeftToProduct;
        }

        foreach ($this->TAdvFactory as &$advFactory) {
            $this->hisCyborgsAvailable += $advFactory->cyborgsCount;
            $this->hisProduction += $advFactory->productionCount - $advFactory->roundLeftToProduct;
        }
    }

    private function initPlayerTroop()
    {
        $this->nextTroopId = 1;
        $this->TMyTroop = $this->TAdvTroop = array();
        foreach ($this->TTroop as $fk_troop => &$troop) {
            switch ($troop->player) {
                case 1: // MINE
                    $this->TMyTroop[$fk_troop] = &$troop;
                    break;
                case -1: // RIVAL
                    $this->TAdvTroop[$fk_troop] = &$troop;
                    break;
            }

            $this->TFactory[$troop->fk_factory_target]->TTroop[] = &$troop;
            if ($troop->id >= $this->nextTroopId) $this->nextTroopId = $troop->id + 1;
        }
    }

    private function initPlayerBomb()
    {
        $this->TMyBomb = $this->TAdvBomb = array();
        foreach ($this->TBomb as $fk_bomb => &$bomb) {
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


    /**
     * ACTION
     */
    function getAction()
    {
        $TAction = array();

        $TFactoryToCapture = $this->determinatePriority();
        //var_dump('count $TFactoryToCapture == '.count($TFactoryToCapture[0]));

        foreach ($TFactoryToCapture as $player => &$TTarget)
        {
            foreach ($TTarget as &$targetFactory)
            {
                foreach ($this->TMyFactory as $myFactory)
                {
                    if ($myFactory->cyborgsCount == 0 || $myFactory->id == $targetFactory->id) continue;

//                    if ($player == 0) var_dump($targetFactory->id);
                    $shortestPaths = $this->dijkstra->shortestPaths($myFactory->id, $targetFactory->id, array());
                    $path = $shortestPaths[0]; // TODO à faire évoluer si je dois prendre un autre chemin

                    $action = $this->dispatchAttack($myFactory, $targetFactory, $path);
                    if (!empty($action)) $TAction[] = $action;
                }
            }

        }
//var_dump($TAction);

        $action = $this->sendBomb();
        if (!empty($action)) $TAction[] = $action;

        // TODO intégrer la notion INCREASE

        if (empty($TAction)) return 'WAIT';
        else return implode(';', $TAction);
    }

    private function dispatchAttack(&$myFactory, &$targetFactory, &$path)
    {
        //var_dump($myFactory->id.' => '.$targetFactory->id.' ['.implode(' -> ', $path).']');
        $distanceTotal = 0;
        $pathCount = count($path);
        $TCyborgsInComing=array();
        for ($i=0; $i<=$pathCount-2; $i++) {
            list($fk_factory_from, $fk_factory_to) = array_slice($path, $i, 2);
            Tools::arrayMerge($TCyborgsInComing,$this->TFactory[$fk_factory_to]->getTCyborgsInComing()); // TODO à revoir pour le calcul par tour restant
            $distanceTotal += $this->TFactory[$fk_factory_to]->getDistanceFrom($fk_factory_from);
        }

        $cyborgsCountTotal = $targetFactory->getProduction() * $distanceTotal + $targetFactory->cyborgsCount;

        $cyborgsCountInProgress = 0;
        if (!empty($TCyborgsInComing)) {
            foreach ($TCyborgsInComing as $roundLeft => $cyborgsCount) {
                $cyborgsCountInProgress += $cyborgsCount; // $cyborgsCount peut être négatif s'il y a plus d'unitées adverses
            }
        }

        // $cyborgsCountInProgress => Si < 0 alors l'adversaire va la capturer ou j'ai pas envoyé assé de cyborgs
        //                              Si > 0 alors je vais la capturer
        //                              Si == 0 alors j'ai pas envoyé assé de cyborgs
        $cyborgsCountTotal -= $cyborgsCountInProgress;


        // Au tour 1 je dois OS, si non j'envoie ma production ou 3+
        if (GameManager::$roundNumber == 1) $cyborgsToSend = $targetFactory->cyborgsCount + 1;
        elseif ($myFactory->productionCount == 0) $cyborgsToSend = 3;
        else {
            if ($myFactory->cyborgsCount > $myFactory->productionCount) $cyborgsToSend = $myFactory->productionCount + 1;
            else $cyborgsToSend = $myFactory->productionCount;
        }

        return 'MOVE '.$myFactory->id.' '.$targetFactory->id.' '.$cyborgsToSend;
    }

    private function getTNeuFactoryToCapture()
    {
        $TNeuFactory = array();
        foreach ($this->TNeuFactory as &$neuFactory) {
            $distanceFromMe = $this->distanceMax;
            $distanceFromAdv = $this->distanceMax;

            foreach ($this->TMyFactory as &$myFactory) {
                $d = $neuFactory->getDistanceFrom($myFactory->id);
                if ($d < $distanceFromMe) $distanceFromMe = $d;
            }

            foreach ($this->TAdvFactory as &$advFactory) {
                $d = $neuFactory->getDistanceFrom($advFactory->id);
                if ($d < $distanceFromAdv) $distanceFromAdv = $d;
            }


            if ($distanceFromMe < $distanceFromAdv) $TNeuFactory[] = $neuFactory;
        }

        usort($TNeuFactory, array('Tools', 'orderByProduction'));
        return $TNeuFactory;
    }

    private function getTAdvFactoryToCapture()
    {
        // TODO à améliorer pour merge les factory encore "neutre" mais qui sont sur le point d'être capturé
        $TAdvFactory = $this->TAdvFactory;

        usort($TAdvFactory, array('Tools', 'orderByProduction'));
//        foreach ($TAdvFactory as &$f) var_dump($f->id);
//        exit;
        return $TAdvFactory;
    }

    private function determinatePriority()
    {
        $TFactoryToCapture = array(
            0 => $this->getTNeuFactoryToCapture()
            ,1 => $this->getTAdvFactoryToCapture()
        );

        return $TFactoryToCapture;
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
        $TTroop = $this->getAllTroopGoingTo($targetFactory->id, $targetFactory->player);
        foreach ($TTroop as &$troop) $cyborgsCount += $troop->cyborgsCount;

        return $cyborgsCount;
    }

    function getAllTroopGoingTo($fk_factory_target, $player)
    {
        $TTroop = array();
        foreach ($this->TTroop as &$troop) {
            if ($player == 'all') {
                if ($troop->fk_factory_target == $fk_factory_target) $TTroop[$troop->roundLeft][$troop->player][] = $troop;
            } else {
                if ($troop->fk_factory_target == $fk_factory_target && $troop->player == $player) $TTroop[$troop->roundLeft][] = $troop;
            }
        }

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
