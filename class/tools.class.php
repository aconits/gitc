<?php

class Tools
{
    static public $factoryFrom;

    static function orderByDistance(&$factoryFrom, &$TMyFactory)
    {
        self::$factoryFrom = $factoryFrom;
        usort($TMyFactory, array('Tools', 'orderByDistanceAction'));
    }

    static function orderByDistanceAction(&$factory_a, &$factory_b)
    {
        $d_a = self::$factoryFrom->getDistanceFrom($factory_a->id);
        $d_b = self::$factoryFrom->getDistanceFrom($factory_b->id);

        if ($d_a < $d_b) return -1;
        elseif ($d_a > $d_b) return 1;

        // Distance égale, je test le nombre de cyborgs
        elseif ($factory_a->cyborgsCount > $factory_b->cyborgsCount) return -1;
        elseif ($factory_a->cyborgsCount < $factory_b->cyborgsCount) return 1;

        return 0;
    }

    static function orderByProduction(&$f_a, &$f_b)
    {
        if ($f_a->productionCount > $f_b->productionCount) return -1;
        elseif ($f_a->productionCount < $f_b->productionCount) return 1;

        elseif ($f_a->cyborgsCount < $f_b->cyborgsCount) return -1;
        else return 1;
    }

    static function arrayMerge(&$TCyborgsInComing,$TCyborgsInComingToMerge)
    {
        if (empty($TCyborgsInComing)) $TCyborgsInComing = $TCyborgsInComingToMerge;
        else {
            foreach ($TCyborgsInComingToMerge as $roundLeft => $cyborgsCount) {
                $TCyborgsInComing[$roundLeft] += $cyborgsCount;
            }
        }
    }
}