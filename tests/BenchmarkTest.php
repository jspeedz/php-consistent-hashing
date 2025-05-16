<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\Tests;

use Jspeedz\PhpConsistentHashing\Benchmark;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Benchmark::class)]
class BenchmarkTest extends TestCase {
    public function testGetAvailableHashCallbacks(): void {
        $benchmark = new Benchmark();
        $results = $benchmark->getAvailableHashCallbacks();

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        foreach($results as $result) {
            $this->assertIsCallable($result);
        }

        $results = $benchmark->getAvailableHashCallbacks([
            'someHashAlgorithm',
        ]);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        foreach($results as $result) {
            $this->assertIsCallable($result);
        }
    }

    public function testGetCombinations(): void {
        $benchmark = new Benchmark();

        // Test case 1: Normal case
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $length = 2;
        $expected = [
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'c' => 3],
            ['b' => 2, 'c' => 3],
        ];
        $this->assertEquals($expected, $benchmark->getCombinations($array, $length));

        // Test case 2: Length greater than array count
        $array = ['a' => 1, 'b' => 2];
        $length = 3;
        $expected = [];
        $this->assertEquals($expected, $benchmark->getCombinations($array, $length));

        // Test case 3: Length equal to array count
        $array = ['a' => 1, 'b' => 2];
        $length = 2;
        $expected = [
            ['a' => 1, 'b' => 2],
        ];
        $this->assertEquals($expected, $benchmark->getCombinations($array, $length));

        // Test case 4: Length of 1
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $length = 1;
        $expected = [
            ['a' => 1],
            ['b' => 2],
            ['c' => 3],
        ];
        $this->assertEquals($expected, $benchmark->getCombinations($array, $length));

        // Test case 5: Empty array
        $array = [];
        $length = 1;
        $expected = [];
        $this->assertEquals($expected, $benchmark->getCombinations($array, $length));
    }

    public function testSortByTwoColumns(): void {
        $benchmark = new Benchmark();

        // Test case 1: Normal case with ascending sort
        $array = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 22],
            ['name' => 'John', 'age' => 20],
        ];
        $expected = [
            ['name' => 'Jane', 'age' => 22],
            ['name' => 'John', 'age' => 20],
            ['name' => 'John', 'age' => 25],
        ];
        $benchmark->sortByTwoColumns($array, 'name', 'age');
        $this->assertEquals($expected, $array);

        // Test case 2: Descending sort on primary column
        $array = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 22],
            ['name' => 'John', 'age' => 20],
        ];
        $expected = [
            ['name' => 'John', 'age' => 20],
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 22],
        ];
        $benchmark->sortByTwoColumns($array, 'name', 'age', SORT_DESC);
        $this->assertEquals($expected, $array);

        // Test case 3: Descending sort on both columns
        $array = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 22],
            ['name' => 'John', 'age' => 20],
        ];
        $expected = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'John', 'age' => 20],
            ['name' => 'Jane', 'age' => 22],
        ];
        $benchmark->sortByTwoColumns($array, 'name', 'age', SORT_DESC, SORT_DESC);
        $this->assertEquals($expected, $array);

        // Test case 4: Empty array
        $array = [];
        $expected = [];
        $benchmark->sortByTwoColumns($array, 'name', 'age');
        $this->assertEquals($expected, $array);

        // Test case 5: Array with one element
        $array = [['name' => 'John', 'age' => 25]];
        $expected = [['name' => 'John', 'age' => 25]];
        $benchmark->sortByTwoColumns($array, 'name', 'age');
        $this->assertEquals($expected, $array);
    }

    public function testFormatNumber(): void {
        $class = new ReflectionClass(Benchmark::class);
        $method = $class->getMethod('formatNumber');
        $method->setAccessible(true);
        $benchmark = new Benchmark();

        // Test case 1: Integer number
        $number = 123;
        $expected = ' 123.00';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );

        // Test case 2: Float number with two decimal places
        $number = 123.45;
        $expected = ' 123.45';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );

        // Test case 3: Float number with more than two decimal places
        $number = 123.456;
        $expected = ' 123.46';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );

        // Test case 4: Float number with less than two decimal places
        $number = 123.4;
        $expected = ' 123.40';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );

        // Test case 5: Negative number
        $number = -123.4;
        $expected = '-123.40';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );

        // Test case 6: Large number
        $number = 1234567.89;
        $expected = '1234567.89';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );

        // Test case 7: Number less than 7 characters
        $number = 1.2;
        $expected = '   1.20';
        $this->assertEquals(
            $expected,
            $method->invokeArgs(
                $benchmark,
                [
                    $number,
                ],
            ),
        );
    }

    public function testPrintResults(): void {
        $mock = $this->getMockBuilder(Benchmark::class)
            ->onlyMethods(['formatNumber'])
            ->getMock();

        // Mock the formatNumber method to return a fixed value for simplicity
        $mock->method('formatNumber')
            ->willReturnCallback(
                function(int|float $number): string {
                    return str_pad(number_format(round($number, 2), 2, '.', ''), 7, ' ', STR_PAD_LEFT);
                },
            );

        // Test case 1: Normal case
        $results = [
            [
                'name' => 'Test1',
                'distribution' => 'Dist1',
                'totalTime' => 123.456,
                'avgTimePerHash' => 12.3456,
                'avgDeviationFromPerfectPercentage' => 0.1234,
            ],
            [
                'name' => 'Test2',
                'distribution' => 'Dist2',
                'totalTime' => 789.012,
                'avgTimePerHash' => 78.9012,
                'avgDeviationFromPerfectPercentage' => 0.5678,
            ],
        ];

        $expected = 'Results:' . PHP_EOL
            .
            '[Test1] -- [Dist1]totalTime: 123.46ms -- avgTimePerHash: 12.35ms -- avgDeviationFromPerfectPercentage:    0.12% -- ' .
            PHP_EOL
            .
            '[Test2] -- [Dist2]totalTime: 789.01ms -- avgTimePerHash: 78.9ms -- avgDeviationFromPerfectPercentage:    0.57% -- ' .
            PHP_EOL;

        $this->assertEquals($expected, $mock->printResults($results));

        // Test case 2: Empty results
        $results = [];
        $expected = 'Results:' . PHP_EOL;
        $this->assertEquals($expected, $mock->printResults($results));

        // Test case 3: Different lengths of names and distributions
        $results = [
            [
                'name' => 'T',
                'distribution' => 'D',
                'totalTime' => 1.1,
                'avgTimePerHash' => 0.1,
                'avgDeviationFromPerfectPercentage' => 0.01,
            ],
        ];

        $expected = 'Results:' . PHP_EOL
            .
            '[T    ] -- [D    ]totalTime: 1.1ms -- avgTimePerHash: 0.1ms -- avgDeviationFromPerfectPercentage:    0.01% -- ' .
            PHP_EOL;

        $this->assertEquals($expected, $mock->printResults($results));
    }
}
