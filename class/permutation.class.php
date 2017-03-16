<?php
/** source: https://github.com/alphazygma/Combinatorics */

class Permutation
{
    protected $_combination;

    public function __construct()
    {
        $this->_combination = new Combination();
    }

    public static function get(array $sourceDataSet, $subsetSize = null)
    {
        $combination = new static($sourceDataSet, $subsetSize);
        return $combination->getPermutations($sourceDataSet, $subsetSize);
    }

    public function getPermutations(array $sourceDataSet, $subsetSize = null)
    {
        $combinationMap = $this->_combination->getCombinations($sourceDataSet, $subsetSize);

        $permutationsMap = [];
        foreach ($combinationMap as $combination) {
            $permutationsMap = array_merge(
                $permutationsMap,
                $this->_findPermutations($combination)
            );
        }

        return $permutationsMap;
    }

    private function _findPermutations($combination)
    {
        if (count($combination) <= 1) {
            return [$combination];
        }
        $permutationList = [];
        $startKey = $this->_processSubPermutations($combination, $permutationList);

        $key = key($combination);
        while ($key != $startKey) {
            $this->_processSubPermutations($combination, $permutationList);
            $key = key($combination);
        }
        return $permutationList;
    }

    private function _processSubPermutations(&$combination, &$permutationList)
    {
        list($shiftedKey, $shiftedVal) = $this->_arrayShiftAssoc($combination);
        $subPermutations = $this->_findPermutations($combination);
        foreach ($subPermutations as $permutation) {
            $permutationList[] = array_merge([$shiftedKey => $shiftedVal], $permutation);
        }

        $combination[$shiftedKey] = $shiftedVal;

        reset($combination);

        return $shiftedKey;
    }

    private function _arrayShiftAssoc(array &$array)
    {
        if (empty($array)) {
            return null;
        }

        reset($array);

        $firstKey   = key($array);
        $firstValue = current($array); // equivalent to $array[$firstKey]

        unset($array[$firstKey]);

        return [$firstKey, $firstValue];
    }
}

class Combination
{
    public static function get(array $sourceDataSet, $subsetSize = null)
    {
        $combination = new static($sourceDataSet, $subsetSize);
        return $combination->getCombinations($sourceDataSet, $subsetSize);
    }

    public function getCombinations(array $sourceDataSet, $subsetSize = null)
    {
        if (isset($subsetSize)) {
            return $this->_getCombinationSubset($sourceDataSet, $subsetSize);
        }

        $masterCombinationSet = [];

        $sourceDataSetLength = count($sourceDataSet);
        for ($i = 1; $i <= $sourceDataSetLength; $i++) {
            $combinationSubset    = $this->_getCombinationSubset($sourceDataSet, $i);
            $masterCombinationSet = array_merge($masterCombinationSet, $combinationSubset);
        }

        return $masterCombinationSet;
    }

    protected function _getCombinationSubset(array $sourceDataSet, $subsetSize)
    {
        if (!isset($subsetSize) || $subsetSize < 0) {
            exit('Subset size cannot be empty or less than 0');
        }

        $sourceSetSize = count($sourceDataSet);
        if ($subsetSize >= $sourceSetSize) {
            return [$sourceDataSet];

        } else if ($subsetSize == 1) {
            return array_chunk($sourceDataSet, 1, true);

        } else if ($subsetSize == 0) {
            return [];
        }
        $combinations = [];
        $setKeys      = array_keys($sourceDataSet);
        $pointer = new Pointer($sourceDataSet, $subsetSize);
        do {
            $combinations[] = $this->_getCombination($sourceDataSet, $setKeys, $pointer);
        } while ($pointer->advance());
        return $combinations;
    }

    private function _getCombination($sourceDataSet, $setKeyList, Pointer $pointer)
    {
        $combination = array();
        $indexPointerList = $pointer->getPointerList();
        foreach ($indexPointerList as $indexPointer) {
            $namedKey = $setKeyList[$indexPointer];

            $combination[$namedKey] = $sourceDataSet[$namedKey];
        }
        return $combination;
    }
}


class Pointer
{
    private $_indexPointerList;
    private $_sourceSetMaxIndex;
    private $_subsetMaxIndex;

    public function __construct($sourceDataSet, $subsetSize)
    {
        $namedKeyList = array_keys($sourceDataSet);

        $positionalKeyList = array_keys($namedKeyList);

        $this->_indexPointerList  = array_slice($positionalKeyList, 0, $subsetSize);
        $this->_sourceSetMaxIndex = count($sourceDataSet) - 1;
        $this->_subsetMaxIndex    = $subsetSize - 1;
    }

    public function getPointerList()
    {
        return $this->_indexPointerList;
    }

    public function advance()
    {
        return $this->_advanceDelegate($this->_subsetMaxIndex, $this->_sourceSetMaxIndex);
    }

    private function _advanceDelegate($subsetLastIndex, $workingSetMaxIndex)
    {
        if ($subsetLastIndex < 0) {
            return false;
        }
        if ($this->_indexPointerList[$subsetLastIndex] < $workingSetMaxIndex) {
            $this->_indexPointerList[$subsetLastIndex]++;
            return true;
        }

        $subsetSecondLastIndex  = $subsetLastIndex - 1;
        $reducedWorkingMaxIndex = $workingSetMaxIndex - 1;

        if ($this->_advanceDelegate($subsetSecondLastIndex, $reducedWorkingMaxIndex)) {
            $this->_indexPointerList[$subsetLastIndex] = $this->_indexPointerList[$subsetSecondLastIndex] + 1;
            return true;
        }

        return false;
    }
}
/*
$sourceDataSet = array('A', 'B', 'C');

// Retrieve all combinations as Utility
$permtuationList = Permutation::get($sourceDataSet);

// Retrieve all combinations as instance class
$permutation      = new Permutation();
$permutationsList = $permutation->getPermutations($sourceDataSet);

var_dump($permutationsList);
*/