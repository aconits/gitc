<?php

class GameManager
{
    public static $waitCount = 0;

    public static $roundNumber = 0;
    public static $TLastTargetedByBomb = array();

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
    public $nextTroopId;

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
        $this->initProduction();
        $this->initPlayerTroop();
        $this->initPlayerBomb();
    }

    private function initFactoryLink()
    {
        if (!$this->initLinks)
        {
            $this->initLinks = true;
            foreach ($this->TFactoryLink as $fk_factory => &$Tab)
            {
                foreach ($Tab as &$TInfo)
                {
                    $this->TFactory[$fk_factory]->addLink($this->TFactory[$TInfo['fk_target']], $TInfo['distance']);
                }
            }
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

    private function initProduction()
    {
        $this->productionMax = $this->productionNeu = $this->productionMine = $this->productionAdv = 0;
        foreach ($this->TFactory as &$factory)
        {
            $production = empty($factory->roundLeftToProduct) ? $factory->productionCount : 0;

            if ($factory->player == 1) $this->productionMine += $production;
            elseif ($factory->player == -1) $this->productionAdv += $production;
            else $this->productionNeu += $production;

            $this->productionMax += $production;
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


    /**
     * ACTION
     */
    function getAction()
    {
        $TAction = array();

        $this->determinatePriority();

        $TFactoryPriority = array_merge($this->TNeuFactory, $this->TMyFactory, $this->TAdvFactory);
        usort($TFactoryPriority, array('Tools', 'orderByPriority'));

        $action = $this->sendBomb();
        if (!empty($action)) $TAction[] = $action;

        foreach ($TFactoryPriority as &$factory)
        {
            $skip = false;
            if ($factory->bomb_is_coming > 0)
            {
                $cyborgsCountIsComing = $this->getCyborgsIsComing($factory);

                if ($cyborgsCountIsComing < 12) // TODO à arbitrer
                {
                    // TODO envoyer des troops à l'attaque dans le bon timing
                    $TMyFactory = $factory->getMyFactoryAround($factory);
                    foreach ($TMyFactory as &$myFactory)
                    {
                        if ($myFactory->getDistance($factory) > $factory->bomb_is_coming)
                        {
                            $toSend = 2;
                            if ($myFactory->cyborgsCount > 0)
                            {
                                $action = $this->createTroop($myFactory, $factory, $toSend); // Return "MOVE [id] [id] [nbCyborgs]" OU une chaine vide
                                if (!empty($action))
                                {
                                    $skip = true;
                                    $TAction[] = $action;
                                }
                            }
                        }
                    }
                }
            }


            if (!$skip)
            {
                if (GameManager::$roundNumber == 1) $nbCyborgsToSend = $factory->cyborgsCount+1;
                else $nbCyborgsToSend = ceil($factory->cyborgsCount * 0.3); // TODO à déterminer pour éviter que toutes mes usines flood la même cible


                $TMyFactory = $factory->getTMyFactoryNearestWithQty($nbCyborgsToSend);


                foreach ($TMyFactory as &$myFactory)
                {
                    if($factory->id == $myFactory->id)
                    {
                        $nbCyborgsToSend -= $myFactory->cyborgsCount;
                    }
                    else
                    {
                        if ($nbCyborgsToSend <= $factory->cyborgsCount) $toSend = $factory->cyborgsCount;
                        else $toSend = $nbCyborgsToSend;

                        $nbCyborgsToSend -= $toSend;

                        $action = $this->createTroop($myFactory, $factory, $toSend); // Return "MOVE [id] [id] [nbCyborgs]" OU une chaine vide
                        if (!empty($action)) $TAction[] = $action;
                    }

                    // TODO à vérifier mais normalement pas besoin de break si le reste à envoyer vos 0 car $TMyFactory contient se qu'il faut
                }
            }
        }

        if (empty($TAction)) return 'WAIT';
        else return implode(';', $TAction);
    }

    private function determinatePriority()
    {
        $nbNeu = count($this->TNeuFactory);
        $nbMine = count($this->TMyFactory);
        $nbAdv = count($this->TAdvFactory);

        // TODO déterminer les usines les plus produtif qui vont être capturer par l'ennemie

        $bonus_multiplicateur = 1;
        if ($nbNeu > $nbMine + $nbAdv) $bonus_multiplicateur = 2;

        // TODO dispatch TO neutral
        foreach ($this->TNeuFactory as &$factory)
        {
            $factory->priority += Tools::getPointFromProduction($factory);
            $factory->priority += Tools::getPointFromPlayerProximity($factory, $bonus_multiplicateur);

            //$factory->priority += $bonus; // TODO check si extra prio necessaire
            // TODO peut etre ajouter des points en fonction du nombre de troop en route
            $factory->priority = Tools::getPointFromWillBeCaptured($factory, $this->TTroop);

            if ($factory->bomb_is_coming) $factory->priority += 500; // high priority car traitement spécifique
            else
            {
                $TTroop = $this->getAllTroopGoingTo($factory->id, 1);
                $factory->priority += Tools::getPointFromTroopIsComing($factory, $TTroop);
            }
        }


        foreach ($this->TAdvFactory as &$factory)
        {
            $factory->priority += Tools::getPointFromProduction($factory);
            $factory->priority += Tools::getPointFromPlayerProximity($factory);

            // TODO method pour savoir le total distance de mes usines est le plus faible
            // Tools::getPointFromTotalDistance($factory, 1);

            // TODO method pour diminuer la priority si je suis sur le point de la capturer
            $factory->priority = Tools::getPointFromWillBeCaptured($factory, $this->TTroop);

            if ($factory->bomb_is_coming) $factory->priority += 500; // high priority car traitement spécifique

            $TTroop = $this->getAllTroopGoingTo($factory->id, 1);
            $factory->priority += Tools::getPointFromTroopIsComing($factory, $TTroop);
        }

        // TODO write loop pour déterminer la priority de mes usines à défendre
        /*foreach ($this->TMyFactory as &$factory)
        {
            // TODO calculer le nombre de troop en route pour pour stop l'envoi et prioriser l'attaque
            if ($factory->bomb_is_coming && true) $factory->priority += 500; // high priority car traitement spécifique
        }*/
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
        $Troop = new Troop($this->nextTroopId, 1, $myFactory->id, $targetFactory->id, $nbCyborgsToSend, $myFactory->getDistance($targetFactory));
        $this->nextTroopId++;
        $cyborgs = $myFactory->sendCyborgs($nbCyborgsToSend, true);
        if ($cyborgs > 0) return 'MOVE '.$myFactory->id.' '.$targetFactory->id.' '.$cyborgs;

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
        else $this->updateTroop($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft);
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
            if ($troop->fk_factory_target == $fk_factory_target && $troop->player == $player)
            {
                $TTroop[] = $troop;
            }
        }

        usort($TTroop, array("Troop", "cmp_troop"));

        return $TTroop;
    }


    /**
     * BOMB
     */
    function saveBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5)
    {
        if (!isset($this->TBomb[$id])) $this->addBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
        else $this->updateBomb($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
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
