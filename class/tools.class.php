<?php

class Tools
{
    static public $factoryFrom;

    static function orderByPriority(&$factory_a, &$factory_b)
    {
        if ($factory_a->priority > $factory_b->priority) return -1; // je remonte "a"
        elseif ($factory_a->priority < $factory_b->priority) return 1; // je remonte "b"

        // Priority egale...
        elseif ($factory_a->player == 0 && $factory_b->player == 0) // 2 usines neutres
        {
            if ($factory_a->productionCount > $factory_b->productionCount) return -1; // favorise la prise pour une production plus élevée
            else return 1;
        }
        elseif ($factory_a->player == 0 && $factory_b->player == -1) return -1; // Favorise l'usine neutre
        elseif ($factory_a->player == -1 && $factory_b->player == 0) return 1;

        // TODO compléter les tests suivants
        elseif ($factory_a->player == 1) return 1; // "a" est à moi je priorise l'autre
        elseif ($factory_b->player == 1) return -1;

		elseif ($factory_a->player == -1 && $factory_b->player == -1)
		{
			if ($factory_a->cyborgsCount > $factory_b->cyborgsCount) return 1;
			else return -1;
		}
		
        return 0;
    }

    static function orderByDistance(&$factoryFrom, &$TMyFactory)
    {
        self::$factoryFrom = $factoryFrom;
        usort($TMyFactory, array('Tools', 'orderByDistanceAction'));
    }

    static function orderByDistanceAction(&$factory_a, &$factory_b)
    {
        $d_a = self::$factoryFrom->getDistance($factory_a);
        $d_b = self::$factoryFrom->getDistance($factory_b);

        if ($d_a < $d_b) return -1;
        elseif ($d_a > $d_b) return 1;

        // Distance égale, je test le nombre de cyborgs
        elseif ($factory_a->cyborgsCount > $factory_b->cyborgsCount) return -1;
        elseif ($factory_a->cyborgsCount < $factory_b->cyborgsCount) return 1;

        return 0;
    }

    static function getPointFromProduction(&$factory)
    {
        $point = 0;
        switch ($factory->productionCount) {
            case 1: $point = 0.5; break;
            case 2: $point = 1; break;
            case 3: $point = 2; break;
        }

        return $point;
    }

    // TODO check priority
    static function getPointFromPlayerProximity(&$factory,$coef=1)
    {
        $nearestFactory = $factory->getPlayerFactoryNearest();

        if (!is_object($nearestFactory))
        {
            if ($nearestFactory == 0) return 0.5*$coef; // distance egale entre moi et l'ennemi
            else return -2*$coef; // $nearestFactory == -1  => cas particulier des 1ers tours
        }
        else
        {
            if ($nearestFactory->player == 1) return 2*$coef;
            else return -0.5*$coef;
        }
    }

    static function getPointFromTroopIsComing(&$factory, &$TTroop)
    {
        $cyborgsCountIsComing = 0;

        foreach ($TTroop as $nbRoundLeft => &$troop)
        {
            $cyborgsCountIsComing += $troop->cyborgsCount;
        }

        if ($factory->cyborgsCount < $cyborgsCountIsComing) $factory->priority -= 500;
        else return 1;
    }

    static function getPointFromWillBeCaptured(&$factory, &$TTroop)
    {
        $cyborgsIsComing = 0;

        foreach ($TTroop as &$troop)
        {
            if ($troop->player == 1) $cyborgsIsComing += $troop->cyborgsCount;
            else $cyborgsIsComing -= $troop->cyborgsCount;
        }

        if ($cyborgsIsComing > $factory->cyborgsCount) return 0;
        else return 3;
    }
}