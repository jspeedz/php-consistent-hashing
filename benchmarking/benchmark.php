<?php declare(strict_types=1);

/**
 * This file is just here to be able to test a bunch of combinations of the hashing functions.
 * To check the performance and accuracy of the distributions.
 *
 * A brute-force way to figure out what works best with the given datasets.
 * Edit as you like to test your own scenario.
 *
 * Use the dumped CSV files in your favorite spreadsheet program.
 * Order the results to your liking, to make analyzing the results a bit easier.
 */

namespace Jspeedz\PhpConsistentHashing\Benchmarking;

use DateTimeImmutable;
use Exception;
use Jspeedz\PhpConsistentHashing\Benchmark;
use Jspeedz\PhpConsistentHashing\HashFunctions\Accurate;
use Jspeedz\PhpConsistentHashing\HashFunctions\Standard;
use Jspeedz\PhpConsistentHashing\MultiProbeConsistentHash;

require __DIR__ . '/../vendor/autoload.php';

set_time_limit(0);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo 'Available hash algos: ' . implode(', ', hash_algos()) . PHP_EOL . PHP_EOL;

// How many data points do we generate?
$dataCount = 35000;
// How many times do we re-generate the data and re-hash it?
$dataIterations = 10;

$nodeDistributions = [
    '50 50' => [
        'node1' => 50,
        'node2' => 50,
    ],
    '25 75' => [
        'node1' => 25,
        'node2' => 75,
    ],
    '20 30 50' => [
        'node1' => 20,
        'node2' => 30,
        'node3' => 50,
    ],
    '25 25 25 25' => [
        'node1' => 100/4,
        'node2' => 100/4,
        'node3' => 100/4,
        'node4' => 100/4,
    ],
    '20 20 20 20 20' => [
        'node1' => 100/5,
        'node2' => 100/5,
        'node3' => 100/5,
        'node4' => 100/5,
        'node5' => 100/5,
    ],
    '16.6 16.6 16.6 16.6 16.6 16.6' => [
        'node1' => 100/6,
        'node2' => 100/6,
        'node3' => 100/6,
        'node4' => 100/6,
        'node5' => 100/6,
        'node6' => 100/6,
    ],
    '14.2857 14.2857 14.2857 14.2857 14.2857 14.2857 14.2857' => [
        'node1' => 100/7,
        'node2' => 100/7,
        'node3' => 100/7,
        'node4' => 100/7,
        'node5' => 100/7,
        'node6' => 100/7,
        'node7' => 100/7,
    ],
    '12.5 12.5 12.5 12.5 12.5 12.5 12.5 12.5' => [
        'node1' => 100/8,
        'node2' => 100/8,
        'node3' => 100/8,
        'node4' => 100/8,
        'node5' => 100/8,
        'node6' => 100/8,
        'node7' => 100/8,
        'node8' => 100/8,
    ],
    '11.1 11.1 11.1 11.1 11.1 11.1 11.1 11.1 11.1 11.1' => [
        'node1' => 100/9,
        'node2' => 100/9,
        'node3' => 100/9,
        'node4' => 100/9,
        'node5' => 100/9,
        'node6' => 100/9,
        'node7' => 100/9,
        'node8' => 100/9,
        'node9' => 100/9,
    ],
    '10 10 10 10 10 10 10 10 10 10' => [
        'node1' => 100/10,
        'node2' => 100/10,
        'node3' => 100/10,
        'node4' => 100/10,
        'node5' => 100/10,
        'node6' => 100/10,
        'node7' => 100/10,
        'node8' => 100/10,
        'node9' => 100/10,
        'node10' => 100/10,
    ],
    '11 11 11 11 11 11 11 11 11 11 11' => [
        'node1' => 100/11,
        'node2' => 100/11,
        'node3' => 100/11,
        'node4' => 100/11,
        'node5' => 100/11,
        'node6' => 100/11,
        'node7' => 100/11,
        'node8' => 100/11,
        'node9' => 100/11,
        'node10' => 100/11,
        'node11' => 100/11,
    ],
    '15 15 25 45' => [
        'node1' => 15,
        'node2' => 15,
        'node3' => 25,
        'node4' => 45,
    ],
    '50 20 20 5 5' => [
        'node1' => 50,
        'node2' => 20,
        'node3' => 20,
        'node4' => 5,
        'node5' => 5,
    ],
    '1 1' => [
        'node1' => 1,
        'node2' => 1,
    ],
    '1 1 1' => [
        'node1' => 1,
        'node2' => 1,
        'node3' => 1,
    ],
    '1 1 1 1' => [
        'node1' => 1,
        'node2' => 1,
        'node3' => 1,
        'node4' => 1,
    ],
    '1 1 1 1 1' => [
        'node1' => 1,
        'node2' => 1,
        'node3' => 1,
        'node4' => 1,
        'node5' => 1,
    ],
];

$benchmark = new Benchmark();

// Use all available hashing algo's or fill the array yourself with some you want to test.
//$availableHashCallbacks = $benchmark->getAvailableHashCallbacks();
$availableHashCallbacks = $benchmark->getAvailableHashCallbacks([
    'crc32',
    'md4',
    'md5',
    'sha1',
    'sha256',
    'sha3-224',
    'sha512',
]);

// How many probes (hashing algos) do we use?
$numberOfProbes = [
    2,
    3,
    4,
];

/** @var array<string,array<int, callable>> $hashFunctionCombinations */
$hashFunctionCombinations = [];
foreach($numberOfProbes as $numberOfProbe) {
    $hashFunctionCombinations = array_merge(
        $hashFunctionCombinations,
        $benchmark->getCombinations($availableHashCallbacks, $numberOfProbe),
    );
}
$hashFunctionCombinations = array_combine(
    array_map(
        static fn(array $algoCallbacks) => implode(', ', array_keys($algoCallbacks)),
        $hashFunctionCombinations,
    ),
    $hashFunctionCombinations,
);

$totalIterations = count($nodeDistributions) * count($hashFunctionCombinations) * $dataIterations;

$hashFunctionCombinations = array_merge(
    [
//        // C'mon, this is just silly..
//        'EVERYTHING-IN-ONE' => $availableHashCallbacks,
        'standard' => (new Standard)(),
        'accurate' => (new Accurate)(),
    ],
    $hashFunctionCombinations,
);

$settings = [
    'dataCount' => $dataCount,
    'dataIterations' => $dataIterations,
    'numberOfProbes' => $numberOfProbes,
    'nodeDistributions' => $nodeDistributions,
    'hashFunctionCombinations' => array_keys($hashFunctionCombinations),
];

// Make sure we generate once, so the data count is correct
$benchmark->generateData($dataCount);

$csvFile = __DIR__ . '/results/results[x].csv';
$i = 0;
do {
    $pickedCsvFile = str_replace('[x]', (string) ++$i, $csvFile);
    if($i > 100) {
        throw new Exception('Could not find a free filename, remove some old ones please');
    }
} while(file_exists($pickedCsvFile));

$fp = fopen($pickedCsvFile, 'w');
file_put_contents(
    str_replace('.csv', '.json', $pickedCsvFile),
    json_encode($settings, JSON_THROW_ON_ERROR),
);

if($fp !== false) {
    fputcsv(
        $fp,
        [
            'name',
            'distribution',
            'totalTime',
            'avgTimePerHash',
            'avgDeviationFromPerfectPercentage',
            'probeCount',
        ],
    );
}

$startTime = new DateTimeImmutable('now');
$results = [];
$totalTime = 0;
$iterationCount = 0;
foreach($nodeDistributions as $nodeDistributionName => $nodeDistribution) {
    foreach($hashFunctionCombinations as $combinationName => $hashFunctionCombination) {
        $totalCombinationTime = $totalCombinationDeviation = 0;
        for($i = 1; $i <= $dataIterations; $i++) {
            $keys = $benchmark->fetchKeys();

            // Generate a new set of data
            $hash = new MultiProbeConsistentHash();
            $hash->setHashFunctions($hashFunctionCombination);

            foreach($nodeDistribution as $nodeName => $nodeDistributionWeight) {
                $hash->addNode($nodeName, $nodeDistributionWeight);
            }

            $distribution = [];
            $time = microtime(true);
            // Do work
            foreach($keys as $key) {
                $pickedNode = $hash->getNode($key);
                $distribution[$pickedNode] ??= 0;
                $distribution[$pickedNode] += 1;
            }
            $time = (microtime(true) - $time) * 1000;

            $total = array_sum($distribution);
            foreach($distribution as $nodeName => &$count) {
                $count = abs($nodeDistribution[$nodeName] - ($count / $total * 100));
            }

            $totalIterationDeviation = array_sum($distribution);
            $totalCombinationDeviation += $totalIterationDeviation;

            $totalCombinationTime += $time;
            $totalTime += $time;
            $iterationCount++;

            if($dataIterations > 1) {
                // Generate new data
                $benchmark->generateData($dataCount);
            }
        }

        $resultName = $combinationName . $nodeDistributionName;
        $results[$resultName] = [
            'avgDeviationFromPerfectPercentage' => $totalCombinationDeviation / $dataIterations,
            'totalTime' => $totalCombinationTime,
            'avgTimePerHash' => $totalCombinationTime / ($dataCount * $dataIterations),
            'name' => $combinationName,
            'distribution' => $nodeDistributionName,
        ];

        if($fp !== false) {
            fputcsv(
                $fp,
                [
                    $results[$resultName]['name'],
                    $results[$resultName]['distribution'],
                    $results[$resultName]['totalTime'],
                    $results[$resultName]['avgTimePerHash'],
                    $results[$resultName]['avgDeviationFromPerfectPercentage'],
                    count($hashFunctionCombination),
                ],
            );
        }

//        echo '----------------' . PHP_EOL;
//        echo 'Distribution [' . $nodeDistributionName . ']' . PHP_EOL;
//        echo 'Probes       [' . $combinationName . ']' . PHP_EOL;
//        echo 'Time: ' . $results[$resultName]['totalTime'] . 'ms' . PHP_EOL;
//        echo 'Time: avg per hash: ' . $results[$resultName]['avgTimePerHash'] . 'ms' . PHP_EOL;
//        echo 'Total average deviation from perfect: ' . $results[$resultName]['avgDeviationFromPerfectPercentage'] . '% (lower is better)' . PHP_EOL;

        $benchmark->printProgress($iterationCount, $totalIterations, $startTime);
    }
}

echo '----------------' . PHP_EOL;
echo '----------------' . PHP_EOL;
echo '----------------' . PHP_EOL;

$benchmark->sortByTwoColumns(
    $results,
    'avgDeviationFromPerfectPercentage',
    'totalTime',
    SORT_ASC,
    SORT_ASC,
);

echo $benchmark->printResults($results);

if($fp !== false) {
    fclose($fp);
}

echo 'Total time: ' . round($totalTime) . 'ms' . PHP_EOL;