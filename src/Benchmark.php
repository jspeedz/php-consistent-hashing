<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

/**
 * @todo Write tests..
 */
#[CodeCoverageIgnore]
class Benchmark {
    /**
     * @param string[] $algorithms
     *
     * @return array<string, callable>
     */
    public function getAvailableHashCallbacks(?array $algorithms = null): array {
        if($algorithms === null) {
            $algorithms = hash_algos();
        }

        // Try all possible combinations of X probes hash functions
        $availableHashCallbacks = [];
        foreach($algorithms as $algorithm) {
            $availableHashCallbacks[$algorithm] = function(string $key) use ($algorithm): int|float {
                return hexdec(substr(hash($algorithm, $key), 0, 8));
            };
        }

        return $availableHashCallbacks;
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function fetchKeys(): array {
        $keysContent = file_get_contents(__DIR__ . '/../tests/data/random_strings.json');
        if(!$keysContent) {
            throw new Exception('Could not read random_strings.json');
        }
        $keys = json_decode(
            $keysContent,
            true,
            JSON_THROW_ON_ERROR,
        );
        unset($keysContent);

        if(!is_array($keys)) {
            throw new Exception('Could not decode random_strings.json');
        }

        $ipsContent = file_get_contents(__DIR__ . '/../tests/data/random_ip_addresses.json');
        if(!$ipsContent) {
            throw new Exception('Could not read random_ip_addresses.json');
        }
        $ips = json_decode(
            $ipsContent,
            true,
            JSON_THROW_ON_ERROR,
        );
        unset($ipsContent);

        if(!is_array($ips)) {
            throw new Exception('Could not decode random_ip_addresses.json');
        }
        $keys = array_merge(
            $ips,
            $keys,
        );
        shuffle($keys);

        return $keys;
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return array<int, mixed>
     */
    public function getCombinations(array $array, int $length): array {
        $result = [];
        $keys = array_keys($array);
        $count = count($keys);

        if ($length > $count) {
            return $result;
        }

        $indices = range(0, $length - 1);
        while ($indices[0] <= $count - $length) {
            $combination = [];
            foreach ($indices as $index) {
                $key = $keys[$index];
                $combination[$key] = $array[$key];
            }
            $result[] = $combination;

            $i = $length - 1;
            while ($i >= 0 && $indices[$i] == $count - $length + $i) {
                $i--;
            }

            if ($i >= 0) {
                $indices[$i]++;
                for ($j = $i + 1; $j < $length; $j++) {
                    $indices[$j] = $indices[$j - 1] + 1;
                }
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<mixed, mixed> $array
     */
    public function sortByTwoColumns(
        array &$array,
        int|string $primaryColumn,
        int|string $secondaryColumn,
        int $primaryDirection = SORT_ASC,
        int $secondaryDirection = SORT_ASC,
    ): void {
        $primaryValues = array_column($array, $primaryColumn);
        $secondaryValues = array_column($array, $secondaryColumn);
        array_multisort($primaryValues, $primaryDirection, $secondaryValues, $secondaryDirection, $array);
    }

    public function printProgress(int $iterationCount, int $totalIterations, DateTimeImmutable $startTime): void {
        if($iterationCount % 5 !== 0) {
            return;
        }

        $percentage = round(($iterationCount * 100 / $totalIterations), 2);
        echo 'Progress: ' . $iterationCount . '/' . $totalIterations . ' (' . $percentage . '%) ' .
            'elapsed: ' . (new DateTimeImmutable('now'))
                ->diff($startTime)
                ->format('%H:%I:%S') . PHP_EOL;
    }

    public function generateData(int $dataCount): void {
        $targetDir = __DIR__ . '/../tmp/benchmark/';

        if(!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        exec(
            'php ' . __DIR__ . '/../tests/data/generatedata.php --amount=' . $dataCount . ' --target-dir=' . $targetDir,
        );
    }

    protected function formatNumber(int|float $number): string {
        return str_pad(
            number_format(
                round($number, 2),
                2,
                '.',
                '',
            ),
            7,
            ' ',
            STR_PAD_LEFT,
        );
    }

    /**
     * @param array<int, array<string, string|float>> $results
     */
    public function printResults(array $results): void {
        echo 'Results:' . PHP_EOL;
        foreach($results as $result) {
            $result['name'] = str_pad((string) $result['name'], 5, ' ', STR_PAD_RIGHT);
            $result['distribution'] = str_pad((string) $result['distribution'], 5, ' ', STR_PAD_RIGHT);
            $result['totalTime'] = round((float) $result['totalTime'], 2) . 'ms';
            $result['avgTimePerHash'] = round((float) $result['avgTimePerHash'], 2) . 'ms';
            $result['avgDeviationFromPerfectPercentage'] = $this->formatNumber((float) $result['avgDeviationFromPerfectPercentage']) . '%';

            foreach($result as $title => $value) {
                if(in_array($title, ['name', 'distribution'], true)) {
                    echo '[' . $value . ']';
                    if($title === 'name') {
                        echo ' -- ';
                    }
                    continue;
                }
                echo $title . ': ' . $value . ' -- ';
            }
            echo PHP_EOL;
        }
    }
}