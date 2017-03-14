<?php

class Troop
{
    public $id;
    public $player;
    public $fk_factory_start;
    public $fk_factory_target;
    public $cyborgsCount;
    public $roundLeft;
    
    function __construct($id,$player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft)
    {
        $this->id = $id;
        $this->update($player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft);
    }
    
    function update($player,$fk_factory_start,$fk_factory_target,$cyborgsCount,$roundLeft)
    {
        $this->player = $player;
        $this->fk_factory_start = $fk_factory_start;
        $this->fk_factory_target = $fk_factory_target;
        $this->cyborgsCount = $cyborgsCount;
        $this->roundLeft = $roundLeft;
    }
    
    static function cmp_troop($a, $b)
    {
        if ($a->roundLeft == $b->roundLeft) return 0;
        
        return ($a->roundLeft > $b->roundLeft) ? 1 : -1;
    }
}


