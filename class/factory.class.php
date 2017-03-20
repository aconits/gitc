<?php

class Factory
{
    public $id;
    public $player;
    public $cyborgsCount;
    public $productionCount;
    public $roundLeftToProduct;
    public $arg5;

    public $distMatrix=array();
    public $TTroop=array();
    public $cyborgsCountAlreadyUsed;

	public $bomb_is_coming;
    
    function __construct($id,$player,$cyborgsCount,$productionCount,$roundLeftToProduct,$arg5)
    {
        $this->id = $id;
        $this->update($player,$cyborgsCount,$productionCount,$roundLeftToProduct,$arg5);
    }
	
    function update($player,$cyborgsCount,$production,$roundLeftToProduct,$arg5)
    {
        $this->player = $player;
        $this->cyborgsCount = $cyborgsCount;
        $this->productionCount = $production;
        $this->roundLeftToProduct = $roundLeftToProduct;
        $this->arg5 = $arg5;

        $this->cyborgsCountAlreadyUsed = 0;
    }
    
    function sendCyborgs($n)
    {
        if ($this->cyborgsCount < $n) $n = $this->cyborgsCount;

        $this->cyborgsCount -= $n;
        return $n;
    }
    
    function getProduction()
    {
        if ($this->player == 0) return 0;
        else return $this->productionCount;
    }

	function getDistanceFrom(&$fk_target)
    {
        return $this->distMatrix[$this->id][$fk_target];
    }

    // TODO à faire évoluter pour avoir le détail par temps d'arrivé
    function getTCyborgsInComing()
    {
        $TCyborgsCount = array();
        $TTroopByRoundLeft = $this->getTroopsInComing('all');
        foreach ($TTroopByRoundLeft as $roundLeft => &$TTroop) {
            foreach ($TTroop as &$troop) {
                //var_dump($troop);
                if ($troop->fk_factory_target == $this->id) {
                    if (!isset($TCyborgsCount[$roundLeft])) $TCyborgsCount[$roundLeft] = 0;
                    $TCyborgsCount[$roundLeft] += ($troop->player == 1 ? $troop->cyborgsCount : $troop->cyborgsCount*-1);
                }
            }
        }

        // Pas besoin de sort(), $TTroopByRoundLeft est déjà trié
        return $TCyborgsCount;
    }

    function getTroopsInComing($player)
    {
        $TTroop = array();
        foreach ($this->TTroop as &$troop) {
            if ($player == 'all') {
                if ($troop->fk_factory_target == $this->id) $TTroop[$troop->roundLeft][] = $troop;
            } else {
                if ($troop->fk_factory_target == $this->id && $troop->player == $player) $TTroop[$troop->roundLeft][] = $troop;
            }
        }

        sort($TTroop);
        return $TTroop;
    }

}
