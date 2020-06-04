<?php

class CountSS {

    // CONSTANTS
    const CONSONANTS_MULTI = 1;
    const VOWEL_MULTI = 1.5;
    const COMMON_FACTOR = 1.5;
    const NO_COMMON_FACTOR = 1;

    const SS_POINTS = 'ssPoints';
    const DRIVER_KEY = 'driverKey';
    // CONSTANTS - End

    /**
     * @var array
     */
    private $streets;

    /**
     * @var array
     */
    private $drivers;

    /**
     * @var array
     */
    private $baseMatrix;
    /**
     * @var array
     */
    private $costMatrix;
    /**
     * @var float
     */
    private $maxMatrixValue;
    /**
     * @var array
     */
    private $combined;

    /**
     * @param array $args
     */
    public function main(array $args)
    {
        $this->processArgs($args);
        $this->createBaseMatrix();
        $this->hungarianAlgorithm();
        $this->printResults();
    }

    /**
     * @param array $args
     */
    private function processArgs(array $args)
    {
        if (!isset($args[1]) || !isset($args[2])) $this->instruction();
        //Load data from files:
        $this->streets = $this->getFileContent($args[1]);
        $this->drivers = $this->getFileContent($args[2]);
    }

    private function createBaseMatrix()
    {
        foreach($this->streets as $streetKey => $street) {
            foreach($this->drivers as $driverKey => $driver) {
                //Get proper multiplier if Common Factor exists or not:
                $commonFactor = $this->getCommonFactorMultiplier($street, $driver);

                $streetNameLength = strlen($this->justLetters($street));

                //Base Matrix:
                $this->baseMatrix[$streetKey][$driverKey] =
                    ($this->isEven($streetNameLength))
                        ? $this->getVowelsPoints($street) * $commonFactor
                        : $this->getConsonantsPoints($street) * $commonFactor;
            }
        }
    }

    //Hungarian Algorithm:
    private function hungarianAlgorithm()
    {
        //Clone array Base Array:
        $this->costMatrix = array_merge($this->baseMatrix);

        //Hungarian Algorithm steps:
        $this->makeAllCostMatrixElementsNegative();
        $this->setMaxValueFromMatrix();
        $this->makeAllCostMatrixElementsNotNegativeByAddingMaxBaseMatrixValue();
        $this->CostMatrixSubtractRowMinimumFromEachRow();
        $this->CostMatrixSubtractColumnMinimumFromEachColumn();
        $this->getPositionsForOptimalValuesFromCostMatrix();
    }

    private function makeAllCostMatrixElementsNegative()
    {
        $this->costMatrix = array_map(
            function($array) {
                return array_map(
                    function($value) {
                        return  -$value;
                    } , $array);
            }
            , $this->costMatrix
        );
    }

    private function setMaxValueFromMatrix()
    {
        array_walk($this->baseMatrix,
            function($array) {
                array_walk($array,
                    function($item) {
                        if ($item > $this->maxMatrixValue) $this->maxMatrixValue = $item;
                    });
            });
    }

    private function makeAllCostMatrixElementsNotNegativeByAddingMaxBaseMatrixValue()
    {
        $this->costMatrix = array_map(
            function($array) {
                return array_map(
                    function($value) {
                        return  $value + $this->maxMatrixValue;
                    } , $array);
            }
            , $this->costMatrix
        );
    }

    private function CostMatrixSubtractRowMinimumFromEachRow()
    {
        $this->costMatrix = array_map(
            function($array) {
                $minValue = min($array);
                foreach($array as $key => $value) {
                    $array[$key] = $value - $minValue;
                }
                return $array;
            }
            , $this->costMatrix
        );
    }

    private function CostMatrixSubtractColumnMinimumFromEachColumn()
    {
        foreach(array_keys($this->costMatrix[0]) as $columnNo) {
            $columnArray = (array_column($this->costMatrix, $columnNo));
            $minColumnValue = min($columnArray);
            foreach($columnArray as $valueKey => $value) {
                $this->costMatrix[$valueKey][$columnNo] = $value - $minColumnValue;
            }
        }
    }

    private function getPositionsForOptimalValuesFromCostMatrix()
    {
        $streetKey = 0;
        while(!empty($this->costMatrix)) {
            $minValueInRow = min($this->costMatrix[$streetKey]);
            $driverKey = array_search($minValueInRow, $this->costMatrix[$streetKey]);

            $this->addToCombined($streetKey, $driverKey, $this->baseMatrix[$streetKey][$driverKey]);

            //Removed assigned resources:
            $this->removeAssignedResources($driverKey, $streetKey);

            $streetKey++;
        }
    }

    /**
     * @param $driverKey
     * @param $streetKey
     */
    private function removeAssignedResources($driverKey, $streetKey)
    {
        //Remove column elements (driver):
        foreach ($this->costMatrix as $lineIdx => $line) {
            unset($this->costMatrix[$lineIdx][$driverKey]);
        }

        //Remove row (street):
        unset($this->costMatrix[$streetKey]);
    }

    //Hungarian Algorithm - end

    /**
     * @param string $street
     * @param string $driver
     * @return float|int
     */
    private function getCommonFactorMultiplier(string $street, string $driver)
    {
        return ( gmp_gcd(strlen($street), strlen($driver)) > 1 ) ? self::COMMON_FACTOR : self::NO_COMMON_FACTOR;
    }

    /**
     * @param int $number
     * @return bool
     */
    private function isEven(int $number): bool
    {
        return (!($number % 2)) ? true : false;
    }

    /**
     * @param string $fileName
     * @return array
     */
    private function getFileContent(string $fileName)
    {
        return explode("\n", file_get_contents($fileName));
    }

    /**
     * @param int $streetKey
     * @param int $driverKey
     * @param float $ssPoints
     */
    private function addToCombined(int $streetKey, int $driverKey, float $ssPoints)
    {
        $this->combined[$streetKey] = [
            self::DRIVER_KEY => $driverKey,
            self::SS_POINTS => $ssPoints
        ];
    }

    /**
     * @param $name
     * @return false|int
     */
    private function getConsonantsPoints($name)
    {
        return preg_match_all('/[^aeiouAEIOU]/i', $this->justLetters($name)) * self::CONSONANTS_MULTI;
    }

    /**
     * @param $name
     * @return false|int
     */
    private function getVowelsPoints($name)
    {
        return preg_match_all('/[aeiouAEIOU]/i', $this->justLetters($name)) * self::VOWEL_MULTI;
    }

    /**
     * @param string $name
     * @return string|string[]|null
     */
    private function justLetters(string $name)
    {
        return preg_replace('/[^a-zA-Z]+/', '', $name);
    }

    private function instruction()
    {
        echo "\n\nERROR!!!\n\n";
        echo "Missing parameters. Please run script in format:\n\n";
        echo "$ php script.php <street_file_name> <drivers_file_name>\n\n";
        echo "Example:\n\n";
        echo "$ php script.php ./streets.txt ./drivers.txt\n\n";
        exit();
    }

    private function printResults()
    {
        $totalSS = 0;

        printf("\n\n-------------------------------------------\n");
        printf("Destination:         | Driver:\n");
        printf("---------------------|---------------------\n");
        foreach ($this->combined as $key => $row) {
            $drKey = $row[self::DRIVER_KEY];
            printf("%20s |%20s \n", $this->streets[$key], $this->drivers[$drKey]);
            $totalSS += $row[self::SS_POINTS];
        }
        printf("-------------------------------------------\n");
        printf("\nTotal SS: %.3f\n\n", $totalSS);
        printf("-------------------------------------------\n\n\n");
    }
}

$task = new CountSS;
$task->main($argv);
