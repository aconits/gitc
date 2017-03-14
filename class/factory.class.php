<?php

class Factory
{
    public $id;
    public $player;
    public $cyborgsCount;
    public $productionCount;
    public $roundLeftToProduct;
    public $arg5;
    
	private $TLink;
	public $TDistance;
	public $priority;

	public $bomb_is_coming;
	
    public $minimal_cyborgsCount;
    
    function __construct($id,$player,$cyborgsCount,$productionCount,$roundLeftToProduct,$arg5)
    {
        $this->id = $id;
		$this->TLink = array();
        $this->update($player,$cyborgsCount,$productionCount,$roundLeftToProduct,$arg5);
    }
	
    function update($player,$cyborgsCount,$production,$roundLeftToProduct,$arg5)
    {
        $this->player = $player;
        $this->cyborgsCount = $cyborgsCount;
        $this->productionCount = $production;
        $this->roundLeftToProduct = $roundLeftToProduct;
        $this->arg5 = $arg5;

        $this->priority = 0;
        $this->minimal_cyborgsCount = 0;
    }

    function addLink(&$factory, $distance)
    {
        $this->TLink[$factory->id] = &$factory;
        $this->TDistance[$factory->id] = $distance;
    }
    
    function sendCyborgs($n, $force=false)
    {
        if ($force || $this->cyborgsCount - $n >= $this->minimal_cyborgsCount)
        {
            $this->cyborgsCount -= $n;
            return $n;
        }
        
        return 0;
    }
    
    function getProdution()
    {
        if ($this->player == 0) return 0;
        else return $this->productionCount;
    }
	
	function getDistance(&$targetFactory)
	{
		return !empty($this->TDistance[$targetFactory->id]) ? $this->TDistance[$targetFactory->id] : false;
	}

	function getAdvFactoryAround() // RIVAL
    {
        return $this->getFactoryAround(-1);
    }
    
    function getNeuFactoryAround() // NEUTRAL
    {
        return $this->getFactoryAround(0);
    }
    
    function getMyFactoryAround() // MINE
    {
        return $this->getFactoryAround(1);
    }
	
    private function getFactoryAround($player)
    {    
        $TFactory = array();
        
        foreach ($this->TLink as &$factory)
        {
            if ($factory->player == $player) $TFactory[] = $factory;
        }
        
        return $TFactory;
    }
	
	public function getAdvFactoryNearest($option='')
	{
		return $this->getFactoryNearest(-1,$option);
	}
	
	public function getNeuFactoryNearest($option='')
	{
		return $this->getFactoryNearest(0,$option);
	}
	
	public function getMyFactoryNearest($option='')
	{
		return $this->getFactoryNearest(1,$option);
	}
	
	private function getFactoryNearest($player,$option='')
    {
        $factoryNearest = null;
        $distance = null;

        foreach ($this->TLink as &$factory)
        {
            if ($factory->player == $player)
            {
                $d = $this->getDistance($factory);
                if ($d !== false && ($d < $distance || $distance === null))
                {
                    $factoryNearest = $factory;
                    $distance = $d;
                }

                if ($option == 'testCyborgsCountOnEquals')
                {
                    if ($d == $distance && $factoryNearest->cyborgsCount < $factory->cyborgsCount)
                    {
                        $factoryNearest = $factory;
                        $distance = $d;
                    }
                }
            }
        }
        
        return $factoryNearest;
    }

    /**
     * Cas particulier dans les 1er tours, ou chaque adversaire possède 1 seule usine chacun
     */
    public function getPlayerFactoryNearest()
    {
        $myFactory = $this->getMyFactoryNearest();
        $advFactory = $this->getAdvFactoryNearest();

        if (!is_null($myFactory) && !is_null($advFactory))
        {
            $delta = $myFactory->getDistance($this) - $advFactory->getDistance($this);
            if ($delta < 0) return $myFactory;
            elseif ($delta > 0) return $advFactory;
            return 0;
        }

        return -1;
    }

    public function getTMyFactoryNearestWithQty($minimalCyborgs)
    {
        $totalCyborgs = 0;
        $TMyFactoryNearestWithQty = array();
        $TMyFactory = $this->getMyFactoryAround();
        Tools::orderByDistance($this, $TMyFactory);

        if ($this->player == 1 && $this->cyborgsCount > 0)
        {
            $totalCyborgs += $this->cyborgsCount;
        }

        foreach ($TMyFactory as $i => &$factory)
        {
            if ($totalCyborgs >= $minimalCyborgs) break;

            if ($factory->cyborgsCount > $minimalCyborgs)
            {
                $totalCyborgs += $factory->cyborgsCount;
                $TMyFactoryNearestWithQty[] = $factory;
            }

        }

        return $TMyFactoryNearestWithQty;
    }
}
