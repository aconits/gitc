<?php

class Bomb
{
    public static $bombCount = 2;

    public $id;
    public $player;
    public $fk_factory_start;
    public $fk_factory_target;
    public $roundLeft;
    public $arg5;
    
    function __construct($id,$player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5)
    {
        $this->id = $id;
        $this->update($player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5);
    }
    
    function update($player,$fk_factory_start,$fk_factory_target,$roundLeft,$arg5)
    {
        $this->player = $player;
        $this->fk_factory_start = $fk_factory_start;
        $this->fk_factory_target = $fk_factory_target;
        $this->roundLeft = $roundLeft;
        $this->arg5 = $arg5;
    }
}